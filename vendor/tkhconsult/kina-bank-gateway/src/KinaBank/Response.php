<?php

namespace TkhConsult\KinaBankGateway\KinaBank;

use TkhConsult\KinaBankGateway\KinaBankGateway;

/**
 * Class Response
 */
abstract class Response implements ResponseInterface
{
    /**
     * @var array
     */
    protected $_responseFields = [
        self::TERMINAL => null,
        self::TRTYPE => null,
        self::ORDER => null,
        self::AMOUNT => null,
        self::CURRENCY => null,
        self::ACTION => null,
        self::RC => null,
        self::RC_MSG => null,
        self::TEXT => null,
        self::APPROVAL => null,
        self::RRN => null,
        self::INT_REF => null,
        self::TIMESTAMP => null,
        self::NONCE => null,
        self::P_SIGN => null,
        self::BIN => null,
        self::CARD => null,
        self::AUTH => null,
    ];

    /**
     * @var array
     */
    protected $_errors = [];

    /**
     * Construct
     *
     * @param array $responseData
     *
     * @throws Exception
     */
    public function __construct(array $responseData)
    {
        if (empty($responseData)) {
            throw new Exception('Bank response error: Empty data received');
        }
        #Set the response fields
        foreach ($this->_responseFields as $k => &$v) {
            if (isset($responseData[$k])) {
                $v = $responseData[$k];
            }
        }

        $this->_responseFields[self::ORDER] = KinaBankGateway::normalizeOrderId($this->_responseFields[self::ORDER]);
        $this->_responseFields[self::AMOUNT] = KinaBankGateway::normalizeAmount($this->_responseFields[self::AMOUNT]);
        $this->_responseFields[self::RC_MSG] = self::convertRcMessage($this->_responseFields[self::RC]);

        return $this;
    }

    public static function convertRcMessage($rc) {
        $message = '';
        switch($rc) {
            case '00': $message = 'Transaction Success'; break;
            case '-1': $message = 'A mandatory request field is not filled in'; break;
            case '-2': $message = 'CGI request validation failed'; break;
            case '-3': $message = 'Acquirer host (NS) does not respond or wrong format of e-gateway response template file'; break;
            case '-4': $message = 'No connection to the acquirer host (NS)'; break;
            case '-5': $message = 'The acquirer host (NS) connection failed during transaction processing'; break;
            case '-6': $message = 'e-Gateway configuration error'; break;
            case '-7': $message = 'The acquirer host (NS) response is invalid, e.g. mandatory fields missing'; break;
            case '-8': $message = 'Error in the "Card number" request field'; break;
            case '-9': $message = 'Error in the "Card expiration date" request field'; break;
            case '-10': $message = 'Error in the "Amount" request field'; break;
            case '-11': $message = 'Error in the "Currency" request field'; break;
            case '-12': $message = 'Error in the "Merchant ID" request field'; break;
            case '-13': $message = 'The referrer IP address (usually the merchant\'s IP) is not the one expected'; break;
            case '-14': $message = 'No connection to the iPOS PINpad or agent program is not running on the iPOS computer/workstation'; break;
            case '-15': $message = 'Error in the "RRN" request field'; break;
            case '-16': $message = 'Another transaction is being performed on the terminal'; break;
            case '-17': $message = 'The terminal is denied access to the e-Gateway'; break;
            case '-18': $message = 'Error in the CVC2 or CVC2 Description request fields'; break;
            case '-19': $message = 'Error in the authentication information request or authentication failed.'; break;
            case '-20': $message = 'A permitted time interval (1 hour by default) between the transaction Time Stamp request field and the e-Gateway time is exceeded'; break;
            case '-21': $message = 'The transaction has already been executed'; break;
            case '-22': $message = 'Transaction contains invalid authentication information'; break;
            case '-23': $message = 'Invalid transaction context'; break;
            case '-24': $message = 'Transaction context data mismatch'; break;
            case '-25': $message = 'Transaction canceled (e.g. by user)'; break;
            case '-26': $message = 'Invalid action BIN'; break;
            case '-27': $message = 'Invalid merchant name'; break;
            case '-28': $message = 'Invalid incoming addendum(s)'; break;
            case '-29': $message = 'Invalid/duplicate authentication reference'; break;
            case '-30': $message = 'Transaction was declined as fraud'; break;
            case '-31': $message = 'Transaction already in progress'; break;
            case '-32': $message = 'Duplicate declined transaction'; break;
            case '-33': $message = 'Client authentication by random amount or verify one-time code in progress'; break;
            case '-34': $message = 'MasterCard Installment client choice in progress'; break;
            case '-35': $message = 'MasterCard Installments auto canceled'; break;
            case '-97': $message = 'Session Timeout / Not Login'; break;
            case '-98': $message = 'Exceed OTP attempts limit'; break;
            case '-99': $message = 'Transaction aborted due to browser refresh'; break;
            default: $message = 'Unexpected error code'; break;
        }

        return $message;
    }

    /**
     * Validates response
     *
     * @return bool
     */
    public function isValid()
    {
        try {
            $isValid = $this->_validateResponse();
        } catch (Exception $e) {
            $isValid         = false;
            $this->_errors[] = $e->getMessage();
        }

        return $isValid;
    }

    /**
     * Validates the response
     *
     * @return bool
     * @throws Exception
     */
    protected function _validateResponse()
    {
        if (!isset($this->_responseFields[self::ACTION])) {
            throw new Exception('Bank response: Invalid data received');
        }
        if(!is_numeric($this->_responseFields[self::ACTION])) {
            $this->_responseFields[self::ACTION] = -99999999;
        }
        switch ((int)$this->_responseFields[self::ACTION]) {
            case self::STATUS_SUCCESS:
                return $this->_validateSignature();
            case self::STATUS_DUPLICATED:
                throw new Exception('Bank response: Duplicate transaction');
            case self::STATUS_DECLINED:
                throw new Exception('Bank response: Transaction declined');
            case self::STATUS_FAULT:
                throw new Exception('Bank response: Processing fault');
            case self::STATUS_INFORMATION:
                throw new Exception('Bank response: Information message');
            default:
                throw new Exception('Undefined bank response status');
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function _validateSignature()
    {
        // TODO - temporary bypass p_sign in response
        return true;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return mixed
     */
    public function getLastError()
    {
        return end($this->_errors);
    }

    /**
     * Magic method to get response fields
     *
     * @param $fieldName
     *
     * @return null
     */
    public function __get($fieldName)
    {
        if (!isset($this->_responseFields[$fieldName])) {
            return null;
        }

        return $this->_responseFields[$fieldName];
    }
}
