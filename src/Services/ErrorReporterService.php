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
    protected string $taskBoardUrl;
    protected string $taskBoardKey;
    protected array $excludedExceptions;
    protected int $rateLimitSeconds;
    protected array $environments;
    protected string $minLevel;
    protected string $projectName;

    public function __construct()
    {
        $this->webhookUrl = config('pinert.webhook_url') ?? '';
        $this->taskBoardUrl = config('pinert.taskboard_url') ?? '';
        $this->taskBoardKey = config('pinert.taskboard_key') ?? '';
        $this->excludedExceptions = config('pinert.exclude_exceptions', []);
        $this->rateLimitSeconds = config('pinert.rate_limit_seconds', 60);
        $this->environments = config('pinert.environments', []);
        $this->minLevel = config('pinert.min_level', 'error');
        $this->projectName = config('pinert.project_name');
    }

    public function report(Throwable $exception, ?array $request = null): void
    {
        try {
            if (!$this->hasSlack() && !$this->hasTaskBoard()) {
                return;
            }

            if (!$this->shouldReport($exception)) {
                return;
            }

            if ($this->isRateLimited($exception)) {
                return;
            }

            if ($this->hasSlack()) {
                $this->safely('Slack', fn () => $this->sendToSlack($this->prepareSlackPayload($exception, $request)));
            }

            if ($this->hasTaskBoard()) {
                $this->safely('TaskBoard', fn () => $this->sendToTaskBoard($this->prepareTaskBoardPayload($exception, $request)));
            }

            $this->recordNotification($exception);
        } catch (Throwable $e) {
            Log::error('Failed to send error notification: ' . $e->getMessage());
        }
    }

    /**
     * @deprecated Use report(), which also sends to TaskBoard when configured.
     */
    public function reportToSlack(Throwable $exception, ?array $request = null): void
    {
        $this->report($exception, $request);
    }

    protected function hasSlack(): bool
    {
        return !empty($this->webhookUrl);
    }

    protected function hasTaskBoard(): bool
    {
        return !empty($this->taskBoardUrl) && !empty($this->taskBoardKey);
    }

    /**
     * A failing channel must not keep the other one from receiving the error.
     */
    protected function safely(string $channel, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::error("Failed to send error notification to {$channel}: " . $e->getMessage());
        }
    }

    public function shouldReport(Throwable $exception): bool
    {
        foreach ($this->excludedExceptions as $excluded) {
            if (is_callable($excluded) && $excluded($exception)) {
                return false;
            }

            if (is_string($excluded) && $exception instanceof $excluded) {
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

    protected function prepareTaskBoardPayload(Throwable $exception, ?array $request = null): array
    {
        $request = $request ?? Request::all();
        $file = $this->relativePath($exception->getFile());
        $userId = Auth::id();

        return [
            'exception'    => get_class($exception),
            'message'      => mb_substr($exception->getMessage(), 0, 5000),
            'file'         => $file,
            'line'         => $exception->getLine(),
            'environment'  => app()->environment(),
            'url'          => app()->runningInConsole() ? null : mb_substr(Request::fullUrl(), 0, 2000),
            'method'       => app()->runningInConsole() ? null : Request::method(),
            'user_id'      => $userId === null ? null : (string) $userId,
            'trace'        => $this->getFormattedTrace($exception, config('pinert.taskboard_stack_trace_lines', 20)) ?: null,
            'request_data' => empty($request) ? null : $this->formatRequestData($request),
            'fingerprint'  => $this->getFingerprint($exception),
            'occurred_at'  => now()->toIso8601String(),
        ];
    }

    /**
     * Identifies "the same error" so TaskBoard can group repeats on one bug.
     * The message is left out because it often holds ids, and the path is
     * relative so a new release directory doesn't change the fingerprint.
     */
    protected function getFingerprint(Throwable $exception): string
    {
        $class = get_class($exception);
        $file = $this->relativePath($exception->getFile());
        $line = $exception->getLine();

        return hash('sha256', "{$class}|{$file}|{$line}");
    }

    protected function relativePath(string $file): string
    {
        if (strpos($file, base_path()) !== 0) {
            return $file;
        }

        return ltrim(substr($file, strlen(base_path())), '/');
    }

    protected function getFormattedTrace(Throwable $exception, ?int $maxLines = null): string
    {
        $maxLines = $maxLines ?? (int) config('pinert.max_stack_trace_lines', 5);

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

            $file = $this->relativePath($file);

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

    protected function sendToTaskBoard(array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $ch = curl_init($this->taskBoardUrl);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$this->taskBoardKey}",
            'Content-Length: ' . strlen($body),
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            Log::error("cURL Error when sending to TaskBoard: {$error}");

            return;
        }

        if ($status >= 400) {
            Log::error("TaskBoard rejected the error notification ({$status}): " . substr((string) $result, 0, 500));
        }
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
