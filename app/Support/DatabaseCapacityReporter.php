<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

class DatabaseCapacityReporter
{
    private const REQUEST_ID_ATTRIBUTE = 'db_capacity_request_id';

    public function report(Throwable $exception, ?Request $request = null): bool
    {
        if (! $this->isConnectionCapacityException($exception)) {
            return false;
        }

        $databaseContext = $this->databaseExceptionContext($exception);
        $event = ($databaseContext['mysql_error_code'] ?? null) === 1040
            ? 'mysql_global_connection_limit_reached'
            : 'mysql_user_connection_limit_reached';
        $context = array_merge(
            $this->requestContext($request, $request === null ? null : 500),
            $databaseContext,
            ['event' => $event]
        );

        $this->writeSafely('critical', $event, $context);

        return true;
    }

    public function isConnectionCapacityException(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if ($current instanceof QueryException || $current instanceof PDOException) {
                $hasStrongMarker = in_array((int) $current->getCode(), [1040, 1203], true)
                    || $this->hasMysqlCapacityErrorInfo($current);
                $hasPdoMessageMarker = ! $current instanceof QueryException
                    && (stripos($current->getMessage(), 'max_user_connections') !== false
                        || stripos($current->getMessage(), 'too many connections') !== false
                        || preg_match('/SQLSTATE\[[A-Z0-9]+\].*\[(?:1040|1203)\]/i', $current->getMessage()) === 1);

                if ($hasStrongMarker || $hasPdoMessageMarker) {
                    return true;
                }
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    public function ensureRequestId(Request $request): string
    {
        $existing = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $forwarded = (string) $request->headers->get('X-Request-ID', '');

        if (preg_match('/\A[A-Za-z0-9._:-]{8,100}\z/', $forwarded) === 1) {
            $requestId = $forwarded;
        } else {
            try {
                $requestId = 'req_'.bin2hex(random_bytes(16));
            } catch (Throwable $exception) {
                $requestId = str_replace('.', '', uniqid('req_', true));
            }
        }

        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);

        return $requestId;
    }

    public function requestContext(?Request $request, ?int $status = null): array
    {
        $context = [
            'method' => null,
            'path' => null,
            'route' => null,
            'status' => $status,
            'user_agent_class' => 'not_applicable',
            'ip_hash' => null,
            'referrer_host' => null,
            'request_id' => null,
            'execution_context' => $request === null ? 'console' : 'http',
            'command_context' => $request === null ? $this->consoleCommand() : 'http',
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
        ];

        if ($request === null) {
            $context['request_id'] = $this->newRequestId();

            return $context;
        }

        try {
            $route = $this->diagnosticRoute($request);
            $context['method'] = strtoupper(substr($request->getMethod(), 0, 12));
            $context['path'] = $this->diagnosticPath($request, $route);
            $context['route'] = $this->routeName($route);
            $context['user_agent_class'] = $this->classifyUserAgent((string) $request->userAgent());
            $context['ip_hash'] = $this->hashIp($request->ip());
            $context['referrer_host'] = $this->referrerHost((string) $request->headers->get('referer', ''));
            $context['request_id'] = $this->ensureRequestId($request);
        } catch (Throwable $exception) {
            // Diagnostic context must never interfere with exception reporting.
            $context['request_id'] = $context['request_id'] ?: $this->newRequestId();
        }

        return $context;
    }

    public function logGuardEvent(string $event, Request $request, ?int $status, array $extra = [], string $level = 'warning'): void
    {
        if (strpos($event, 'http_capacity_guard_') === 0) {
            [$allowed, $suppressed] = $this->guardEventLogAllowance($event);

            if (! $allowed) {
                return;
            }

            $extra['suppressed_since_previous'] = $suppressed;
        }

        $safeExtra = array_intersect_key($extra, array_flip([
            'configured_slots',
            'wait_milliseconds',
            'retry_after_seconds',
            'guard_error_class',
            'suppressed_since_previous',
        ]));

        $this->writeSafely(
            $level,
            $event,
            array_merge($this->requestContext($request, $status), $safeExtra, ['event' => $event])
        );
    }

    /**
     * Permit at most one cross-process log per guard event per second. Failure to
     * coordinate means "do not log" so a traffic spike cannot amplify I/O.
     * MySQL 1040/1203 reports use report() and are intentionally never throttled.
     *
     * @return array{0: bool, 1: int}
     */
    private function guardEventLogAllowance(string $event): array
    {
        $directories = array_unique([
            rtrim((string) config('database_capacity.guard.directory', ''), DIRECTORY_SEPARATOR),
            storage_path('logs'),
        ]);

        foreach ($directories as $directory) {
            $allowance = $this->guardEventLogAllowanceInDirectory($directory, $event);

            if ($allowance !== null) {
                return $allowance;
            }
        }

        return [false, 0];
    }

    /**
     * @return array{0: bool, 1: int}|null
     */
    private function guardEventLogAllowanceInDirectory(string $directory, string $event): ?array
    {
        if ($directory === '' || ! is_dir($directory)) {
            return null;
        }

        $stateFile = '.db-capacity-event-'.substr(hash('sha256', $event), 0, 12).'.state';
        $handle = @fopen($directory.DIRECTORY_SEPARATOR.$stateFile, 'c+');

        if (! is_resource($handle)) {
            return null;
        }

        $locked = false;

        try {
            $wouldBlock = 0;

            if (! @flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                return $wouldBlock ? [false, 0] : null;
            }

            $locked = true;
            @rewind($handle);
            $state = trim((string) @stream_get_contents($handle));
            $parts = preg_split('/\s+/', $state);
            $lastLoggedAt = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            $suppressed = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;
            $now = time();

            if ($lastLoggedAt > $now + 5) {
                $lastLoggedAt = 0;
                $suppressed = 0;
            }

            if ($lastLoggedAt >= $now) {
                $allowed = false;
                $suppressed++;
                $nextState = $lastLoggedAt.' '.$suppressed."\n";
                $suppressedToReport = 0;
            } else {
                $allowed = true;
                $nextState = $now." 0\n";
                $suppressedToReport = $suppressed;
            }

            @rewind($handle);
            $written = @ftruncate($handle, 0)
                && @fwrite($handle, $nextState) === strlen($nextState)
                && @fflush($handle);

            return $written ? [$allowed, $suppressedToReport] : null;
        } finally {
            if ($locked) {
                @flock($handle, LOCK_UN);
            }

            @fclose($handle);
        }
    }

    private function databaseExceptionContext(Throwable $exception): array
    {
        $queryException = null;
        $mysqlCode = null;
        $sqlState = null;
        $current = $exception;

        while ($current instanceof Throwable) {
            if ($queryException === null && $current instanceof QueryException) {
                $queryException = $current;
            }

            if (isset($current->errorInfo) && is_array($current->errorInfo)) {
                $sqlState = isset($current->errorInfo[0]) ? $this->safeToken($current->errorInfo[0], 12) : $sqlState;
                $mysqlCode = isset($current->errorInfo[1]) ? (int) $current->errorInfo[1] : $mysqlCode;
            }

            if ($mysqlCode === null && in_array((int) $current->getCode(), [1040, 1203], true)) {
                $mysqlCode = (int) $current->getCode();
            }

            if ($mysqlCode === null && $current instanceof PDOException) {
                if (stripos($current->getMessage(), 'max_user_connections') !== false) {
                    $mysqlCode = 1203;
                } elseif (stripos($current->getMessage(), 'too many connections') !== false) {
                    $mysqlCode = 1040;
                }
            }

            $current = $current->getPrevious();
        }

        $queryShape = $queryException instanceof QueryException
            ? $this->queryShape((string) $queryException->getSql())
            : ['query_operation' => null, 'query_table' => null];

        return array_merge($queryShape, [
            'mysql_error_code' => $mysqlCode ?: 1203,
            'sql_state' => $sqlState,
            'exception_class' => get_class($exception),
        ]);
    }

    private function queryShape(string $sql): array
    {
        $operation = null;
        $table = null;

        if (preg_match('/\A\s*(select|insert|update|delete|replace|alter|create|drop|truncate|call)\b/i', $sql, $match) === 1) {
            $operation = strtolower($match[1]);
        }

        $patterns = [
            'select' => '/\bfrom\s+([^\s,()]+)/i',
            'insert' => '/\binto\s+([^\s,()]+)/i',
            'replace' => '/\binto\s+([^\s,()]+)/i',
            'update' => '/\A\s*update\s+([^\s,()]+)/i',
            'delete' => '/\bfrom\s+([^\s,()]+)/i',
            'alter' => '/\btable\s+([^\s,()]+)/i',
            'create' => '/\btable\s+(?:if\s+not\s+exists\s+)?([^\s,()]+)/i',
            'drop' => '/\btable\s+(?:if\s+exists\s+)?([^\s,()]+)/i',
            'truncate' => '/\btable\s+([^\s,()]+)/i',
        ];

        if ($operation !== null && isset($patterns[$operation])
            && preg_match($patterns[$operation], $sql, $match) === 1) {
            $table = $this->safeTableName($match[1]);
        }

        return [
            'query_operation' => $operation,
            'query_table' => $table,
        ];
    }

    private function safeTableName(string $candidate): ?string
    {
        $candidate = trim($candidate, "`\"[]");
        $candidate = str_replace(['`', '"', '[', ']'], '', $candidate);

        if ($candidate === '' || strlen($candidate) > 128
            || preg_match('/\A[A-Za-z0-9_$.-]+\z/', $candidate) !== 1) {
            return null;
        }

        return strtolower($candidate);
    }

    private function hasMysqlCapacityErrorInfo(Throwable $exception): bool
    {
        return isset($exception->errorInfo)
            && is_array($exception->errorInfo)
            && isset($exception->errorInfo[1])
            && in_array((int) $exception->errorInfo[1], [1040, 1203], true);
    }

    private function diagnosticRoute(Request $request)
    {
        $route = $request->route();

        if (is_object($route)) {
            return $route;
        }

        try {
            $router = app('router');

            if (is_object($router) && method_exists($router, 'getRoutes')) {
                return $router->getRoutes()->match($request);
            }
        } catch (Throwable $exception) {
            // Unknown routes retain only a stable HMAC fingerprint below.
        }

        return null;
    }

    private function routeName($route): ?string
    {
        if (! is_object($route)) {
            return null;
        }

        $name = method_exists($route, 'getName') ? $route->getName() : null;

        if (! is_string($name) || $name === '') {
            $name = method_exists($route, 'uri') ? $route->uri() : null;
        }

        return is_string($name) ? $this->safeToken($name, 160) : null;
    }

    private function diagnosticPath(Request $request, $route): string
    {
        if (is_object($route) && method_exists($route, 'uri')) {
            $uri = $route->uri();

            if (is_string($uri) && $uri !== '') {
                return substr('/'.ltrim($uri, '/'), 0, 512);
            }
        }

        $key = (string) config('database_capacity.ip_hash_key', '');
        $key = $key !== '' ? $key : 'database-capacity-diagnostic';
        $path = '/'.ltrim($request->path(), '/');

        return 'unresolved:hmac-sha256:'.hash_hmac('sha256', $path, $key);
    }

    private function classifyUserAgent(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if ($userAgent === '') {
            return 'missing';
        }

        if ($this->containsAny($userAgent, [
            'meta-externalagent',
            'meta-externalfetcher',
            'reflectionbot',
            'gptbot',
            'chatgpt-user',
            'claudebot',
            'perplexitybot',
            'google-extended',
            'bytespider',
            'cohere-ai',
            'anthropic-ai',
            'ccbot',
        ])) {
            return 'ai_crawler';
        }

        if ($this->containsAny($userAgent, ['facebookexternalhit', 'twitterbot', 'linkedinbot', 'slackbot', 'whatsapp', 'telegrambot'])) {
            return 'social_preview';
        }

        if ($this->containsAny($userAgent, ['googlebot', 'bingbot', 'yandexbot', 'duckduckbot', 'baiduspider', 'applebot'])) {
            return 'search_crawler';
        }

        if ($this->containsAny($userAgent, ['uptime', 'pingdom', 'statuscake', 'healthcheck', 'better uptime'])) {
            return 'monitor';
        }

        if ($this->containsAny($userAgent, ['curl/', 'wget/', 'guzzlehttp', 'postmanruntime', 'python-requests'])) {
            return 'automated_client';
        }

        return strpos($userAgent, 'mozilla/') !== false ? 'browser' : 'other';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $key = (string) config('database_capacity.ip_hash_key', '');
        $key = $key !== '' ? $key : 'database-capacity-diagnostic';

        return 'hmac-sha256:'.hash_hmac('sha256', $ip, $key);
    }

    private function referrerHost(string $referrer): ?string
    {
        if ($referrer === '') {
            return null;
        }

        $host = @parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return preg_match('/\A[A-Za-z0-9.-]{1,253}\z/', $host) === 1 ? $host : null;
    }

    private function consoleCommand(): ?string
    {
        $command = isset($_SERVER['argv'][1]) && is_string($_SERVER['argv'][1])
            ? $_SERVER['argv'][1]
            : null;

        return $command !== null && preg_match('/\A[A-Za-z0-9:_-]{1,100}\z/', $command) === 1
            ? $command
            : null;
    }

    private function safeToken($value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return preg_match('/\A[A-Za-z0-9._:\/-]+\z/', $value) === 1
            ? substr($value, 0, $maxLength)
            : null;
    }

    private function newRequestId(): string
    {
        try {
            return 'req_'.bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            return str_replace('.', '', uniqid('req_', true));
        }
    }

    private function writeSafely(string $level, string $message, array $context): void
    {
        try {
            Log::channel('db-capacity')->log($level, $message, $context);
        } catch (Throwable $exception) {
            $safeMessage = preg_replace('/[^A-Za-z0-9._:-]/', '_', substr($message, 0, 100));
            $payload = json_encode([
                'level' => $level,
                'message' => $safeMessage,
                'context' => $context,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($payload)) {
                $payload = '{"message":"db_capacity_diagnostic_encoding_failed"}';
            }

            // PHP's process error log is a DB-independent last resort.
            @error_log('[db-capacity] '.$payload);
        }
    }
}
