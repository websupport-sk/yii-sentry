# Yii Sentry extension
Layer for Yii framework for communication with Sentry logging API

## Installation

**Yii Sentry** is composer library so you can install the latest version with:

```shell
composer require websupport/yii-sentry
```

## Configuration

To your application's config add following:

```php
'components' => array(
    'log' => array(
        'class' => 'CLogRouter',
        'routes' => array(
            // your other log routers
            array(
                'class' => 'Tatarko\\YiiSentry\\LogRoute',
                'levels' => E_ALL,
                'enabled' => !YII_DEBUG,
            ),
        ),
    ),
    'sentry' => array(
        'class' => 'Tatarko\\YiiSentry\\Client',
        'dsn' => '', // Your's DSN from Sentry
    ),
    'preload' => array('sentry'),
)
```
