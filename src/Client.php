<?php

namespace Websupport\YiiSentry;

use Yii;
use CMap;
use CApplicationComponent;
use CClientScript;
use CJavaScript;
use Raven_Client;
use Raven_ErrorHandler;

/**
 * Class Client
 * @package Websupport\YiiSentry
 * @property-read string|null $lastEventId
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
     * @see https://docs.sentry.io/clients/javascript/config/
     */
    public $options = array();

    /**
     * Sentry project URL
     * @var string
     */
    public $projectUrl = '';

    /**
     * If logging should be performed. This can be useful if running under
     * development/staging
     * @var boolean
     */
    public $enabled = true;

    /**
     * Url of Sentry reporting JS file
     * @var string
     */
    public $jsScriptUrl = "https://cdn.ravenjs.com/3.26.2/raven.min.js";

    /**
     * Sentry DSN value
     * @var string
     */
    public $jsDsn;

    /**
     * Stored sentry client connection
     * @var \Raven_Client
     */
    private $sentry;

    /**
     * Sentry error handler
     * @var Raven_ErrorHandler
     */
    private $errorHandler;

    /**
     * user context for JS error reporting
     * @var array
     */
    private $jsUserContext = array();

    /**
     * Initializes the SentryClient component.
     * @return void
     * @throws \CException
     */
    public function init()
    {
        parent::init();

        if (!empty($this->dsn)) {
            $this->installPhpErrorReporting();
        }

        if (!empty($this->jsDsn)) {
            $this->installJsErrorReporting();
        }
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
        return $this->sentry->captureMessage($message, $params, $data, $stack, $vars);
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
     * Return the last captured event's URL
     * @return string
     */
    public function getLastEventUrl()
    {
        return sprintf('%s/?query=%s', rtrim($this->projectUrl, '/'), $this->sentry->getLastEventID());
    }

    /**
     * User context for tracking current user
     * @param array $context
     * @see https://docs.sentry.io/clients/javascript/usage/#tracking-users
     */
    public function setJsUserContext($context)
    {
        $this->jsUserContext = CMap::mergeArray($this->jsUserContext, $context);
        $userContext = CJavaScript::encode($this->jsUserContext);
        Yii::app()->clientScript->registerScript(
            'sentry-javascript-user',
            "Raven.setUserContext({$userContext});"
        );
    }

    private function installPhpErrorReporting()
    {
        $this->sentry = new Raven_Client($this->dsn, $this->options);
        $this->errorHandler = new Raven_ErrorHandler($this->sentry);
        $this->errorHandler->registerErrorHandler(true, ~error_reporting());
        $this->errorHandler->registerExceptionHandler(true);
        $this->errorHandler->registerShutdownFunction();
    }

    /**
     * @throws \CException
     */
    private function installJsErrorReporting()
    {
        /** @var \CClientScript $clientScript */
        $clientScript = Yii::app()->clientScript;

        $clientScript->registerScriptFile(
            $this->scriptUrl,
            CClientScript::POS_HEAD,
            array('crossorigin' => 'anonymous')
        );

        $options = $this->options;
        if (!isset($options['dataCallback'])) {
            $options['dataCallback'] = 'function(data) {
                data.extra.source_scripts = [];
                data.extra.referenced_scripts = [];
                var scripts = document.getElementsByTagName("script");
                for (var i=0;i<scripts.length;i++) {
                    if (scripts[i].src)
                        data.extra.referenced_scripts.push(scripts[i].src);
                    else
                        data.extra.source_scripts.push(scripts[i].innerHTML);
                }
            }';
        }
        $options = CJavaScript::encode($options);

        $clientScript->registerScript(
            'sentry-javascript-init',
            "Raven.config('{$this->jsDsn}', {$options}).install();",
            CClientScript::POS_HEAD
        );
    }
}
