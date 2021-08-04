<?php

namespace Websupport\YiiSentry;

use CLogRoute;
use Sentry\Severity;
use Sentry\State\Scope;
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

        // hack to disable yii autoloader
        // sentry is calling is_callable() method which will ends in YiiBase::autoload
        spl_autoload_unregister(array('YiiBase', 'autoload'));

	foreach ($logs as $log) {
            /**
             * @var string $message
             * @var string $level
             * @var string $category
             */
            list($message, $level, $category) = $log;

            // remove stack trace from message
            if (($pos = strpos($message, 'Stack trace:')) !== false) {
                $message = substr($message, 0, $pos);
            }

            $scope = new Scope();
            $scope->setExtra('category', $category);

            $this->eventId = $this->client->captureMessage(
                $message,
                new Severity($level),
                $scope
            );
        }
    }
}
