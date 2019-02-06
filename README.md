# Yii Sentry extension
Layer for Yii framework for communication with Sentry logging API

## Installation

**Yii Sentry** is composer library so you can install the latest version with:

```shell
composer require websupport/yii-sentry
```

## Configuration

Add following to your application's config:

### PHP error reporting


```php
'components' => array(
    'log' => array(
        'class' => 'CLogRouter',
        'routes' => array(
            // your other log routers
            array(
                'class' => 'Websupport\\YiiSentry\\LogRoute',
                'levels' => E_ALL,
                'enabled' => !YII_DEBUG,
            ),
        ),
    ),
    'sentry' => array(
        'class' => 'Websupport\\YiiSentry\\Client',
        'dsn' => '', // Your's DSN from Sentry
    ),
)
```

### JS error reporting

```php
'preload' => array('sentry'),
'components' => array(
    'sentry' => array(
        'class' => 'Websupport\\YiiSentry\\Client',
        'jsDsn' => '', // Your's DSN from Sentry
    ),
)
```

#### Sending user context to JS
`Websupport\\YiiSentry\\Client` component has public method: `setJsUserContext($context)` which will send `$context` to Raven JS instance.
You can call this method multiple times from any part of the system. Recommended way however is to use it in `CWebUser` class right after init.