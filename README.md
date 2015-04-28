# Yii Sentry extension
Layer for Yii framework for communication with Sentry logging API

[![Latest Stable Version](https://poser.pugx.org/tatarko/yii-sentry/v/stable.png)](https://packagist.org/packages/tatarko/yii-sentry)
[![Code Climate](https://codeclimate.com/github/tatarko/yii-slack/badges/gpa.png)](https://codeclimate.com/github/tatarko/yii-sentry)

## Installation

**Yii Sentry** is composer library so you can install the latest version with:

```shell
php composer.phar require tatarko/yii-sentry
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
				'levels' => 'error,warning',
				// 'enabled' => !YII_DEBUG,
			),
		),
	),
	'sentry' => array(
		'class' => 'Tatarko\\YiiSentry\\Client',
		'dsn' => '', // Your's DSN from Sentry
	),
)
```
