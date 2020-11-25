<?php

namespace TkhConsult\KinaBankGateway\KinaBank;

/**
 * Interface RequestInterface
 *
 * @package TkhConsult\KinaBankGateway\KinaBank
 */
interface RequestInterface
{
    /**
     * RequestInterface constructor.
     *
     * @param array  $requestParams
     * @param string $gatewayUrl
     * @param bool   $debugMode
     * @param bool   $sslVerify
     */
    public function __construct(array $requestParams, $gatewayUrl, $debugMode = false, $sslVerify = true);

    /**
     * @param bool $debugMode
     *
     * @return $this
     */
    public function setDebugMode($debugMode);

    /**
     * @param boolean $sslVerify
     *
     * @return $this
     */
    public function setSslVerify($sslVerify);

    /**
     * @param string $gatewayUrl
     *
     * @return $this
     */
    public function setGatewayUrl($gatewayUrl);

    /**
     * Performs the actual request
     * @return mixed
     */
    public function request();
}