<?php /** @noinspection ALL */

namespace TkhConsult\KinaBankGateway;

use DateTime;
use DateTimeZone;
use TkhConsult\KinaBankGateway\KinaBank;
use TkhConsult\KinaBankGateway\KinaBank\ResponseInterface;

/**
 * Class KinaBankGateway
 *
 * @package TkhConsult\KinaBankGateway
 */
class KinaBankGateway
{
    const TRX_TYPE_AUTHORIZATION = 0;
    const TRX_TYPE_COMPLETION    = 21;
    const TRX_TYPE_REVERSAL      = 24;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var bool
     */
    private $sslVerify = true;

    /**
     * @var string
     */
    private $gatewayUrl = 'https://egateway.kinabank.md/cgi-bin/cgi_link';

    /**
     * @var string
     */
    private $merchant;

    /**
     * @var string
     */
    private $terminal;

    /**
     * @see https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
     *
     * @var array
     */
    private $supportedLanguages = ['en', 'ro', 'ru'];

    /**
     * @see http://php.net/manual/en/timezones.php
     *
     * @var string
     */
    private $timezoneName;

    /**
     * @see https://en.wikipedia.org/wiki/ISO_4217
     *
     * @var string
     */
    private $defaultCurrency = 'MDL';

    /**
     * @see https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
     *
     * @var string
     */
    private $defaultLanguage = 'en';

    /**
     * @var string
     */
    private $countryCode = 'md';

    /**
     * @var string
     */
    private $merchantName;

    /**
     * @var string
     */
    private $merchantUrl;

    /**
     * @var string
     */
    private $merchantAddress;

    /**
     * KinaBankGateway constructor.
     */
    public function __construct()
    {
        $this->timezoneName = date_default_timezone_get();
    }

    /**
     * @param string $certDir
     *
     * @return $this
     * @throws \TkhConsult\KinaBankGateway\KinaBank\Exception
     */
    public function configureFromEnv($certDir)
    {
        $certDir = rtrim($certDir);
        // Set basic info
        $this
            ->setMerchantId(getenv('KINA_BANK_MERCHANT_ID'))
            ->setMerchantTerminal(getenv('KINA_BANK_MERCHANT_TERMINAL'))
            ->setMerchantUrl(getenv('KINA_BANK_MERCHANT_URL'))
            ->setMerchantName(getenv('KINA_BANK_MERCHANT_NAME'))
            ->setMerchantAddress(getenv('KINA_BANK_MERCHANT_ADDRESS'))
            ->setTimezone(getenv('KINA_BANK_MERCHANT_TIMEZONE_NAME'))
            ->setCountryCode(getenv('KINA_BANK_MERCHANT_COUNTRY_CODE'))
            ->setDefaultCurrency(getenv('KINA_BANK_MERCHANT_DEFAULT_CURRENCY'))
            ->setDefaultLanguage(getenv('KINA_BANK_MERCHANT_DEFAULT_LANGUAGE'));
        //Set security options - provided by the bank
        $signatureFirst    = getenv('KINA_BANK_SECURITY_SIGNATURE_FIRST');
        $signaturePrefix   = getenv('KINA_BANK_SECURITY_SIGNATURE_PREFIX');
        $signaturePadding  = getenv('KINA_BANK_SECURITY_SIGNATURE_PADDING');
        $publicKeyPath     = $certDir.'/'.getenv('KINA_BANK_MERCHANT_PROD_KEY');
        $testKeyPath    = $certDir.'/'.getenv('KINA_BANK_MERCHANT_TEST_KEY');
        $this
            ->setSecurityOptions($signatureFirst, $signaturePrefix, $signaturePadding, $publicKeyPath, $testKeyPath);

        return $this;
    }

    /**
     * @return \DateTimeZone
     */
    protected function getMerchantTimeZone()
    {
        return new DateTimeZone($this->timezoneName);
    }

    /**
     * Merchant transaction timestamp in GMT: YYYYMMDDHHMMSS.
     * Timestamp difference between merchant server and e-Gateway
     * server must not exceed 1 hour, otherwise e-Gateway will reject this transaction
     *
     * @return string
     * @throws \Exception
     */
    protected function getTransactionTimestamp()
    {
        $date = new DateTime('now', $this->getMerchantTimeZone());
        $date->setTimezone(new DateTimeZone('GMT'));

        return $date->format('YmdHis');
    }

    /**
     * Merchant UTC/GMT time zone offset (e.g. –3).
     * Must be provided if merchant system is located
     * in a time zone other than the gateway server's time zone.
     *
     * @return string
     * @throws \Exception
     */
    protected function getMerchantGmtTimezoneOffset()
    {
        $dateTimeZone   = $this->getMerchantTimeZone();
        $timezoneOffset = (float)$dateTimeZone->getOffset(new DateTime()) / 3600;
        if ($timezoneOffset > 0) {
            $timezoneOffset = '+'.$timezoneOffset;
        }

        return (string)$timezoneOffset;
    }

    /**
     * Debug mode setter
     *
     * @param boolean $debug
     *
     * @return $this
     */
    public function setDebug($debug)
    {
        $this->debug = (boolean)$debug;

        return $this;
    }

    /**
     * SSL verify mode setter
     *
     * @param boolean $sslVerify
     *
     * @return $this
     */
    public function setSslVerify($sslVerify)
    {
        $this->sslVerify = (boolean)$sslVerify;

        return $this;
    }

    /**
     * Set Gateway URL
     *
     * @param string $gatewayUrl
     *
     * @return $this
     */
    public function setGatewayUrl($gatewayUrl)
    {
        $this->gatewayUrl = $gatewayUrl;

        return $this;
    }

    /**
     * Set Timezone name
     * Used to calculate the timezone offset sent to KinaBank
     *
     * @param $tzName
     *
     * @return $this
     */
    public function setTimezone($tzName)
    {
        $this->timezoneName = $tzName;

        return $this;
    }

    /**
     * Add custom supported language
     * @see https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
     *
     * If need forms in another languages please contact gateway administrator
     *
     * @param string $lang
     *
     * @return $this
     */
    public function addSupportedLanguage($lang)
    {
        $lang                       = strtolower(trim($lang));
        $this->supportedLanguages[] = $lang;

        return $this;
    }

    /**
     * Transaction forms language.
     * By default are available forms in en, ro, ru.
     * @see https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
     *
     * If need forms in another languages please contact gateway administrator
     * @see addSupportedLanguage()
     *
     * @param string $lang
     *
     * @return $this
     * @throws KinaBank\Exception
     */
    public function setDefaultLanguage($lang)
    {
        $lang = strtolower(trim($lang));
        if (!in_array($lang, $this->supportedLanguages, true)) {
            throw new KinaBank\Exception("The language '{$lang}' is not accepted by KinaBank");
        }
        $this->defaultLanguage = $lang;

        return $this;
    }

    /**
     * Merchant shop 2-character country code. Must be provided if
     * merchant system is located in a country other than the gateway
     * server's country.
     * @see https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
     *
     * @param $countryCode - two letter country code
     *
     * @return $this
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = strtolower(trim($countryCode));

        return $this;
    }

    /**
     * Set default currency for all operations
     *
     * @param int $currency 3-character currency code
     *
     * @return $this
     */
    public function setDefaultCurrency($currency)
    {
        $this->defaultCurrency = $currency;

        return $this;
    }

    /**
     * Set Merchant Terminal ID assigned by bank
     *
     * @param int $terminal
     *
     * @return $this
     */
    public function setMerchantTerminal($terminal)
    {
        $this->terminal = $terminal;

        return $this;
    }

    /**
     * Set Merchant ID assigned by bank
     *
     * @param int $id
     *
     * @return $this
     */
    public function setMerchantId($id)
    {
        $this->merchant = $id;

        return $this;
    }

    /**
     * Merchant name setter
     *
     * @param $name
     *
     * @return $this
     */
    public function setMerchantName($name)
    {
        $this->merchantName = $name;

        return $this;
    }

    /**
     * Merchant address setter
     *
     * @param $address
     *
     * @return $this
     */
    public function setMerchantAddress($address)
    {
        $this->merchantAddress = $address;

        return $this;
    }

    /**
     * Set Merchant primary web site URL
     *
     * @param $url
     *
     * @return $this
     */
    public function setMerchantUrl($url)
    {
        $this->merchantUrl = $url;

        return $this;
    }

    /**
     * @param        $signatureFirst
     * @param        $signaturePrefix
     * @param        $signaturePadding
     * @param        $publicKeyPath
     * @param        $testKeyPath
     *
     * @return $this
     */
    public function setSecurityOptions($signatureFirst, $signaturePrefix, $signaturePadding, $publicKeyPath, $testKeyPath)
    {
        #Request security options
        KinaBank\Request::$signatureFirst   = $signatureFirst;
        KinaBank\Request::$signaturePrefix  = $signaturePrefix;
        KinaBank\Request::$signaturePadding = $signaturePadding;
        KinaBank\Request::$publicKeyPath    = $publicKeyPath;
        KinaBank\Request::$testKeyPath   = $testKeyPath;
        #Response security options
        KinaBank\Response::$signaturePrefix   = $signaturePrefix;

        return $this;
    }

    /**
     * Perform an authorization request
     *
     * @param string $orderId     Merchant order ID
     * @param float  $amount      Order total amount in float format with decimal point separator
     * @param string $backRefUrl  Merchant URL for redirecting the client after receiving transaction result
     * @param string $currency    Order currency: 3-character currency code
     * @param string $description Order description
     * @param string $clientEmail Client e-mail address
     * @param string $language    Transaction forms language
     *
     * @throws KinaBank\Exception
     */
    public function requestAuthorization($orderId, $amount, $backRefUrl, $currency = null, $description = null, $clientEmail = null, $language = null)
    {
        try {
            /** @noinspection PhpUnhandledExceptionInspection */
            $request = new KinaBank\Authorization\AuthorizationRequest(
                [
                    KinaBank\Authorization\AuthorizationRequest::TERMINAL      => $this->terminal,
                    KinaBank\Authorization\AuthorizationRequest::ORDER         => static::normalizeOrderId($orderId),
                    KinaBank\Authorization\AuthorizationRequest::AMOUNT        => static::normalizeAmount($amount),
                    KinaBank\Authorization\AuthorizationRequest::CURRENCY      => $currency ? $currency : $this->defaultCurrency,
                    KinaBank\Authorization\AuthorizationRequest::TIMESTAMP     => $this->getTransactionTimestamp(),
                    KinaBank\Authorization\AuthorizationRequest::NONCE         => $this->generateNonce(),
                    KinaBank\Authorization\AuthorizationRequest::DESC          => $description ? $description : "Order {$orderId} payment",
                    KinaBank\Authorization\AuthorizationRequest::EMAIL         => (string)$clientEmail,
                    KinaBank\Authorization\AuthorizationRequest::COUNTRY       => $this->countryCode,
                    KinaBank\Authorization\AuthorizationRequest::BACKREF       => $backRefUrl,
                    KinaBank\Authorization\AuthorizationRequest::MERCH_GMT     => $this->getMerchantGmtTimezoneOffset(),
                    KinaBank\Authorization\AuthorizationRequest::LANG          => $language ? $language : $this->defaultLanguage,
                    KinaBank\Authorization\AuthorizationRequest::MERCHANT      => $this->merchant,
                    KinaBank\Authorization\AuthorizationRequest::MERCH_NAME    => $this->merchantName,
                    KinaBank\Authorization\AuthorizationRequest::MERCH_URL     => $this->merchantUrl,
                    KinaBank\Authorization\AuthorizationRequest::MERCH_ADDRESS => $this->merchantAddress,
                ], $this->gatewayUrl, $this->debug, $this->sslVerify
            );
            $request->request();
        } catch (KinaBank\Exception $e) {
            if ($this->debug) {
                throw $e;
            } else {
                throw new KinaBank\Exception(
                    'Authorization request to the payment gateway failed. Please contact '.$this->merchantUrl.' for further details'
                );
            }
        }
    }

    /**
     * @param mixed  $orderId  Merchant order ID
     * @param float  $amount   Transaction amount
     * @param string $rrn      Retrieval reference number from authorization response
     * @param string $intRef   Internal reference number from authorization response
     * @param string $currency Order currency: 3-character currency code
     *
     * @return mixed|void
     * @throws KinaBank\Exception
     */
    public function requestCompletion($orderId, $amount, $rrn, $intRef, $currency = null)
    {
        try {
            $request = new KinaBank\Completion\CompletionRequest(
                [
                    KinaBank\Completion\CompletionRequest::TERMINAL  => $this->terminal,
                    KinaBank\Completion\CompletionRequest::ORDER     => static::normalizeOrderId($orderId),
                    KinaBank\Completion\CompletionRequest::AMOUNT    => static::normalizeAmount($amount),
                    KinaBank\Completion\CompletionRequest::CURRENCY  => $currency ? $currency : $this->defaultCurrency,
                    KinaBank\Completion\CompletionRequest::TIMESTAMP => $this->getTransactionTimestamp(),
                    KinaBank\Completion\CompletionRequest::NONCE     => $this->generateNonce(),
                    KinaBank\Completion\CompletionRequest::RRN       => $rrn,
                    KinaBank\Completion\CompletionRequest::INT_REF   => $intRef,
                ], $this->gatewayUrl, $this->debug, $this->sslVerify
            );

            return $request->request();
        } catch (KinaBank\Exception $e) {
            if ($this->debug) {
                throw $e;
            } else {
                throw new KinaBank\Exception(
                    'Completion request to the payment gateway failed. Please contact '.$this->merchantUrl.' for further details.'.$e->getMessage()
                );
            }
        }
    }

    /**
     * @param mixed  $orderId  Merchant order ID
     * @param float  $amount   Transaction amount
     * @param string $rrn      Retrieval reference number from authorization response
     * @param string $intRef   Internal reference number from authorization response
     * @param string $currency Order currency: 3-character currency code
     *
     * @return mixed|void
     * @throws KinaBank\Exception
     */
    public function requestReversal($orderId, $amount, $rrn, $intRef, $currency = null)
    {
        try {
            $request = new KinaBank\Reversal\ReversalRequest(
                [
                    KinaBank\Reversal\ReversalRequest::TERMINAL  => $this->terminal,
                    KinaBank\Reversal\ReversalRequest::ORDER     => static::normalizeOrderId($orderId),
                    KinaBank\Reversal\ReversalRequest::AMOUNT    => static::normalizeAmount($amount),
                    KinaBank\Reversal\ReversalRequest::CURRENCY  => $currency ? $currency : $this->defaultCurrency,
                    KinaBank\Reversal\ReversalRequest::TIMESTAMP => $this->getTransactionTimestamp(),
                    KinaBank\Reversal\ReversalRequest::NONCE     => $this->generateNonce(),
                    KinaBank\Reversal\ReversalRequest::RRN       => $rrn,
                    KinaBank\Reversal\ReversalRequest::INT_REF   => $intRef,
                ], $this->gatewayUrl, $this->debug, $this->sslVerify
            );

            return $request->request();
        } catch (KinaBank\Exception $e) {
            if ($this->debug) {
                throw $e;
            } else {
                throw new KinaBank\Exception(
                    'Reversal request to the payment gateway failed. Please contact '.$this->merchantUrl.' for further details.'.$e->getMessage()
                );
            }
        }
    }

    /**
     * Identifies the type of response object based on the received data over post from the bank
     *
     * @param array $post
     *
     * @return ResponseInterface
     * @throws KinaBank\Exception
     */
    public function getResponseObject(array $post)
    {
        if (!isset($post[KinaBank\Response::TRTYPE])) {
            throw new KinaBank\Exception('Invalid response data');
        }
        switch ($post[KinaBank\Response::TRTYPE]) {
            case KinaBank\Authorization\AuthorizationResponse::TRX_TYPE:
                return new KinaBank\Authorization\AuthorizationResponse($post);
                break;
            case KinaBank\Completion\CompletionResponse::TRX_TYPE:
                return new KinaBank\Completion\CompletionResponse($post);
                break;
            case KinaBank\Reversal\ReversalResponse::TRX_TYPE:
                return new KinaBank\Reversal\ReversalResponse($post);
                break;
            default:
                throw new KinaBank\Exception('No response object found for the provided data');
        }
    }

    /**
     * KinaBank accepts order ID not less than 6 characters long
     *
     * @param string|int $code
     *
     * @return string
     */
    static public function normalizeOrderId($code)
    {
        return sprintf('%06s', $code);
    }

    /**
     * KinaBank accepts order ID not less than 6 characters long
     *
     * @param string $code
     *
     * @return string
     */
    static public function deNormalizeOrderId($code)
    {
        return ltrim($code, '0');
    }

    /**
     * @param float $amount
     *
     * @return mixed
     */
    static public function normalizeAmount($amount)
    {
        return str_replace(',', '.', (string)$amount);
    }

    /**
     * Merchant nonce. Must be filled with 20-32 unpredictable random
     * bytes in hexadecimal format. Must be present if MAC is used
     *
     * @return string
     */
    protected function generateNonce()
    {
        return md5(mt_rand());
    }
}
