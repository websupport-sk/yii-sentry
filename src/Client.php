<?php

namespace Websupport\YiiSentry;

use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Yii;
use CMap;
use CApplicationComponent;
use CClientScript;
use CJavaScript;

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
    public $options = [];

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
     * user context for JS error reporting
     * @var array
     */
    private $jsUserContext = [];

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
     * Logs a message.
     *
     * @param string     $message The message (primary description) for the event
     * @param Severity   $level   The level of the message to be sent
     * @param Scope|null $scope   An optional scope keeping the state
     *
     * @return string|null
     */
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?string
    {
        return Hub::getCurrent()->getClient()->captureMessage($message, $level, $scope);
    }

    /**
     * Logs an exception.
     *
     * @param \Throwable $exception The exception object
     * @param Scope|null $scope     An optional scope keeping the state
     *
     * @return string|null
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null): ?string
    {
        return Hub::getCurrent()->getClient()->captureException($exception, $scope);
    }

    /**
     * Return the last captured event's ID or null if none available.
     *
     * @return string|null
     */
    public function getLastEventId()
    {
        return Hub::getCurrent()->getLastEventId();
    }

    /**
     * Return the last captured event's URL
     * @return string
     */
    public function getLastEventUrl()
    {
        return sprintf('%s/?query=%s', rtrim($this->projectUrl, '/'), $this->getLastEventId());
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
        \Sentry\init(array_merge(['dsn' => $this->dsn], $this->options));

        \Sentry\configureScope(function (Scope $scope): void {
            $user = [];

            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $user['ip_address'] = $_SERVER['REMOTE_ADDR'];
            }

            if (Yii::app()->getComponent('user')) {
                $user['id'] = Yii::app()->user->id;
                $user['username'] = Yii::app()->user->name;
            }

            if (Yii::app()->getComponent('session')) {
                $user['session_data'] = Yii::app()->session->toArray();
            }

            $scope->setUser($user);
        });

    }

    /**
     * @throws \CException
     */
    private function installJsErrorReporting()
    {
        /** @var \CClientScript $clientScript */
        $clientScript = Yii::app()->clientScript;

        $clientScript->registerScriptFile(
            $this->jsScriptUrl,
            CClientScript::POS_HEAD,
            ['crossorigin' => 'anonymous']
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
