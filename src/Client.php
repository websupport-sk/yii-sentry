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

use CApplicationComponent;
use Raven_Client;


/**
 * Sentry Client application component
 *
 * Layer for Yii framework for communication with Sentry logging API
 *
 * @category      Logging
 * @package       YiiSentry
 * @author        Tomáš Tatarko <tomas@tatarko.sk>
 * @license       http://choosealicense.com/licenses/mit/ MIT
 * @link          https://github.com/tatarko/yii-sentry Official repository
 * @property-read \Raven_Client $ravenClient The Raven_Client instance
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
     * @see https://github.com/getsentry/raven-php#configuration
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
    private $_client = false;
    
    /**
     * Initializes the RSentryClient component.
     * @return void
     */
    public function init()
    {
        if ($this->enabled) {
            parent::init();
            if ($this->_client === false) {
                $this->_client = new Raven_Client($this->dsn, $this->options);
            }
        }
    }
    
    /**
     * Returns true if Yii debug is turned on, false otherwise.
     * @return boolean true if Yii debug is turned on, false otherwise.
     */
    protected function isDebugMode() 
    {
        return defined('YII_DEBUG') && YII_DEBUG === true;
    }
    
    /**
     * Returns the Raven_Client
     * @return \Raven_Client The Raven_Client if this component is initialised, 
     * false otherwise.
     */
    public function getRavenClient() 
    {
        return $this->_client;
    }

    /**
     * Log a message to sentry
     *
     * @param string  $message          Message to log
     * @param array   $params           Parameters to post
     * @param array   $level_or_options Options
     * @param boolean $stack            Stack sending of this message?
     * @param array   $vars             Additional vars
     *
     * @return integer Captured event ID
     */
    public function captureMessage(
        $message,
        $params = array(),
        $level_or_options=array(),
        $stack=false,
        $vars = null
    ) {
        return $this->_client->captureMessage(
            $message,
            $params,
            $level_or_options,
            $stack,
            $vars
        );
    }

    /**
     * Given an identifier, returns a Sentry searchable string.
     * @param integer $ident Unique identifier
     * @return string
     */
    public function getIdent($ident)
    {
        return $this->_client->getIdent($ident);
    }


    /**
     * Returns the request response from sentry if and only if the last message
     * was not sent successfully.
     * 
     * @return mixed Last error
     */
    public function getLastError()
    {
        return $this->_client->getLastError();
    }
}
