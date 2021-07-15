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

1. Generate the public / private key pair according to the instructions from *Appendix A*, section *`2. Key Generation and transmission`* of the *`e-Commerce Gateway merchant interface (CGI/WWW forms version)`* document received from the bank
2. Configure the plugin Connection Settings by performing one of the following steps:
    * **BASIC**: Upload the generated PEM key files and the bank public key
    * **ADVANCED**: Copy the PEM key files to the server, securely set up the owner and file system permissions, configure the paths to the files
3. Set the private key password
4. Provide the *Callback URL* to the bank to enable online payment notifications
5. Enable *Test* and *Debug* modes in the plugin settings
6. Perform all the tests described in *Appendix B*, section *`Test Cases`* of the document received from the bank:
    * **Test case No 1**: Set *Transaction type* to *Charge*, create a new order and pay with a test card
    * **Test case No 2**: Set *Transaction type* to *Authorization*, create a new order and pay with a test card, afterwards perform a full order refund
    * **Test case No 3**: Set *Transaction type* to *Charge*, create a new order and pay with a test card, afterwards perform a full order refund
7. Disable *Test* and *Debug* modes when ready to accept live payments

#### Frequently Asked Questions

##### How can I configure the plugin settings?

Use the *WooCommerce > Settings > Payments > Kinabank* screen to configure the plugin.

##### Where can I get the Merchant Data and Connection Settings?

The merchant data and connection settings are provided by Kinabank. This data is used by the plugin to connect to the Kinabank payment gateway and process the card transactions.

##### What store settings are supported?

Kinabank currently supports transactions in PGK (Papua New Guinea) only.

##### What is the difference between transaction types?

* **Charge** submits all transactions for settlement.
* **Authorization** simply authorizes the order total for capture later. Use the *Complete transaction* order action to settle the previously authorized transaction.

##### How can I manually process a bank transaction response callback data message received by email from the bank?

As part of the backup procedure Kinabank payment gateway sends a duplicate copy of the transaction responses to a specially designated merchant email address specified during initial setup.
If the automated response payment notification callback failed the shop administrator can manually process the transaction response message received from the bank.
Go to the payment gateway settings screen *Payment Notification* section and click *Advanced*, paste the bank transaction response data as received in the email and click *Process*.

### [Release](../../releases) & Changelog

#### [1.3.5](../../releases/tag/v1.3.5)
avoid payment page affected by wordpress template

#### [1.3.4](../../releases/tag/v1.3.4)
auto close iframe when payment decline

#### [1.3.3](../../releases/tag/v1.3.3)
- auto open popup
- add visa, master, kinabank logo to payment page
- remove refund from order page

#### [1.3.2](../../releases/tag/v1.3.2)
- remove refund classes

#### [1.3.1](../../releases/tag/v1.3.1)
Updated WC tested up to 4.5.2