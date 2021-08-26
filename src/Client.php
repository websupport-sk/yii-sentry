<?php

namespace Websupport\YiiSentry;

use CApplicationComponent;
use CClientScript;
use CEvent;
use CJavaScript;
use CMap;
use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\State\HubAdapter;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Throwable;
use Yii;

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
     * user context for error reporting
     * @var array
     */
    private $userContext = [];

    /** @var Transaction|null  */
    private $rootTransaction = null;
    /** @var Span|null  */
    private $appSpan = null;

    /**
     * Initializes the SentryClient component.
     * @return void
     * @throws \CException
     */
    public function init()
    {
        parent::init();

        if ($this->isPhpErrorReportingEnabled()) {
            $this->installPhpErrorReporting();
        }

        if ($this->isJsErrorReportingEnabled()) {
            $this->installJsErrorReporting();
        }

        if ($this->isTracingEnabled()) {
            $this->attachEventHandlers();
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
        if ($this->getSentry()->getClient() === null) {
            return null;
        }

        return $this->getSentry()->getClient()->captureMessage($message, $level, $scope);
    }

    public function addBreadcrumb(
        string $level,
        string $type,
        string $category,
        ?string $message = null,
        array $metadata = [],
        ?float $timestamp = null
    ): bool {
        return $this->getSentry()->addBreadcrumb(
            new Breadcrumb($level, $type, $category, $message, $metadata, $timestamp)
        );
    }

    /**
     * Logs an exception.
     *
     * @param Throwable $exception The exception object
     * @param Scope|null $scope     An optional scope keeping the state
     *
     * @return string|null
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null): ?string
    {
        if ($this->rootTransaction !== null) {
            $this->rootTransaction->setHttpStatus(500);
        }

        if ($this->getSentry()->getClient() === null) {
            return null;
        }

        return $this->getSentry()->getClient()->captureException($exception, $scope);
    }

    /**
     * Return the last captured event's ID or null if none available.
     *
     * @return string|null
     */
    public function getLastEventId()
    {
        return $this->getSentry()->getLastEventId();
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
     * @see https://docs.sentry.io/enriching-error-data/context/?platform=php#capturing-the-user
     */
    public function setUserContext($context)
    {
        $this->userContext = CMap::mergeArray($this->userContext, $context);

        // Set user context for PHP client
        if ($this->isPhpErrorReportingEnabled()) {
            HubAdapter::getInstance()->configureScope(function (Scope $scope): void {
                $user = array_merge($this->userContext, $this->getInitialPhpUserContext());
                $scope->setUser($user);
            });
        }

        // Set user context for JS client
        if ($this->isJsErrorReportingEnabled()) {
            $userContext = CJavaScript::encode($this->userContext);
            Yii::app()->clientScript->registerScript(
                'sentry-javascript-user',
                "Raven.setUserContext({$userContext});"
            );
        }
    }

    private function getSentry(): HubInterface
    {
        return HubAdapter::getInstance();
    }

    private function installPhpErrorReporting() : void
    {
        \Sentry\init(array_merge(['dsn' => $this->dsn], $this->options));

        $this->getSentry()->configureScope(function (Scope $scope): void {
            $scope->setUser($this->getInitialPhpUserContext());
        });
    }

    private function getInitialPhpUserContext(): array
    {
        if (!function_exists('session_id') || !session_id()) {
            return [];
        }
        $user = [];
        if (!empty($_SESSION)) {
            $user = $_SESSION;
        }
        $user['session_id'] = session_id();
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $user['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        return $user;
    }

    /**
     * @throws \CException
     */
    private function installJsErrorReporting() : void
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

    /**
     * @throws \CException
     */
    protected function attachEventHandlers(): void
    {
        Yii::app()->attachEventHandler('onBeginRequest', [$this, 'handleBeginRequestEvent']);
        Yii::app()->attachEventHandler('onEndRequest', [$this, 'handleEndRequestEvent']);
        Yii::app()->attachEventHandler('onException', [$this, 'handleExceptionEvent']);
        Yii::app()->attachEventHandler('onError', [$this, 'handleExceptionEvent']);
    }

    private function isPhpErrorReportingEnabled(): bool
    {
        return !empty($this->dsn);
    }

    private function isJsErrorReportingEnabled(): bool
    {
        return !empty($this->jsDsn);
    }

    private function isTracingEnabled(): bool
    {
        return isset($this->options['traces_sampler']);
    }

    //region Events

    /**
     * @param \CEvent $event
     */
    public function handleBeginRequestEvent(\CEvent $event): void
    {
        $name = $this->operationNameFromBeginRequestEvent($event);
        $this->rootTransaction = $this->startRootTransaction($name, []);
        $this->getSentry()->setSpan($this->rootTransaction);

        $appContextStart = new SpanContext();
        $appContextStart->setOp('app.handle');
        $appContextStart->setDescription($name);
        $appContextStart->setStartTimestamp(microtime(true));
        $appContextStart->setData(
            $this->spanDataFromBeginRequestEvent($event)
        );
        $this->appSpan = $this->rootTransaction->startChild($appContextStart);
    }

    /**
     * @param \CEvent $event
     */
    public function handleEndRequestEvent(\CEvent $event): void
    {
        $this->grabPushLogsFromLoggerToBreadcrumbs();

        if ($this->appSpan !== null) {
            $this->appSpan->finish();
        }

        if ($this->rootTransaction !== null) {
            if ($this->rootTransaction->getStatus() === null) {
                $this->rootTransaction->setHttpStatus(200);
            }
            $this->rootTransaction->finish();
        }
    }

    public function handleExceptionEvent(\CEvent $event): void
    {
        $this->grabPushLogsFromLoggerToBreadcrumbs();

        if ($this->rootTransaction !== null) {
            $this->rootTransaction->setHttpStatus(500);
        }
    }

    /**
     * @param string[]|int[]|bool[] $data
     */
    private function startRootTransaction(string $description, array $data = []): Transaction
    {
        $context = new TransactionContext();
        $context->setOp('yii-app');
        $context->setName($description);
        $context->setDescription($description);
        $context->setData($data);

        return $this->getSentry()->startTransaction($context);
    }

    /**
     * @return string[]
     */
    private function spanDataFromBeginRequestEvent(CEvent $event): array
    {
        $application = $event->sender;
        assert($application instanceof \CApplication);

        $data = [
            'component' => get_class($application),
        ];

        // Add HTTP related tags
        if ($application instanceof \CWebApplication) {
            try {
                $requestUri = $application->request->getRequestUri();
            } catch (\CException $exception) {
                $requestUri = '';
            }

            $data['http.method'] = $application->request->getRequestType();
            $data['http.url'] = sprintf(
                '%s%s',
                $application->request->getHostInfo(),
                $requestUri
            );
            $data['http.host'] = parse_url($application->request->getUrl(), PHP_URL_HOST);
            $data['http.uri'] = $requestUri;
        }

        return $data;
    }

    private function operationNameFromBeginRequestEvent(CEvent $event): string
    {
        $application = $event->sender;
        assert($application instanceof \CApplication);

        if ($application instanceof \CConsoleApplication) {
            // phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong
            // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
            return implode(' ', $_SERVER['argv']);
        }

        if ($application instanceof \CWebApplication) {
            return strtolower($application->getUrlManager()->parseUrl($application->request));
        }

        return 'yii-app';
    }

    //endregion
    private function grabPushLogsFromLoggerToBreadcrumbs(): void
    {
        $logs = Yii::getLogger()->getLogs();

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

            if (!in_array($level, [
                Breadcrumb::LEVEL_DEBUG,
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::LEVEL_WARNING,
                Breadcrumb::LEVEL_ERROR,
                Breadcrumb::LEVEL_FATAL,
            ], true)) {
                $level = Breadcrumb::LEVEL_DEBUG;
            }

            $this->addBreadcrumb($level, Breadcrumb::TYPE_DEFAULT, $category, $message);
        }
    }
}
