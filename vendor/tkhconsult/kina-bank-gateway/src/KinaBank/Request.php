<?php

namespace TkhConsult\KinaBankGateway\KinaBank;

use TkhConsult\KinaBankGateway\KinaBankGateway;

/**
 * Class Request
 *
 * @package TkhConsult\KinaBankGateway\KinaBank
 */
abstract class Request implements RequestInterface
{
    /**
     * The path to the secret key - not used
     * @var string
     */
    static public $secretKeyPath;

    /**
     * @var bool
     */
    protected $_debugMode = false;

    /**
     * @var bool
     */
    protected $_sslVerify = true;

    /**
     * @var string
     */
    protected $_gatewayUrl;

    /**
     * @var array
     */
    protected $_requestFields = [];
    protected $_acceptUrl = '';
    protected $_submitButtonLabel = '';

    /**
     * Construct
     *
     * @param array  $requestParams
     * @param string $gatewayUrl
     * @param bool   $debugMode
     * @param bool   $sslVerify
     *
     * @throws Exception
     */
    public function __construct(array $requestParams, $gatewayUrl, $acceptUrl = '', $submitButtonLabel = '', $debugMode = false, $sslVerify = true)
    {
        #Push the request field values
        foreach ($requestParams as $name => $value) {
            if (!array_key_exists($name, $this->_requestFields)) {
                continue;
            }
            $this->_requestFields[$name] = $value;
        }

        #Set gateway URL
        $this->_gatewayUrl = $gatewayUrl;
        $this->_acceptUrl = $acceptUrl;
        $this->_submitButtonLabel = $submitButtonLabel;
        #Set debug mode
        $this->_debugMode = $debugMode;
        #Set SSL verify mode
        $this->_sslVerify = $sslVerify;

        #Make sure to set these static params prior to calling the request
        if (is_null(self::$secretKeyPath)) {
            throw new Exception('Could not instantiate the bank request - missing parameter secretKeyPath');
        }
        $this->init();
    }

    /**
     * Initialization
     */
    protected function init()
    {
        $this->validateRequestParams();

        return $this;
    }

    /**
     * @return mixed
     */
    abstract public function validateRequestParams();

    /**
     * @param boolean $debugMode
     *
     * @return $this
     */
    public function setDebugMode($debugMode)
    {
        $this->_debugMode = (boolean)$debugMode;

        return $this;
    }

    /**
     * @param boolean $sslVerify
     *
     * @return $this
     */
    public function setSslVerify($sslVerify)
    {
        $this->_sslVerify = (boolean)$sslVerify;

        return $this;
    }

    /**
     * @param string $gatewayUrl
     *
     * @return $this
     */
    public function setGatewayUrl($gatewayUrl)
    {
        $this->_gatewayUrl = $gatewayUrl;

        return $this;
    }

    /**
     * Performs the actual request
     * @return mixed
     */
    abstract public function request();

    /**
     * Generates the P_SIGN
     *
     * @param array $data
     *
     * @return string
     * @throws Exception
     */
    protected function _createSignature($data)
    {
        $mac = '';
        foreach ($data as $Id => $filed) {
            $mac .= strlen($filed).$filed;
        }
        $key = file_get_contents(static::$secretKeyPath);
        $key = preg_replace('/[^A-Za-z0-9]+/', '', $key);
        return hash_hmac('sha256', $mac, pack('H*', $key));
    }
}
