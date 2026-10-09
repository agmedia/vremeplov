<?php

namespace App\Exceptions;

use App\Support\DatabaseCapacityReporter;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
        'api_key',
        'webhook_signing_secret',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $exception) {
            try {
                $request = app()->runningInConsole() ? null : request();
                $reportedSafely = app(DatabaseCapacityReporter::class)->report($exception, $request);

                if ($reportedSafely) {
                    // QueryException interpolates bindings into its message in Laravel 8.
                    // The dedicated report above intentionally replaces that unsafe log.
                    return false;
                }
            } catch (Throwable $reportingException) {
                // Capacity diagnostics must never interfere with normal reporting.
            }
        });
    }

}
