<?php

namespace Dennenboom\Pinert\Services;

use Dennenboom\Pinert\Notifications\SlackErrorNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ErrorReporterService
{
    protected string $webhookUrl;
    protected array $excludedExceptions;
    protected int $rateLimitSeconds;
    protected array $environments;
    protected string $minLevel;

    public function __construct()
    {
        $this->webhookUrl = config('pinert.webhook_url');
        $this->excludedExceptions = config('pinert.exclude_exceptions', []);
        $this->rateLimitSeconds = config('pinert.rate_limit_seconds', 60);
        $this->environments = config('pinert.environments', []);
        $this->minLevel = config('pinert.min_level', 'error');
    }

    public function reportToSlack(Throwable $exception, ?array $request = null): void
    {
        try {
            if (empty($this->webhookUrl)) {
                return;
            }

            if (!$this->shouldReport($exception)) {
                return;
            }

            if ($this->isRateLimited($exception)) {
                return;
            }

            $notifiable = new class($this->webhookUrl) {
                protected $webhookUrl;

                public function __construct($url)
                {
                    $this->webhookUrl = $url;
                }

                public function routeNotificationForSlack()
                {
                    return $this->webhookUrl;
                }
            };

            Notification::send($notifiable, new SlackErrorNotification($exception, $request));

            $this->recordNotification($exception);
        } catch (Throwable $e) {
            Log::error('Failed to send error notification to Slack: ' . $e->getMessage());
        }
    }

    public function shouldReport(Throwable $exception): bool
    {
        foreach ($this->excludedExceptions as $excludedClass) {
            if ($exception instanceof $excludedClass) {
                return false;
            }
        }

        $currentEnv = app()->environment();
        if (!empty($this->environments) && !in_array($currentEnv, $this->environments)) {
            return false;
        }

        if (method_exists($exception, 'getCode') && is_numeric($exception->getCode())) {
            $errorCode = $exception->getCode();
            $minLevel = $this->getLogLevelCode($this->minLevel);

            if ($errorCode < $minLevel) {
                return false;
            }
        }

        return true;
    }

    protected function getLogLevelCode(string $level): int
    {
        $levels = [
            'debug'     => 100,
            'info'      => 200,
            'notice'    => 250,
            'warning'   => 300,
            'error'     => 400,
            'critical'  => 500,
            'alert'     => 550,
            'emergency' => 600,
        ];

        return $levels[strtolower($level)] ?? 0;
    }

    protected function isRateLimited(Throwable $exception): bool
    {
        if ($this->rateLimitSeconds <= 0) {
            return false;
        }

        $key = $this->getRateLimitKey($exception);

        return Cache::has($key);
    }

    protected function getRateLimitKey(Throwable $exception): string
    {
        $class = get_class($exception);
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();

        return 'slack_error_notifier:' . md5("{$class}:{$message}:{$file}:{$line}");
    }

    protected function recordNotification(Throwable $exception): void
    {
        if ($this->rateLimitSeconds <= 0) {
            return;
        }

        $key = $this->getRateLimitKey($exception);

        Cache::put($key, true, $this->rateLimitSeconds);
    }
}
