<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * PHP Version 5.3
 *
 * @category Logging
 * @package  YiiSentry
 * @author   Tomáš Tatarko <tomas@tatarko.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/tatarko/yii-sentry Official repository
 */

namespace Tatarko\YiiSentry;

use CLogRoute;
use Yii;
use CLogger;
use Raven_ErrorHandler;


/**
 * Sentry Log Route
 *
 * Layer for Yii framework for communication with Sentry logging API
 *
 * @category Logging
 * @package  YiiSentry
 * @author   Tomáš Tatarko <tomas@tatarko.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/tatarko/yii-sentry Official repository
 */
class LogRoute extends CLogRoute
{
    /**
     * Component ID of the sentry client that should be used to
     * send the logs
     * @var string  
     */
    public $sentryComponent = 'sentry';

    /**
     * The log category for raven logging related to this extension.
     * @var string
     */
    public $ravenLogCategory = 'raven';

    /**
     * Local store for last event's ID
     * @var string
     */
    public $eventId;

    /**
     * Sentry client
     * @var Client
     */
    private $_client;

    /**
     * Sentry error handler
     * @var \Raven_ErrorHandler
     */
    private $_errorHandler;

    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     * @return void
     */
    public function init() 
    {
        parent::init();

        if ((empty($this->levels) || stristr($this->levels, 'error') !== false)
            && $this->getClient() !== false
        ) {
            Yii::app()->attachEventHandler(
                'onException', array(
                $this,
                'handleException'
                )
            );
            Yii::app()->attachEventHandler(
                'onError', array(
                $this,
                'handleError'
                )
            );

            $this->_errorHandler = new Raven_ErrorHandler(
                $this->getClient()->getRavenClient()
            );
            $this->_errorHandler->registerShutdownFunction();
        }
    }

    /**
     * Send log messages to Sentry.
     * 
     * @param array $logs List of log messages.
     * 
     * @return boolean
     */
    protected function processLogs($logs) 
    {
        if (count($logs) == 0 || !$sentry = $this->getClient()) {
            return false;
        }

        foreach ($logs as $log) {
            if (stristr($log[0], 'Stack trace:') !== false
                || $log[2] == $this->ravenLogCategory
            ) {
                continue;
            }

            $format = explode("\n", $log[0]);
            $title = strip_tags($format[0]);

            $sentry->captureMessage(
                $title,
                array(
                    'extra'=>array(
                        'category'=>$log[2],
                    ),
                ),
                array(
                    'level'=>$log[1],
                    'timestamp'=>$log[3],
                )
            );
        }

        return true;
    }

    /**
     * Send exceptions to sentry server
     * @param \CExceptionEvent $event represents the parameter for the 
     * onException event.
     * @return boolean
     */
    public function handleException($event) 
    {
        if (!$sentry = $this->getClient()) {
            return false;
        }

        $this->_errorHandler->handleException($event->exception);
        $this->eventId = $event->exception->event_id;
        if ($lastError = $sentry->getLastError()) {
            Yii::log($lastError, CLogger::LEVEL_ERROR, $this->ravenLogCategory);
        }
        return true;
    }

    /**
     * Send errors to sentry server
     * @param CErrorEvent $event represents the parameter for the onError event.
     * @return boolean
     */
    public function handleError($event) 
    {
        if (!$sentry = $this->getClient()) {
            return false;
        }

        $this->_errorHandler->handleError(
            $event->code,
            $event->message,
            $event->file,
            $event->line,
            $event->params
        );

        if ($lastError = $sentry->getLastError()) {
            Yii::log($lastError, CLogger::LEVEL_ERROR, $this->ravenLogCategory);
        }

        return true;
    }

    /**
     * Returns the RSentryClient which should send the data.
     * It ensure RSentryClient application component exists and is initialised.
     * 
     * @return Client The configured Client instance
     */
    protected function getClient() 
    {
        if (!isset($this->_client)) {
            if (!Yii::app()->hasComponent($this->sentryComponent)) {
                Yii::log(
                    "'$this->sentryComponent' does not exist", 
                    CLogger::LEVEL_TRACE, 'application.RSentryLogRoute'
                );
                $this->_client = false;
            } else {
                $sentry = Yii::app()->{$this->sentryComponent};

                if (!$sentry || !$sentry->getIsInitialized()) {
                    Yii::log(
                        "'$this->sentryComponent' not initialised", 
                        CLogger::LEVEL_TRACE, 'application.RSentryLogRoute'
                    );
                    $this->_client = false;
                } else {
                    $this->_client = $sentry;
                }
            }
        }

        return $this->_client;
    }
}
