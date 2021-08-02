<?php

namespace TkhConsult\KinaBankGateway\KinaBank\Authorization;

use TkhConsult\KinaBankGateway\KinaBank\Response;

/**
 * Class AuthorizationResponse
 *
 * @package TkhConsult\KinaBankGateway\KinaBank\Authorization
 */
class AuthorizationResponse extends Response
{
    const TRX_TYPE = 1;

    /**
     * @return bool
     * @throws Exception
     */
    protected function _validateSignature()
    {
        $mac = '';
        $fields = [
            self::ACTION,
            self::RC,
            self::APPROVAL,
            self::CURRENCY,
            self::AMOUNT,
            self::TERMINAL,
            self::TRTYPE,
            self::ORDER,
            self::RRN,
            self::TIMESTAMP,
            self::INT_REF,
            self::NONCE,
        ];

        foreach ($fields as $fieldName) {
            $filed = $this->_responseFields[$fieldName];
            $mac .= strlen($filed) . $filed;
            if($fieldName == self::RRN) {
                $filed = static::$merchant;
                $mac .= strlen($filed) . $filed;
            }
        }
        $pSign        = $this->_responseFields[self::P_SIGN];
        $key = file_get_contents(static::$secretKeyPath);
        $key = preg_replace('/[^A-Za-z0-9]+/', '', $key);
        $encryptedBin = hash_hmac('sha256', $mac, pack('H*', $key));
        $encryptedBin = strtoupper($encryptedBin);

        return $pSign == $encryptedBin;
    }
}