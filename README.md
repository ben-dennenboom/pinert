# Pinert

Pinert or Pine Alert is a Laravel package that sends detailed error notifications to Slack when exceptions occur in your Laravel applications. Perfect for multiple project environments where you need to monitor errors across different applications using the same Slack webhook.

## Requirements

- PHP 8.0 or higher (tested up to PHP 8.5)
- Laravel 9, 10, 11, 12 or 13

## Installation

You can install the package via composer:

```bash
composer require dennenboom/pinert
```

### Publish Configuration

After installing, publish the configuration file:

```bash
php artisan vendor:publish --provider="Dennenboom\Pinert\Providers\PinertServiceProvider" --tag="config"
```

This will create a `config/pinert.php` configuration file.

## Configuration

### Basic Configuration

Add these variables to your `.env` file:

```
SLACK_ERROR_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
SLACK_ERROR_CHANNEL=
SLACK_ERROR_USERNAME="Error Reporter"
SLACK_ERROR_ICON=:rotating_light:
```

### TaskBoard

Pinert can also send errors to TaskBoard. Each new error becomes a bug in the project's backlog, and repeats of an error whose bug is still open are counted on that bug instead of creating new ones. Once the bug is done, a new occurrence opens a fresh bug.

In TaskBoard, go to **API Tokens**, create a token with **For Pinert error alerts** checked and pick the project, then add the generated values to your `.env`:

```
PINERT_TASKBOARD_URL=https://your-taskboard.test/api/alerts
PINERT_TASKBOARD_KEY=your-project-key
PINERT_TASKBOARD_STACK_TRACE_LINES=20
```

### Health Monitor

Pinert can also report errors to Health Monitor, which counts unique errors and how often they occur, and shows them on the project's dashboard card. Generate a token on the project's **Errors** tab, then add these values to your `.env`:

```
PINERT_MONITOR_URL=https://your-monitor.test/api/error-reports
PINERT_MONITOR_TOKEN=your-project-token
```

No request data or stack trace is sent. Occurrences held back by the rate limit are sent along with the next report of that error.

Slack, TaskBoard and Health Monitor can be used together or on their own. When TaskBoard and Slack are both set up, the Slack message links to the ticket.

### Advanced Configuration

You can further customize the behavior by setting these environment variables:

```
SLACK_ERROR_MAX_STACK_TRACE_LINES=5
SLACK_ERROR_MIN_LEVEL=error
SLACK_ERROR_ENVIRONMENTS=production,staging
SLACK_ERROR_RATE_LIMIT=60
```

## Usage

### Automatic Error Reporting

Once installed and configured, Pinert will automatically send notifications to Slack, TaskBoard and/or Health Monitor whenever a reportable exception occurs in your application.

### Manual Error Reporting

You can also report exceptions manually:

```php
use Dennenboom\Pinert\Facades\Pinert;

try {
    // Your code that might throw an exception
} catch (\Throwable $e) {
    // Manually report to Slack, TaskBoard and/or Health Monitor
    Pinert::report($e, request()->all());
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.