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
# Slack Error Notifications
SLACK_ERROR_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
SLACK_ERROR_CHANNEL=
SLACK_ERROR_USERNAME="Error Reporter"
SLACK_ERROR_ICON=:rotating_light:
```

### Advanced Configuration

You can further customize the behavior by setting these environment variables:

```
# Slack Error Notifications - Advanced Settings
SLACK_ERROR_MAX_STACK_TRACE_LINES=5
SLACK_ERROR_MIN_LEVEL=error
SLACK_ERROR_ENVIRONMENTS=production,staging
SLACK_ERROR_RATE_LIMIT=60
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.