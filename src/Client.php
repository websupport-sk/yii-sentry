<?php

namespace Websupport\YiiSentry;

use CApplicationComponent;
use Raven_Client;
use Raven_ErrorHandler;
use \CApplication;
/**
 * Class Client
 * @package Websupport\YiiSentry
 * @property-read string|null eventId
 */
class Client extends CApplicationComponent
{
    /**
     * Sentry DSN value
     * @var string
     */
    public $dsn;

    /**
     * Raven_Client options
     * @var array
     * @see https://docs.sentry.io/clients/php/config/
     */
    public $options = array();

    /**
     * If logging should be performed. This can be useful if running under
     * development/staging
     * @var boolean 
     */
    public $enabled = true;

    /**
     * Stored sentry client connection
     * @var \Raven_Client
     */
    private $sentry = null;

    /**
     * Sentry error handler
     * @var Raven_ErrorHandler
     */
    private $errorHandler;

//    /**
//     * @var callable
//     */
//    private $previousHandler;

    /**
     * Initializes the RSentryClient component.
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->sentry = new Raven_Client($this->dsn, $this->options);
        $this->errorHandler = new Raven_ErrorHandler($this->sentry);
        $this->errorHandler->registerErrorHandler(true, ~error_reporting());
        $this->errorHandler->registerExceptionHandler(true);
        $this->errorHandler->registerShutdownFunction();
    }

    /**
     * Log a message to sentry
     *
     * @param string     $message The message (primary description) for the event.
     * @param array      $params  params to use when formatting the message.
     * @param array      $data    Additional attributes to pass with this event (see Sentry docs).
     * @param bool|array $stack
     * @param mixed      $vars
     * @return string|null
     */
    public function captureMessage($message, $params = array(), $data = array(), $stack = false, $vars = null)
    {
        return $this->sentry->captureMessage($message, $params,  $data, $stack, $vars);
    }

    /**
     * Log an exception to sentry
     *
     * @param \Throwable|\Exception $exception The Throwable/Exception object.
     * @param array                 $data      Additional attributes to pass with this event (see Sentry docs).
     * @param mixed                 $logger
     * @param mixed                 $vars
     * @return string|null
     */
    public function captureException($exception, $data = null, $logger = null, $vars = null)
    {
        return $this->sentry->captureException($exception, $data, $logger, $vars);
    }

    /**
     * Return the last captured event's ID or null if none available.
     *
     * @return string|null
     */
    public function getLastEventId()
    {
        return $this->sentry->getLastEventID();
    }

    /**
     * @param $type
     * @param $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return bool|mixed
     */
//    public function handleError($type, $message, $file = '', $line = 0, $context = array())
//    {
//        if (error_reporting() & $type) {
//            return call_user_func_array($this->previousHandler, array($type, $message, $file, $line, $context));
//        }
//
//        return false;
//    }
}
