<?php
namespace Websupport\YiiSentry\Js;

use Yii;
use CClientScript;
use CApplicationComponent;
use CMap;

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
     * @see https://docs.sentry.io/clients/javascript/config/
     */
    public $options = array();

    /**
     * Url of Sentry reporting JS file
     * @var string
     */
    public $scriptUrl = "https://cdn.ravenjs.com/3.26.2/raven.min.js";

    private $userContext = array();

    public function init()
    {
        parent::init();

        $this->initializeDefaults();

        $this->installTracking();
    }
    /**
     * User context for tracking current user
     * @param array $context
     * @see https://docs.sentry.io/clients/javascript/usage/#tracking-users
     */
    public function setUserContext($context)
    {
        $this->userContext = CMap::mergeArray($this->userContext, $context);
        $userContext = \CJavaScript::encode($this->userContext);
        Yii::app()->clientScript->registerScript(
            'sentry-javascript-user',
            "Raven.setUserContext({$userContext});"
        );
    }

    private function initializeDefaults()
    {
        if (!isset($this->options['dataCallback'])) {
            $this->options['dataCallback'] = 'function(data) {
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
    }

    private function installTracking()
    {
        /** @var \CClientScript $clientScript */
        $clientScript = Yii::app()->clientScript;

        $clientScript->registerScriptFile(
            $this->scriptUrl,
            CClientScript::POS_HEAD,
            array('crossorigin' => 'anonymous')
        );

        $options = \CJavaScript::encode($this->options);

        $clientScript->registerScript(
            'sentry-javascript-init',
            "Raven.config('{$this->dsn}', {$options}).install();",
            CClientScript::POS_HEAD
        );
    }
}