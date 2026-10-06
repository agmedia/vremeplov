<?php

namespace Tests\Feature;

use App\Http\Middleware\LimitConcurrentDatabaseRequests;
use App\Support\DatabaseCapacityLeaseManager;
use App\Support\DatabaseCapacityReporter;
use App\Support\FilesystemSemaphore;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DatabaseCapacityGuardTest extends TestCase
{
    /** @var string */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/db-capacity-'.bin2hex(random_bytes(8)));
        config([
            'database_capacity.guard.enabled' => true,
            'database_capacity.guard.slots' => 1,
            'database_capacity.guard.wait_milliseconds' => 0,
            'database_capacity.guard.retry_after_seconds' => 2,
            'database_capacity.guard.directory' => $this->directory,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (new \FilesystemIterator($this->directory) as $file) {
                @unlink($file->getPathname());
            }

            @rmdir($this->directory);
        } elseif (is_file($this->directory)) {
            @unlink($this->directory);
        }

        parent::tearDown();
    }

    public function test_full_guard_returns_retryable_503_without_running_application_code(): void
    {
        $heldLease = (new FilesystemSemaphore($this->directory, 1))->acquire(0);
        $nextWasCalled = false;
        $reporter = Mockery::mock(DatabaseCapacityReporter::class);
        $reporter->shouldReceive('ensureRequestId')->once()->andReturn('request-capacity-test');
        $reporter->shouldReceive('logGuardEvent')
            ->once()
            ->with('http_capacity_guard_rejected', Mockery::type(Request::class), 503, [
                'configured_slots' => 1,
                'wait_milliseconds' => 0,
                'retry_after_seconds' => 2,
            ]);

        try {
            $response = (new LimitConcurrentDatabaseRequests($reporter, app(DatabaseCapacityLeaseManager::class)))->handle(
                Request::create('/api/products', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']),
                function () use (&$nextWasCalled) {
                    $nextWasCalled = true;

                    return new Response('should not run');
                }
            );
        } finally {
            $heldLease->release();
        }

        $this->assertFalse($nextWasCalled);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('2', $response->headers->get('Retry-After'));
        $this->assertSame('request-capacity-test', $response->headers->get('X-Request-ID'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_slot_stays_held_after_downstream_exception_until_lifecycle_cleanup(): void
    {
        $reporter = new DatabaseCapacityReporter();
        $leases = new DatabaseCapacityLeaseManager();
        $middleware = new LimitConcurrentDatabaseRequests($reporter, $leases);
        $request = Request::create('/fails', 'GET');

        try {
            $middleware->handle($request, function () {
                throw new RuntimeException('Downstream failure');
            });
            $this->fail('Expected downstream exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Downstream failure', $exception->getMessage());
        }

        $this->assertNull((new FilesystemSemaphore($this->directory, 1))->acquire(0));

        $leases->release($request);
        $lease = (new FilesystemSemaphore($this->directory, 1))->acquire(0);
        $this->assertNotNull($lease);
        $lease->release();
    }

    public function test_guard_failure_fails_closed_and_records_diagnostic_event(): void
    {
        touch($this->directory);
        $nextWasCalled = false;

        $reporter = Mockery::mock(DatabaseCapacityReporter::class);
        $reporter->shouldReceive('ensureRequestId')->once()->andReturn('request-fail-closed');
        $reporter->shouldReceive('logGuardEvent')
            ->once()
            ->with(
                'http_capacity_guard_unavailable',
                Mockery::type(Request::class),
                503,
                Mockery::on(function (array $context) {
                    return $context['configured_slots'] === 1
                        && $context['wait_milliseconds'] === 0
                        && $context['retry_after_seconds'] === 2
                        && $context['guard_error_class'] === RuntimeException::class;
                }),
                'error'
            );

        $response = (new LimitConcurrentDatabaseRequests($reporter, app(DatabaseCapacityLeaseManager::class)))->handle(
            Request::create('/still-works', 'GET'),
            function () use (&$nextWasCalled) {
                $nextWasCalled = true;

                return new Response('should not run');
            }
        );

        $this->assertFalse($nextWasCalled);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('request-fail-closed', $response->headers->get('X-Request-ID'));
    }

    public function test_guard_is_disabled_without_touching_filesystem_when_configured_off(): void
    {
        config(['database_capacity.guard.enabled' => false]);
        $reporter = Mockery::mock(DatabaseCapacityReporter::class);
        $reporter->shouldReceive('ensureRequestId')->once()->andReturn('request-disabled');

        $response = (new LimitConcurrentDatabaseRequests($reporter, app(DatabaseCapacityLeaseManager::class)))->handle(
            Request::create('/local', 'GET'),
            function () {
                return new Response('OK');
            }
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse(file_exists($this->directory));
    }

    public function test_global_guard_holds_slot_through_streaming_and_kernel_termination(): void
    {
        $heldDuringStream = false;
        $databaseConnection = null;
        $request = Request::create('/_capacity-stream-test', 'GET');
        $middleware = app(LimitConcurrentDatabaseRequests::class);
        $response = $middleware->handle($request, function () use (&$heldDuringStream, &$databaseConnection) {
            $databaseConnection = DB::connection();
            $databaseConnection->getPdo();

            return new StreamedResponse(function () use (&$heldDuringStream) {
                $probe = (new FilesystemSemaphore($this->directory, 1))->acquire(0);
                $heldDuringStream = $probe === null;

                if ($probe !== null) {
                    $probe->release();
                }

                echo 'chunk';
            });
        });

        $kernel = app(HttpKernelContract::class);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertNull((new FilesystemSemaphore($this->directory, 1))->acquire(0));
        $this->assertNotNull($databaseConnection->getRawPdo());

        ob_start();
        $response->sendContent();
        ob_end_clean();

        $this->assertTrue($heldDuringStream);
        $this->assertNotNull($databaseConnection->getRawPdo());

        $kernel->terminate($request, $response);

        $this->assertNull($databaseConnection->getRawPdo());
        $lease = (new FilesystemSemaphore($this->directory, 1))->acquire(0);
        $this->assertNotNull($lease);
        $lease->release();
    }

    public function test_rejection_diagnostics_are_rate_limited_across_reporter_instances(): void
    {
        mkdir($this->directory, 0775, true);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->with('warning', 'http_capacity_guard_rejected', Mockery::on(function (array $context) {
                return $context['suppressed_since_previous'] === 0;
            }));
        Log::shouldReceive('channel')->once()->with('db-capacity')->andReturn($logger);

        $request = Request::create('/search', 'GET');
        $extra = [
            'configured_slots' => 1,
            'wait_milliseconds' => 0,
            'retry_after_seconds' => 2,
        ];

        (new DatabaseCapacityReporter())->logGuardEvent(
            'http_capacity_guard_rejected',
            $request,
            503,
            $extra
        );
        (new DatabaseCapacityReporter())->logGuardEvent(
            'http_capacity_guard_rejected',
            $request,
            503,
            $extra
        );

        $this->addToAssertionCount(1);
    }

    public function test_guard_failure_diagnostics_are_also_rate_limited(): void
    {
        mkdir($this->directory, 0775, true);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->with('error', 'http_capacity_guard_unavailable', Mockery::on(function (array $context) {
                return $context['suppressed_since_previous'] === 0;
            }));
        Log::shouldReceive('channel')->once()->with('db-capacity')->andReturn($logger);

        $request = Request::create('/search', 'GET');
        $extra = [
            'configured_slots' => 1,
            'wait_milliseconds' => 0,
            'retry_after_seconds' => 2,
            'guard_error_class' => RuntimeException::class,
        ];

        foreach ([new DatabaseCapacityReporter(), new DatabaseCapacityReporter()] as $reporter) {
            $reporter->logGuardEvent(
                'http_capacity_guard_unavailable',
                $request,
                503,
                $extra,
                'error'
            );
        }

        $this->addToAssertionCount(1);
    }
}
