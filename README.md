# Pinert

Pinert or Pine Alert is a Laravel package that sends detailed error notifications to Slack when exceptions occur in your Laravel applications. Perfect for multiple project environments where you need to monitor errors across different applications using the same Slack webhook.

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

Slack and TaskBoard can be used together or on their own.

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

Once installed and configured, Pinert will automatically send notifications to Slack and/or TaskBoard whenever a reportable exception occurs in your application.

### Manual Error Reporting

You can also report exceptions manually:

```php
use Dennenboom\Pinert\Facades\Pinert;

try {
    // Your code that might throw an exception
} catch (\Throwable $e) {
    // Manually report to Slack and/or TaskBoard
    Pinert::report($e, request()->all());
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.