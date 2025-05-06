<?php

namespace Dennenboom\Pinert\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Throwable;

class SlackErrorNotification extends Notification
{
    use Queueable;

    protected Throwable $exception;
    protected ?array $request;
    protected string $environment;
    protected string $projectName;

    public function __construct(Throwable $exception, ?array $request = null)
    {
        $this->exception = $exception;
        $this->request = $request;
        $this->environment = app()->environment();
        $this->projectName = config('pinert.project_name');
    }

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $request = $this->request ?? Request::all();
        $url = Request::fullUrl();
        $method = Request::method();
        $userIp = Request::ip();
        $userAgent = Request::header('User-Agent');

        $userId = Auth::id() ?? 'Guest';

        $exceptionClass = get_class($this->exception);
        $exceptionMessage = $this->exception->getMessage();
        $file = $this->exception->getFile();
        $line = $this->exception->getLine();
        $trace = $this->getFormattedTrace();

        $slackMessage = (new SlackMessage)
            ->from(config('pinert.username', 'Error Reporter'))
            ->to(config('pinert.channel'))
            ->image(config('pinert.icon', ':rotating_light:'))
            ->error()
            ->content("*Error in {$this->projectName} ({$this->environment})*");

        $attachment = (new SlackAttachment)
            ->title(":boom: {$exceptionClass}")
            ->content($exceptionMessage)
            ->fallback("Error: {$exceptionMessage}")
            ->color('danger')
            ->fields(
                [
                    'Project'     => $this->projectName,
                    'Environment' => $this->environment,
                    'URL'         => "{$method} {$url}",
                    'User ID'     => $userId,
                    'IP Address'  => $userIp,
                    'User Agent'  => substr($userAgent, 0, 100) . (strlen($userAgent) > 100 ? '...' : ''),
                    'Location'    => "{$file}:{$line}",
                ]
            );

        if (!empty($request)) {
            $requestData = $this->formatRequestData($request);
            if (!empty($requestData)) {
                $attachment->field(
                    [
                        'title' => 'Request Data',
                        'value' => "```{$requestData}```",
                        'short' => false,
                    ]
                );
            }
        }

        if (!empty($trace)) {
            $attachment->field([
                                   'title' => 'Stack Trace',
                                   'value' => "```{$trace}```",
                                   'short' => false,
                               ]);
        }

        return $slackMessage->attachment($attachment);
    }

    protected function getFormattedTrace(): string
    {
        $maxLines = config('pinert.max_stack_trace_lines', 5);

        if ($maxLines <= 0) {
            return '';
        }

        $trace = $this->exception->getTrace();
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
}
