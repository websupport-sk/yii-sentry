<?php
namespace Websupport\YiiSentry\Js;

use Yii;
use CClientScript;
use CApplicationComponent;

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

    /**
     * User context for tracking current user
     * @var array
     * @see https://docs.sentry.io/clients/javascript/usage/#tracking-users
     */
    public $userContext = array();

    public function init()
    {
        parent::init();

        $this->initializeDefaults();

        $this->installTracking();
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
        $userContext = \CJavaScript::encode($this->userContext);

        $trackingScript = <<<RAVEN
    Raven.config('{$this->dsn}', {$options}).install();
    Raven.setUserContext({$userContext});
RAVEN;
        $clientScript->registerScript('sentry-javascript', $trackingScript);
    }
}