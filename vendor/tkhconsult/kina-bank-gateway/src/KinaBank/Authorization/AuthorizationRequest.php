<?php

namespace TkhConsult\KinaBankGateway\KinaBank\Authorization;

use TkhConsult\KinaBankGateway\KinaBank\Exception;
use TkhConsult\KinaBankGateway\KinaBank\Form;
use TkhConsult\KinaBankGateway\KinaBank\Request;
use TkhConsult\KinaBankGateway\KinaBankGateway;

/**
 * Class AuthorizationRequest
 *
 * @package TkhConsult\KinaBankGateway\KinaBank\Authorization
 */
class AuthorizationRequest extends Request
{
    #Visible authorization request fields
    const AMOUNT        = 'AMOUNT';         #Size: 1-12, Order total amount in float format with decimal point separator
    const CURRENCY      = 'CURRENCY';       #Size: 03, Order currency: 3-character currency code
    const ORDER         = 'ORDER';          #Size: 6-20, Merchant order ID
    const DESC          = 'DESC';           #Size: 1-50, Order description
    const MERCH_NAME    = 'MERCH_NAME';     #Size: 1-50, Merchant name (recognizable by cardholder)
    const MERCH_URL     = 'MERCH_URL';      #Size: 1-250, Merchant primary web site URL in format http://www.merchantsitename.domain
    const MERCHANT      = 'MERCHANT';       #Size: 15, Merchant ID assigned by bank
    const TERMINAL      = 'TERMINAL';       #Size: 8, Merchant Terminal ID assigned by bank
    const EMAIL         = 'EMAIL';          #Size: 80, Client e-mail address

    #Hidden authorization request fields
    const TRTYPE    = 'TRTYPE';         #Size: 1, Must be equal to "0" (Authorization).
    const COUNTRY   = 'COUNTRY';        #Size: 02, Merchant shop 2-character country code. Must be provided if merchant system is located in a country other than the gateway server's country.
    const MERCH_GMT = 'MERCH_GMT';      #Size: 1-5, Merchant UTC/GMT time zone offset (e.g. –3). Must be provided if merchant system is located in a time zone other than the gateway server's time zone.
    const TIMESTAMP = 'TIMESTAMP';      #Size: 14, Merchant transaction timestamp in GMT: YYYYMMDDHHMMSS. Timestamp difference between merchant server and e-Gateway server must not exceed 1 hour, otherwise e-Gateway will reject this transaction.
    const NONCE     = 'NONCE';          #Size: 1-64, Merchant nonce. Must be filled with 20-32 unpredictable random bytes in hexadecimal format. Must be present if MAC is used.
    const BACKREF   = 'BACKREF';        #Size: 1-250, Merchant URL for redirecting the client after receiving transaction result.
    const P_SIGN    = 'P_SIGN';         #Size: 1-256, Merchant MAC in hexadecimal form.
    const LANG      = 'LANG';           #Size: 02, Transaction forms language. By default are available forms in en, ro, ru. If need forms in another languages please contact gateway administrator.

    #Request fields
    protected $_requestFields = [
        self::AMOUNT => null,
        self::CURRENCY => null,
        self::ORDER => null,
        self::DESC => null,
        self::MERCH_NAME => null,
        self::MERCH_URL => null,
        self::MERCHANT => null,
        self::TERMINAL => null,
        self::EMAIL => null,
        self::TRTYPE => null,
        self::COUNTRY => null,
        self::MERCH_GMT => null,
        self::TIMESTAMP => null,
        self::NONCE => null,
        self::BACKREF => null,
        self::P_SIGN => null,
    ];

    /**
     *
     * @throws \TkhConsult\KinaBankGateway\KinaBank\Exception
     */
    protected function init()
    {
        parent::init();
        #Set TRX type
        $this->_requestFields[self::TRTYPE] = KinaBankGateway::TRX_TYPE_AUTHORIZATION;
        #Set TRX signature
        $order                              = $this->_requestFields[self::ORDER];
        $nonce                              = $this->_requestFields[self::NONCE];
        $timestamp                          = $this->_requestFields[self::TIMESTAMP];
        $trType                             = $this->_requestFields[self::TRTYPE];
        $amount                             = $this->_requestFields[self::AMOUNT];
        $this->_requestFields[self::P_SIGN] = $this->_createSignature([
            'TERMINAL' => $this->_requestFields[self::TERMINAL],
            'TRTYPE' => $this->_requestFields[self::TRTYPE],
            'AMOUNT' => $this->_requestFields[self::AMOUNT],
            'CURRENCY' => $this->_requestFields[self::CURRENCY],
            'ORDER' => $this->_requestFields[self::ORDER],
            'MERCHANT' => $this->_requestFields[self::MERCHANT],
            'EMAIL' => $this->_requestFields[self::EMAIL],
            'BACKREF' => $this->_requestFields[self::BACKREF],
            'TIMESTAMP' => $this->_requestFields[self::TIMESTAMP],
            'MERCH_NAME' => $this->_requestFields[self::MERCH_NAME],
            'COUNTRY' => $this->_requestFields[self::COUNTRY] ?: 'PG',
            'MERCH_URL' => $this->_requestFields[self::MERCH_URL],
            'MERCH_GMT' => $this->_requestFields[self::MERCH_GMT],
            'DESC' => $this->_requestFields[self::DESC],
            'NONCE' => $this->_requestFields[self::NONCE],
        ]);
    }

    /**
     * @return $this|mixed
     * @throws Exception
     */
    public function validateRequestParams()
    {
        if (!isset($this->_requestFields[self::AMOUNT])
            || strlen($this->_requestFields[self::AMOUNT]) < 1
            || strlen($this->_requestFields[self::AMOUNT]) > 12) {
            throw new Exception('Authorization request failed: invalid '.self::AMOUNT);
        }
        if (!isset($this->_requestFields[self::CURRENCY]) || strlen($this->_requestFields[self::CURRENCY]) != 3) {
            throw new Exception('Authorization request failed: invalid '.self::CURRENCY);
        }
        if (!isset($this->_requestFields[self::ORDER])
            || strlen($this->_requestFields[self::ORDER]) < 6
            || strlen($this->_requestFields[self::ORDER]) > 20
        ) {
            throw new Exception('Authorization request failed: invalid '.self::ORDER);
        }
        if (!isset($this->_requestFields[self::DESC]) || strlen($this->_requestFields[self::DESC]) < 1) {
            throw new Exception('Authorization request failed: invalid '.self::DESC);
        } elseif (strlen($this->_requestFields[self::DESC]) > 50) {
            $this->_requestFields[self::DESC] = substr($this->_requestFields[self::DESC], 0, 49);
        }
        if (!isset($this->_requestFields[self::MERCH_URL])
            || strlen($this->_requestFields[self::MERCH_URL]) < 1
            || strlen($this->_requestFields[self::MERCH_URL]) > 250) {
            throw new Exception('Authorization request failed: invalid '.self::MERCH_URL);
        }
        if (!isset($this->_requestFields[self::MERCHANT]) || strlen($this->_requestFields[self::MERCHANT]) != 15) {
            throw new Exception('Authorization request failed: invalid '.self::MERCHANT);
        }
        if (!isset($this->_requestFields[self::TERMINAL]) || strlen($this->_requestFields[self::TERMINAL]) != 8) {
            throw new Exception('Authorization request failed: invalid '.self::TERMINAL);
        }
        if (!isset($this->_requestFields[self::EMAIL]) || strlen($this->_requestFields[self::EMAIL]) > 80) {
            throw new Exception('Authorization request failed: invalid '.self::EMAIL);
        }
        if (isset($this->_requestFields[self::COUNTRY]) && strtoupper($this->_requestFields[self::COUNTRY]) == 'MD') {
            unset($this->_requestFields[self::COUNTRY]);
        } elseif (isset($this->_requestFields[self::COUNTRY]) && strlen($this->_requestFields[self::COUNTRY]) != 2) {
            throw new Exception('Authorization request failed: invalid '.self::COUNTRY);
        }
        if (!isset($this->_requestFields[self::TIMESTAMP]) || strlen($this->_requestFields[self::TIMESTAMP]) != 14) {
            throw new Exception('Authorization request failed: invalid '.self::TIMESTAMP);
        }
        if (!isset($this->_requestFields[self::NONCE])
            || strlen($this->_requestFields[self::NONCE]) < 20
            || strlen($this->_requestFields[self::NONCE]) > 32) {
            throw new Exception('Authorization request failed: invalid '.self::NONCE);
        }
        if (!isset($this->_requestFields[self::BACKREF])
            || strlen($this->_requestFields[self::BACKREF]) < 1
            || strlen($this->_requestFields[self::BACKREF]) > 250) {
            throw new Exception('Authorization request failed: invalid '.self::BACKREF);
        }

        return $this;
    }

    /**
     * Prepares the form to be submitted to the payment gateway and performs the redirect
     */
    public function request()
    {
        $form = new Form('authorization-request');
        if ($this->_debugMode) {
            $constructElementMethod = 'addTextElement';
        } else {
            $constructElementMethod = 'addHiddenElement';
        }
        $form->{$constructElementMethod}(self::AMOUNT, $this->_requestFields[self::AMOUNT]);
        $form->{$constructElementMethod}(self::CURRENCY, $this->_requestFields[self::CURRENCY]);
        $form->{$constructElementMethod}(self::ORDER, $this->_requestFields[self::ORDER]);
        $form->{$constructElementMethod}(self::DESC, $this->_requestFields[self::DESC]);
        $form->{$constructElementMethod}(self::MERCH_NAME, $this->_requestFields[self::MERCH_NAME]);
        $form->{$constructElementMethod}(self::MERCH_URL, $this->_requestFields[self::MERCH_URL]);
        $form->{$constructElementMethod}(self::MERCHANT, $this->_requestFields[self::MERCHANT]);
        $form->{$constructElementMethod}(self::TERMINAL, $this->_requestFields[self::TERMINAL]);
        $form->{$constructElementMethod}(self::EMAIL, $this->_requestFields[self::EMAIL]);
        if ($this->_debugMode) {
            $constructElementMethod = 'addTextElement';
        } else {
            $constructElementMethod = 'addHiddenElement';
        }
        $form->{$constructElementMethod}(self::TRTYPE, $this->_requestFields[self::TRTYPE]);
        if (isset($this->_requestFields[self::COUNTRY])) {
            $form->{$constructElementMethod}(self::COUNTRY, $this->_requestFields[self::COUNTRY]);
        } else {
            $form->{$constructElementMethod}(self::COUNTRY, 'PG');
        }
        $form->{$constructElementMethod}(self::MERCH_GMT, $this->_requestFields[self::MERCH_GMT]);
        $form->{$constructElementMethod}(self::TIMESTAMP, $this->_requestFields[self::TIMESTAMP]);
        $form->{$constructElementMethod}(self::NONCE, $this->_requestFields[self::NONCE]);
        $form->{$constructElementMethod}(self::BACKREF, $this->_requestFields[self::BACKREF]);
        $form->{$constructElementMethod}(self::P_SIGN, $this->_requestFields[self::P_SIGN]);
        $formHtml = $form->setFormMethod('POST')
                         ->setFormAction($this->_gatewayUrl)
                         ->renderForm(!$this->_debugMode);

        $this->generateHtmlPage($formHtml);
    }

    private function generateHtmlPage($formHtml) {
echo /** @lang text */
<<<HTML
    <html>
        <head>
            <title>Please wait...</title>
            <style>
                input{
                    width:500px;
                    margin: 5px;
                }
            </style>
        </head>
        <body>
            {$formHtml}
        </body>
    </html>
HTML;
exit;
    }
}