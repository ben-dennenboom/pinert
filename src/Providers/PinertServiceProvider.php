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

        $this->app->extend(ExceptionHandler::class, function ($handler, $app) {
            $handler->reportable(function (\Throwable $e) use ($app) {
                $reporter = $app->make(ErrorReporterService::class);

                if ($reporter->shouldReport($e)) {
                    $reporter->reportToSlack($e, request()->all());

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
