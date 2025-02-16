# WooCommerce Kinabank Payment Gateway
 ![php 7.0+](https://img.shields.io/badge/php-7.0+-brightgreen.svg?style=flat&logo=php&labelColor=777BB4&logoColor=white&color=lightgrey) ![Contributors](https://img.shields.io/badge/Contributors-tkhconsult-brightgreen.svg?style=flat&logo=bitbucket&color=lightgrey)

Tags: WooCommerce, Kinabank, KB, bank, payment, gateway, visa, mastercard, credit card

* Requires at least: `4.8`
* Tested up to: `5.5.1`
* License: GPLv3 or later
* License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce Payment Gateway for Kinabank

## Description

Accept Visa and Mastercard directly on your store with the Kinabank payment gateway for WooCommerce.

### Features 

* Charge and Authorization card transaction types
* Reverse transactions – partial or complete refunds
* Admin order actions – complete authorized transaction
* Order confirmation email with card transaction details
* Free to use – [Open-source GPL-3.0 license on GitHub](https://github.com/tkhconsult/kinawp)

### Getting Started

* [Installation Instructions](#installation)
* [Frequently Asked Questions](#frequently-asked-questions)

#### Installation

1. Refer to [Installation guide](../../blob/master/doc/)

#### Frequently Asked Questions

##### How can I configure the plugin settings?

Use the *WooCommerce > Settings > Payments > Kinabank* screen to configure the plugin.

##### Where can I get the Merchant Data and Connection Settings?

The merchant data and connection settings are provided by Kinabank.

##### Which currency are supported?

Kinabank currently supports transactions in PGK (Papua New Guinea) only.

### [Release](../../releases) & Changelog

#### [1.4.8](../../releases/tag/v1.4.8)
- update staging url

#### [1.4.7](../../releases/tag/v1.4.7)
- support woo commerce up to 9.1.2

#### [1.4.6](../../releases/tag/v1.4.6)
- avoid payment page affected by wordpress template

#### [1.4.5](../../releases/tag/v1.4.5)
- remove transaction type for authorization

#### [1.4.4](../../releases/tag/v1.4.4)
- redirect to checkout url & show error for hosted payment page

#### [1.4.3](../../releases/tag/v1.4.3)
- change prod & test url to non editable

#### [1.4.2](../../releases/tag/v1.4.2)
- update default prod url

#### [1.4.1](../../releases/tag/v1.4.1)
- fix wrong param in auto submit js

#### [1.4.0](../../releases/tag/v1.4.0)
- show error when url is not accessible

#### [1.3.9](../../releases/tag/v1.3.9)
- allow test / prod url configurable

#### [1.3.8](../../releases/tag/v1.3.8)
- add hmac verification for response

#### [1.3.7](../../releases/tag/v1.3.7)
- add support for hosted payment page

#### [1.3.6](../../releases/tag/v1.3.6)
- avoid url scheme affected by wordpress template

#### [1.3.5](../../releases/tag/v1.3.5)
- avoid payment page affected by wordpress template

#### [1.3.4](../../releases/tag/v1.3.4)
- auto close iframe when payment decline

#### [1.3.3](../../releases/tag/v1.3.3)
- auto open popup
- add visa, master, kinabank logo to payment page
- remove refund from order page

#### [1.3.2](../../releases/tag/v1.3.2)
- remove refund classes

#### [1.3.1](../../releases/tag/v1.3.1)
- Updated WC tested up to 4.5.2