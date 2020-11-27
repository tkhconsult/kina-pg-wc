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
     * Provided by KinaBank
     * @var null
     */
    static public $signatureFirst;

    /**
     * Provided by KinaBank
     * @var null
     */
    static public $signaturePrefix;

    /**
     * Provided by KinaBank
     * @var string
     */
    static public $signaturePadding;

    /**
     * The path to the public key - not used
     * @var string
     */
    static public $publicKeyPath;

    /**
     * The path to the test key
     * @var string
     */
    static public $testKeyPath;

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
    public function __construct(array $requestParams, $gatewayUrl, $debugMode = false, $sslVerify = true)
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
        #Set debug mode
        $this->_debugMode = $debugMode;
        #Set SSL verify mode
        $this->_sslVerify = $sslVerify;

        #Make sure to set these static params prior to calling the request
        if (is_null(self::$signatureFirst)) {
            throw new Exception('Could not instantiate the bank request - missing parameter signatureFirst');
        }
        if (is_null(self::$signaturePrefix)) {
            throw new Exception('Could not instantiate the bank request - missing parameter signaturePrefix');
        }
        if (is_null(self::$signaturePadding)) {
            throw new Exception('Could not instantiate the bank request - missing parameter signaturePadding');
        }
        if (is_null(self::$testKeyPath)) {
            throw new Exception('Could not instantiate the bank request - missing parameter testKeyPath');
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
     * @param string $order
     * @param string $nonce
     * @param string $timestamp
     * @param string $trType
     * @param float  $amount
     *
     * @return string
     * @throws Exception
     */
    protected function _createSignature($order, $nonce, $timestamp, $trType, $amount)
    {
        $mac = '';
        if (empty($order) || empty($nonce) || empty($timestamp) || is_null($trType) || empty($amount)) {
            throw new Exception('Failed to generate transaction signature: Invalid request params');
        }
        if (!file_exists(self::$testKeyPath) || !$rsaKey = file_get_contents(self::$testKeyPath)) {
            throw new Exception('Failed to generate transaction signature: TEST key not accessible');
        }
        $data = [
            'ORDER' => KinaBankGateway::normalizeOrderId($order),
            'NONCE' => $nonce,
            'TIMESTAMP' => $timestamp,
            'TRTYPE' => $trType,
            'AMOUNT' => KinaBankGateway::normalizeAmount($amount),
        ];

        return true;
    }
}