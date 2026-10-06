<?php

namespace App\Http\Middleware;

use App\Support\DatabaseCapacityReporter;
use App\Support\DatabaseCapacityLeaseManager;
use App\Support\FilesystemSemaphore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LimitConcurrentDatabaseRequests
{
    /** @var \App\Support\DatabaseCapacityReporter */
    private $reporter;

    /** @var \App\Support\DatabaseCapacityLeaseManager */
    private $leases;

    public function __construct(DatabaseCapacityReporter $reporter, DatabaseCapacityLeaseManager $leases)
    {
        $this->reporter = $reporter;
        $this->leases = $leases;
    }

    public function handle(Request $request, Closure $next)
    {
        $requestId = $this->reporter->ensureRequestId($request);

        if (! (bool) config('database_capacity.guard.enabled', false)) {
            return $this->withRequestId($next($request), $requestId);
        }

        $slots = max(1, (int) config('database_capacity.guard.slots', 40));
        $waitMilliseconds = max(0, (int) config('database_capacity.guard.wait_milliseconds', 150));
        $retryAfter = max(1, (int) config('database_capacity.guard.retry_after_seconds', 1));
        $directory = (string) config('database_capacity.guard.directory', storage_path('framework/db-capacity'));

        try {
            $lease = (new FilesystemSemaphore($directory, $slots))->acquire($waitMilliseconds);
        } catch (Throwable $exception) {
            $this->reporter->logGuardEvent('http_capacity_guard_unavailable', $request, 503, [
                'configured_slots' => $slots,
                'wait_milliseconds' => $waitMilliseconds,
                'retry_after_seconds' => $retryAfter,
                'guard_error_class' => get_class($exception),
            ], 'error');

            return $this->capacityResponse($request, $requestId, $retryAfter);
        }

        if ($lease === null) {
            $this->reporter->logGuardEvent('http_capacity_guard_rejected', $request, 503, [
                'configured_slots' => $slots,
                'wait_milliseconds' => $waitMilliseconds,
                'retry_after_seconds' => $retryAfter,
            ]);

            return $this->capacityResponse($request, $requestId, $retryAfter);
        }

        $this->leases->hold($request, $lease);

        return $this->withRequestId($next($request), $requestId);
    }

    private function capacityResponse(Request $request, string $requestId, int $retryAfter): Response
    {
        $headers = [
            'Retry-After' => (string) $retryAfter,
            'X-Request-ID' => $requestId,
            'Cache-Control' => 'no-store, private',
        ];

        if ($request->expectsJson()) {
            $headers['Content-Type'] = 'application/json';

            return new Response(
                json_encode([
                    'message' => 'Usluga je trenutačno zauzeta. Pokušajte ponovno za nekoliko trenutaka.',
                    'request_id' => $requestId,
                ], JSON_UNESCAPED_UNICODE),
                503,
                $headers
            );
        }

        $headers['Content-Type'] = 'text/plain; charset=UTF-8';

        return new Response(
            'Usluga je trenutačno zauzeta. Pokušajte ponovno za nekoliko trenutaka.',
            503,
            $headers
        );
    }

    private function withRequestId($response, string $requestId)
    {
        if ($response instanceof Response) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }
}
