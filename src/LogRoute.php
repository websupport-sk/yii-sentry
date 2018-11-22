<?php

namespace Websupport\YiiSentry;

use CLogRoute;
use Yii;

class LogRoute extends CLogRoute
{
    /**
     * Component ID of the sentry client that should be used to send the logs
     * @var string
     */
    public $sentryId = 'sentry';

    /**
     * Local store for last event's ID
     * @var string
     */
    public $eventId;

    /**
     * Sentry client
     * @var Client
     */
    private $client;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->client = Yii::app()->getComponent($this->sentryId);
    }

    /**
     * Send log messages to Sentry.
     *
     * @inheritdoc
     */
    protected function processLogs($logs)
    {
        foreach ($logs as $log) {
            /**
             * @var string $message
             * @var string $level
             * @var string $category
             * @var float $timestamp
             */
            list($message, $level, $category, $timestamp) = $log;

            if (stristr($message, 'Stack trace:') !== false) {
                continue;
            }

            $this->eventId = $this->client->captureMessage(
                $message,
                array(),
                array(
                    'level' => $level,
                    'timestamp' => $timestamp,
                    'extra' => array(
                        'category' => $category,
                    ),
                )
            );
        }
    }
}
