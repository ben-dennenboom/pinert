<?php

namespace Dennenboom\Pinert\Providers;

use Dennenboom\Pinert\Services\ErrorReporterService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class PinertServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/pinert.php',
            'pinert'
        );

        $this->app->singleton(ErrorReporterService::class, function ($app) {
            return new ErrorReporterService();
        });

        // Packages that wrap the handler, such as Collision in the console, make
        // this extender run again. A second callback would count errors twice.
        $isRegistered = false;

        $this->app->extend(ExceptionHandler::class, function ($handler, $app) use (&$isRegistered) {
            if ($isRegistered) {
                return $handler;
            }

            $isRegistered = true;

            $handler->reportable(function (\Throwable $e) use ($app) {
                $reporter = $app->make(ErrorReporterService::class);

                if ($reporter->shouldReport($e)) {
                    $reporter->report($e, request()->all());

                    return true;
                }

                return null;
            });

            return $handler;
        });
    }

    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../../config/pinert.php' => config_path('pinert.php'),
            ],
            'config'
        );
    }
}
