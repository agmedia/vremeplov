<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use App\Support\DatabaseCapacityReporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class DatabaseCapacityReporterTest extends TestCase
{
    public function test_detects_mysql_1203_in_a_wrapped_query_exception(): void
    {
        $exception = new RuntimeException('Controller failed', 0, $this->capacityQueryException());

        $this->assertTrue((new DatabaseCapacityReporter())->isConnectionCapacityException($exception));
    }

    public function test_detects_error_info_even_when_message_and_exception_code_do_not_contain_1203(): void
    {
        $pdo = new PDOException('Connection refused by server');
        $pdo->errorInfo = ['HY000', 1203, 'Connection capacity reached'];

        $this->assertTrue((new DatabaseCapacityReporter())->isConnectionCapacityException($pdo));
    }

    public function test_detects_1203_from_standard_pdo_message_when_error_info_is_missing(): void
    {
        $pdo = new PDOException('SQLSTATE[HY000] [1203] Connection capacity reached');

        $this->assertTrue((new DatabaseCapacityReporter())->isConnectionCapacityException($pdo));
    }

    public function test_detects_mysql_global_connection_limit_from_code_error_info_and_message(): void
    {
        $byCode = new PDOException('Connection unavailable', 1040);
        $byErrorInfo = new PDOException('Connection unavailable');
        $byErrorInfo->errorInfo = ['HY000', 1040, 'Server capacity reached'];
        $byMessage = new PDOException('SQLSTATE[HY000] [1040] Too many connections');
        $reporter = new DatabaseCapacityReporter();

        $this->assertTrue($reporter->isConnectionCapacityException($byCode));
        $this->assertTrue($reporter->isConnectionCapacityException($byErrorInfo));
        $this->assertTrue($reporter->isConnectionCapacityException($byMessage));
    }

    public function test_global_connection_limit_uses_a_distinct_safe_event(): void
    {
        $pdo = new PDOException('SQLSTATE[HY000] [1040] Too many connections', 1040);
        $pdo->errorInfo = ['HY000', 1040, 'Too many connections'];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->with('critical', 'mysql_global_connection_limit_reached', Mockery::on(function (array $context) {
                return ($context['event'] ?? null) === 'mysql_global_connection_limit_reached'
                    && ($context['mysql_error_code'] ?? null) === 1040;
            }));
        Log::shouldReceive('channel')->once()->with('db-capacity')->andReturn($logger);

        $this->assertTrue((new DatabaseCapacityReporter())->report($pdo));
    }

    public function test_does_not_treat_non_database_exception_as_capacity_incident(): void
    {
        $exception = new RuntimeException('max_user_connections was mentioned in a validation message');

        $this->assertFalse((new DatabaseCapacityReporter())->isConnectionCapacityException($exception));
    }

    public function test_capacity_marker_and_unrelated_pdo_must_not_be_on_different_chain_nodes(): void
    {
        $pdo = new PDOException('SQLSTATE[HY000] Connection failed for another reason');
        $exception = new RuntimeException('max_user_connections validation text', 0, $pdo);

        $this->assertFalse((new DatabaseCapacityReporter())->isConnectionCapacityException($exception));
    }

    public function test_query_binding_cannot_spoof_capacity_marker(): void
    {
        $pdo = new PDOException('SQLSTATE[HY000] Generic query failure');
        $query = new QueryException(
            'select * from `settings` where `value` = ?',
            ['max_user_connections'],
            $pdo
        );

        $this->assertFalse((new DatabaseCapacityReporter())->isConnectionCapacityException($query));
    }

    public function test_context_is_useful_but_excludes_sql_bindings_and_raw_identifiers(): void
    {
        config(['database_capacity.ip_hash_key' => 'test-only-key']);

        $request = Request::create('/api/products/search?query=private', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.44',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64)',
            'HTTP_REFERER' => 'https://shop.example.test/private/customer/path?token=secret',
            'HTTP_X_REQUEST_ID' => 'upstream-request-1234',
        ]);
        $route = (new Route('GET', '/api/products/search', function () {
            return null;
        }))->name('api.products.search');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        $loggedContext = null;
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->with('critical', 'mysql_user_connection_limit_reached', Mockery::type('array'))
            ->andReturnUsing(function ($level, $message, array $context) use (&$loggedContext) {
                $loggedContext = $context;
            });
        Log::shouldReceive('channel')->once()->with('db-capacity')->andReturn($logger);

        $reporter = new DatabaseCapacityReporter();
        $this->assertTrue($reporter->report($this->capacityQueryException(), $request));

        $context = $loggedContext;

        $this->assertSame('GET', $context['method']);
        $this->assertSame('/api/products/search', $context['path']);
        $this->assertSame('api.products.search', $context['route']);
        $this->assertSame(500, $context['status']);
        $this->assertSame('browser', $context['user_agent_class']);
        $this->assertStringStartsWith('hmac-sha256:', $context['ip_hash']);
        $this->assertSame('shop.example.test', $context['referrer_host']);
        $this->assertSame('upstream-request-1234', $context['request_id']);
        $this->assertSame('select', $context['query_operation']);
        $this->assertSame('inventory.products', $context['query_table']);
        $this->assertSame(1203, $context['mysql_error_code']);
        $this->assertSame('HY000', $context['sql_state']);
        $this->assertIsInt($context['memory_usage_bytes']);
        $this->assertSame('mysql_user_connection_limit_reached', $context['event']);

        $encoded = json_encode($context);
        $this->assertStringNotContainsString('private-isbn-binding', $encoded);
        $this->assertStringNotContainsString('isbn =', $encoded);
        $this->assertStringNotContainsString('203.0.113.44', $encoded);
        $this->assertStringNotContainsString('/private/customer/path', $encoded);
    }

    public function test_logger_failure_does_not_escape_the_reporter(): void
    {
        $fallbackLog = tempnam(sys_get_temp_dir(), 'db-capacity-fallback-');
        $previousErrorLog = ini_get('error_log');
        ini_set('error_log', $fallbackLog);

        Log::shouldReceive('channel')
            ->once()
            ->with('db-capacity')
            ->andThrow(new RuntimeException('Filesystem unavailable'));

        try {
            $reported = (new DatabaseCapacityReporter())->report($this->capacityQueryException());
            $fallbackContents = (string) file_get_contents($fallbackLog);
        } finally {
            ini_set('error_log', $previousErrorLog);
            @unlink($fallbackLog);
        }

        $this->assertTrue($reported);
        $this->assertStringContainsString('[db-capacity]', $fallbackContents);
        $this->assertStringContainsString('mysql_user_connection_limit_reached', $fallbackContents);
        $this->assertStringNotContainsString('private-isbn-binding', $fallbackContents);
        $this->assertStringNotContainsString('isbn =', $fallbackContents);
    }

    public function test_capacity_incident_replaces_unsafe_default_query_exception_log(): void
    {
        $reporter = Mockery::mock(DatabaseCapacityReporter::class);
        $reporter->shouldReceive('report')->once()->andReturn(true);
        $this->app->instance(DatabaseCapacityReporter::class, $reporter);

        $defaultLogger = Mockery::mock(LoggerInterface::class);
        $defaultLogger->shouldNotReceive('error');
        $this->app->instance(LoggerInterface::class, $defaultLogger);

        (new Handler($this->app))->report($this->capacityQueryException());

        $this->addToAssertionCount(1);
    }

    public function test_sensitive_path_values_are_replaced_by_route_placeholders(): void
    {
        $token = str_repeat('private-token-', 6);
        $request = Request::create('/reset-password/'.$token, 'GET');
        $route = (new Route('GET', 'reset-password/{token}', function () {
            return null;
        }))->name('reset.password.get');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        $context = (new DatabaseCapacityReporter())->requestContext($request, 500);

        $this->assertSame('/reset-password/{token}', $context['path']);
        $this->assertStringNotContainsString($token, json_encode($context));
    }

    public function test_long_tokens_are_replaced_by_a_matched_route_template(): void
    {
        $token = '0123456789abcdef0123456789abcdef';
        $request = Request::create('/zahtjev-za-recenziju/'.$token, 'GET');

        $context = (new DatabaseCapacityReporter())->requestContext($request, 503);

        $this->assertSame('/zahtjev-za-recenziju/{token}', $context['path']);
        $this->assertStringNotContainsString($token, json_encode($context));
    }

    public function test_short_dynamic_ids_are_replaced_by_a_catch_all_route_template(): void
    {
        $request = Request::create('/definitely-not-a-real-route/12345', 'GET');

        $context = (new DatabaseCapacityReporter())->requestContext($request, 503);

        $this->assertSame('/{group}/{cat?}/{subcat?}/{prod?}', $context['path']);
        $this->assertStringNotContainsString('12345', json_encode($context));
        $this->assertStringNotContainsString('/definitely-not-a-real-route', json_encode($context));
    }

    public function test_known_route_is_safely_identified_before_dispatch(): void
    {
        $request = Request::create('/pretrazi/suggest', 'GET');

        $context = (new DatabaseCapacityReporter())->requestContext($request, 503);

        $this->assertSame('/pretrazi/suggest', $context['path']);
        $this->assertSame('pretrazi.suggest', $context['route']);
    }

    /**
     * @dataProvider aiCrawlerUserAgents
     */
    public function test_ai_crawlers_have_a_dedicated_user_agent_class(string $userAgent): void
    {
        $request = Request::create('/knjige', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        $context = (new DatabaseCapacityReporter())->requestContext($request, 500);

        $this->assertSame('ai_crawler', $context['user_agent_class']);
    }

    public function aiCrawlerUserAgents(): array
    {
        return [
            'Meta crawler' => ['Mozilla/5.0 (compatible; meta-externalagent/1.1)'],
            'ReflectionBot' => ['ReflectionBot/1.0'],
            'ChatGPT fetcher' => ['Mozilla/5.0; ChatGPT-User/1.0'],
            'Perplexity crawler' => ['Mozilla/5.0 (compatible; PerplexityBot/1.0)'],
            'Common Crawl' => ['CCBot/2.0'],
        ];
    }

    private function capacityQueryException(): QueryException
    {
        $pdo = new PDOException(
            "SQLSTATE[HY000] [1203] User 'app' already has more than 'max_user_connections' active connections",
            1203
        );
        $pdo->errorInfo = ['HY000', 1203, 'User connection limit reached'];

        return new QueryException(
            'select * from `inventory`.`products` where `isbn` = ?',
            ['private-isbn-binding'],
            $pdo
        );
    }
}
