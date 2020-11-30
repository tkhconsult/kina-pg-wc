# Welcome to KinaBank Merchant e-Commerce Library 👋

[![GitHub issues](https://img.shields.io/github/issues/TkhConsult/KinaBankGateway)](https://github.com/TkhConsult/KinaBankGateway/issues)
[![Version](https://img.shields.io/packagist/v/tkhconsult/kina-bank-gateway)](https://packagist.org/packages/tkhconsult/kina-bank-gateway)
![Packagist](https://img.shields.io/packagist/l/tkhconsult/kina-bank-gateway)

---

> Packagist package (library) to give any php-based website an access to the interface of KinaBank that merchant systems use to process credit card based e- commerce transactions using the standard CGI/WWW forms posting method. This interface transparently supports various cardholder authentication protocols such as 3-D Secure and Secure Code as well as legacy unauthenticated SSL commerce transactions.

#### 🏠 [Homepage](https://github.com/TkhConsult/KinaBankGateway)

## Install

```sh
composer require tkhconsult/kina-bank-gateway
```

### Requirements

* PHP >= 5.5
* OpenSSL >=0.9.8 

## Usage

### Step 1. Environment configuration (not required)

You can use one of the composer packages
```bash
composer require vlucas/phpdotenv
```

or

```bash
composer require symfony/dotenv
```

.env file

```dosini
# Merchant ID assigned by bank
KINA_BANK_MERCHANT_ID=xxxxxxxxxxxxxxx

# Merchant Terminal ID assigned by bank 
KINA_BANK_MERCHANT_TERMINAL=xxxxxxxx

# Merchant primary web site URL
KINA_BANK_MERCHANT_URL='http://example.com'

# Merchant name (recognizable by cardholder)
KINA_BANK_MERCHANT_NAME='Merchant company name'

# Merchant company registered office address
KINA_BANK_MERCHANT_ADDRESS='Merchant address'

# Security options - provided by the bank
KINA_BANK_SECURITY_SIGNATURE_FIRST='0001'
KINA_BANK_SECURITY_SIGNATURE_PREFIX='A00B00C00D00EA864886F70D020505000410'
KINA_BANK_SECURITY_SIGNATURE_PADDING='00'

# Merchant secret key
KINA_BANK_MERCHANT_SECRET_KEY=secret.key

# Default Merchant shop timezone
# Used to calculate the timezone offset sent to KinaBank
KINA_BANK_MERCHANT_TIMEZONE_NAME='Europe/Chisinau'

# Merchant shop 2-character country code. 
# Must be provided if merchant system is located 
# in a country other than the gateway server's country. 
KINA_BANK_MERCHANT_COUNTRY_CODE=MD

# Default currency for all operations: 3-character currency code 
KINA_BANK_MERCHANT_DEFAULT_CURRENCY=MDL

# Default forms language
# By default are available forms in en, ro, ru. 
# If need forms in another languages please contact gateway
# administrator
KINA_BANK_MERCHANT_DEFAULT_LANGUAGE=ro
```

### Step 2. Init Gateway client

#### Init Gateway client through configureFromEnv method

```php
<?php

use TkhConsult\KinaBankGateway\KinaBankGateway;

$kinaBankGateway = new KinaBankGateway();

$certDir = '/path/to/cert/dir';
$kinaBankGateway
    ->configureFromEnv($certDir)
;
```

#### Init Gateway client manually

You can reproduce implementation of the configureFromEnv() method


### Step 3. Request payment authorization - redirects to the banks page

```php
<?php

use TkhConsult\KinaBankGateway\KinaBankGateway;
$backRefUrl = getenv('KINA_BANK_MERCHANT_URL').'/after-payment/';

/** @var KinaBankGateway $kinaBankGateway */
$kinaBankGateway
    ->requestAuthorization($orderId = 1, $amount = 1, $backRefUrl, $currency = null, $description = null, $clientEmail = null, $language = null)
;
```

### Step 4. Receive bank responses - all bank responses are asynchronous server to server and are handled by same URI

```php
<?php

use TkhConsult\KinaBankGateway\KinaBankGateway;
use TkhConsult\KinaBankGateway\KinaBank\Exception;
use TkhConsult\KinaBankGateway\KinaBank\Response;
use TkhConsult\KinaBankGateway\KinaBank\AuthorizationResponse;

/** @var KinaBankGateway $kinaBankGateway */
$bankResponse = $kinaBankGateway->getResponseObject($_POST);

if (!$bankResponse->isValid()) {
    throw new Exception('Invalid bank Auth response');
}

switch ($bankResponse::TRX_TYPE) {
    case KinaBankGateway::TRX_TYPE_AUTHORIZATION:
        $amount         = $bankResponse->{Response::AMOUNT};
        $bankOrderCode  = $bankResponse->{Response::ORDER};
        $rrn            = $bankResponse->{Response::RRN};
        $intRef         = $bankResponse->{Response::INT_REF};

        #
        # You must save $rrn and $intRef from the response here for reversal requests
        #

        # Funds locked on bank side - transfer the product/service to the customer and request completion
        $kinaBankGateway->requestCompletion($amount, $bankOrderCode, $rrn, $intRef, $currency = null);
        break;

    case KinaBankGateway::TRX_TYPE_COMPLETION:
        # Funds successfully transferred on bank side
        break;

    case KinaBankGateway::TRX_TYPE_REVERSAL:
        # Reversal successfully applied on bank size
        break;

    default:
        throw new Exception('Unknown bank response transaction type');
}
```

### Step 5. Request reversal (refund)

```$rrn``` and ```$intRef``` must be saved on the step 4

```php
<?php

use TkhConsult\KinaBankGateway\KinaBankGateway;

/** @var KinaBankGateway $kinaBankGateway */
$kinaBankGateway
    ->requestReversal($orderId = 1, $amount = 1, $rrn = 'xxx', $intRef = 'yyy', $currency = null)
;
```

## Author

👤 Lovely handcrafted by **TkhConsult team**

* Github: [@tkhconsult](https://github.com/tkhconsult)

## 🤝 Contributing

Contributions, issues and feature requests are welcome!<br />Feel free to check [issues page](https://github.com/TkhConsult/KinaBankGateway/issues).