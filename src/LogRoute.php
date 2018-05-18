<?php

namespace Websupport\YiiSentry;

use CLogRoute;
use Yii;

class LogRoute extends CLogRoute
{
    /**
     * Component ID of the sentry client that should be used to
     * send the logs
     * @var string  
     */
    public $sentryId = 'sentry';

    /**
     * Local store for last event's ID
     * @var string
     */
    public $eventId;

    /**
     * @var string
     */
    public $levels;

    /**
     * Sentry client
     * @var Client
     */
    private $client;

    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     * @return void
     */
    public function init() 
    {
        parent::init();
        $this->client = Yii::app()->getComponent($this->sentryId);
    }

    /**
     * Send log messages to Sentry.
     * 
     * @param array $logs List of log messages.
     */
    protected function processLogs($logs) 
    {
        /*
         *   [0] => message (string)
         *   [1] => level (string)
         *   [2] => category (string)
         *   [3] => timestamp (float, obtained by microtime(true));
         */

        foreach ($logs as $log) {
            list($message, $level, $category, $timestamp) = $log;

            if (stristr($message, 'Stack trace:') !== false) {
                continue;
            }

            $this->eventId = $this->client->captureMessage(
                $message,
                array(),
                array(
                    'level' => $level,
                    'timestamp'=> $timestamp,
                    'extra'=>array(
                        'category' => $category,
                    ),
                )
            );
        }
    }
}
