<?php

namespace Dennenboom\Pinert\Facades;

use Dennenboom\Pinert\Services\ErrorReporterService;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * @method static bool shouldReport(Throwable $exception)
 * @method static void reportToSlack(Throwable $exception, ?array $request = null)
 *
 * @see ErrorReporterService
 */
class Pinert extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ErrorReporterService::class;
    }
}
