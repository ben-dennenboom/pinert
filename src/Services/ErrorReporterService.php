<?php

namespace Dennenboom\Pinert\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class ErrorReporterService
{
    protected string $webhookUrl;
    protected array $excludedExceptions;
    protected int $rateLimitSeconds;
    protected array $environments;
    protected string $minLevel;
    protected string $projectName;

    public function __construct()
    {
        $this->webhookUrl = config('pinert.webhook_url');
        $this->excludedExceptions = config('pinert.exclude_exceptions', []);
        $this->rateLimitSeconds = config('pinert.rate_limit_seconds', 60);
        $this->environments = config('pinert.environments', []);
        $this->minLevel = config('pinert.min_level', 'error');
        $this->projectName = config('pinert.project_name');
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

            $payload = $this->prepareSlackPayload($exception, $request);

            $this->sendToSlack($payload);

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

        return true;
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

    protected function prepareSlackPayload(Throwable $exception, ?array $request = null): array
    {
        $request = $request ?? Request::all();
        $environment = app()->environment();
        $url = Request::fullUrl();
        $method = Request::method();
        $userIp = Request::ip();
        $userAgent = Request::header('User-Agent');
        $userId = Auth::id() ?? 'Guest';

        $exceptionClass = get_class($exception);
        $exceptionMessage = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $this->getFormattedTrace($exception);

        $requestData = '';
        if (!empty($request)) {
            $requestData = $this->formatRequestData($request);
        }

        $payload = [
            'username'    => config('pinert.username', 'Error Reporter'),
            'channel'     => config('pinert.channel'),
            'icon_emoji'  => config('pinert.icon', ':rotating_light:'),
            'text'        => "*Error in {$this->projectName} ({$environment})*",
            'attachments' => [
                [
                    'title'    => ":boom: {$exceptionClass}",
                    'text'     => $exceptionMessage,
                    'fallback' => "Error: {$exceptionMessage}",
                    'color'    => 'danger',
                    'fields'   => [
                        [
                            'title' => 'Project',
                            'value' => $this->projectName,
                            'short' => true,
                        ],
                        [
                            'title' => 'Environment',
                            'value' => $environment,
                            'short' => true,
                        ],
                        [
                            'title' => 'URL',
                            'value' => "{$method} {$url}",
                            'short' => true,
                        ],
                        [
                            'title' => 'User ID',
                            'value' => $userId,
                            'short' => true,
                        ],
                        [
                            'title' => 'IP Address',
                            'value' => $userIp,
                            'short' => true,
                        ],
                        [
                            'title' => 'Location',
                            'value' => "{$file}:{$line}",
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];

        $payload['attachments'][0]['fields'][] = [
            'title' => 'User Agent',
            'value' => substr($userAgent, 0, 100) . (strlen($userAgent) > 100 ? '...' : ''),
            'short' => false,
        ];

        if (!empty($requestData)) {
            $payload['attachments'][0]['fields'][] = [
                'title' => 'Request Data',
                'value' => "```{$requestData}```",
                'short' => false,
            ];
        }

        if (!empty($trace)) {
            $payload['attachments'][0]['fields'][] = [
                'title' => 'Stack Trace',
                'value' => "```{$trace}```",
                'short' => false,
            ];
        }

        return $payload;
    }

    protected function getFormattedTrace(Throwable $exception): string
    {
        $maxLines = config('pinert.max_stack_trace_lines', 5);

        if ($maxLines <= 0) {
            return '';
        }

        $trace = $exception->getTrace();
        $traceLines = [];

        $count = 0;
        foreach ($trace as $t) {
            if ($count >= $maxLines) {
                break;
            }

            $class = $t['class'] ?? '';
            $type = $t['type'] ?? '';
            $function = $t['function'] ?? '';
            $file = $t['file'] ?? 'unknown';
            $line = $t['line'] ?? 0;

            if (strpos($file, base_path()) === 0) {
                $file = str_replace(base_path(), '', $file);
                if (strpos($file, '/') === 0) {
                    $file = substr($file, 1);
                }
            }

            $traceLines[] = "#{$count} {$file}({$line}): {$class}{$type}{$function}()";
            $count++;
        }

        return implode("\n", $traceLines);
    }

    protected function formatRequestData(array $data): string
    {
        $filterFields = config('pinert.filter_request_data', [
            'password',
            'password_confirmation',
            'token',
            'authorization',
        ]);

        $filtered = collect($data)->except($filterFields)->toArray();

        $json = json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (strlen($json) > 1000) {
            $json = substr($json, 0, 1000) . '... (truncated)';
        }

        return $json;
    }

    protected function sendToSlack(array $payload): void
    {
        $ch = curl_init($this->webhookUrl);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($payload)),
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            Log::error("cURL Error when sending to Slack: {$error}");
        }

        curl_close($ch);
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
