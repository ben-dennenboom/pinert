<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Slack Webhook URL
    |--------------------------------------------------------------------------
    |
    | This is the webhook URL that Slack provides when you create a new
    | incoming webhook integration.
    |
    */
    'webhook_url'           => env('SLACK_ERROR_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Project Name
    |--------------------------------------------------------------------------
    |
    | This is the name of your project that will be displayed in the Slack
    | notification to help identify which project the error came from.
    |
    */
    'project_name'          => env('APP_NAME', 'Laravel Project'),

    /*
    |--------------------------------------------------------------------------
    | Notification Channel
    |--------------------------------------------------------------------------
    |
    | This is the Slack channel where notifications will be sent.
    | Leave empty to use the default channel configured in Slack.
    |
    */
    'channel'               => env('SLACK_ERROR_CHANNEL', null),

    /*
    |--------------------------------------------------------------------------
    | Notification Username
    |--------------------------------------------------------------------------
    |
    | This is the username that will be displayed for the Slack notification.
    |
    */
    'username'              => env('SLACK_ERROR_USERNAME', 'Error Reporter'),

    /*
    |--------------------------------------------------------------------------
    | Notification Icon
    |--------------------------------------------------------------------------
    |
    | This is the emoji icon that will be displayed for the Slack notification.
    |
    */
    'icon'                  => env('SLACK_ERROR_ICON', ':rotating_light:'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Stack Trace Lines
    |--------------------------------------------------------------------------
    |
    | The maximum number of stack trace lines to include in the notification.
    | Set to 0 to include no stack trace.
    |
    */
    'max_stack_trace_lines' => env('SLACK_ERROR_MAX_STACK_TRACE_LINES', 5),

    /*
    |--------------------------------------------------------------------------
    | Minimum Error Level
    |--------------------------------------------------------------------------
    |
    | The minimum error level to send notifications for.
    | Options: 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'
    |
    */
    'min_level'             => env('SLACK_ERROR_MIN_LEVEL', 'error'),

    /*
    |--------------------------------------------------------------------------
    | Enabled Environments
    |--------------------------------------------------------------------------
    |
    | The environments in which error notifications should be sent.
    | Leave empty to enable in all environments.
    |
    */
    'environments'          => explode(',', env('SLACK_ERROR_ENVIRONMENTS', 'production,staging')),

    /*
    |--------------------------------------------------------------------------
    | Exclude Exception Types
    |--------------------------------------------------------------------------
    |
    | A list of exception types that should not be reported to Slack.
    |
    */
    'exclude_exceptions'    => [
        // Common exceptions you may want to exclude
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filter Request Data
    |--------------------------------------------------------------------------
    |
    | Filter out sensitive request data to avoid sending it to Slack.
    |
    */
    'filter_request_data'   => [
        'password',
        'password_confirmation',
        'token',
        'authorization',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'credit_card',
        'card_number',
        'cvv',
        'secret',
        'api_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limit the number of notifications sent to Slack for the same exception
    | to avoid flooding the channel when many errors occur in a short time.
    | Set to 0 to disable rate limiting.
    |
    */
    'rate_limit_seconds'    => env('SLACK_ERROR_RATE_LIMIT', 60),
];
