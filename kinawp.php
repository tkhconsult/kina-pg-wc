<?php
/**
 * Plugin Name: WooCommerce Kinabank Payment Gateway
 * Description: WooCommerce Payment Gateway for Kinabank
 * Plugin URI: https://github.com/tkhconsult/kinawp
 * Version: 1.3.1
 * Author: tkhconsult
 * Text Domain: kinawp
 * Domain Path: /languages
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 4.8
 * Tested up to: 5.5.1
 * WC requires at least: 3.3
 * WC tested up to: 4.5.2
 */

//Looking to contribute code to this plugin? Go ahead and fork the repository over at GitHub https://github.com/tkhconsult/kinawp
//This plugin is based on KinaBankGateway by TkhConsult https://github.com/TkhConsult/KinaBankGateway (https://packagist.org/packages/tkhconsult/kina-bank-gateway)

if(!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once(__DIR__ . '/vendor/autoload.php');

use TkhConsult\KinaBankGateway\KinaBankGateway;
use TkhConsult\KinaBankGateway\KinaBank\Exception;
use TkhConsult\KinaBankGateway\KinaBank\Response;

add_action('plugins_loaded', 'woocommerce_kinabank_init', 0);

function woocommerce_kinabank_init() {
	if(!class_exists('WC_Payment_Gateway'))
		return;

	load_plugin_textdomain('kinawp', false, dirname(plugin_basename(__FILE__)) . '/languages');

	class WC_KinaBank extends WC_Payment_Gateway {
		protected $logger;

		#region Constants
		const MOD_ID          = 'kinabank';
		const MOD_TITLE       = 'Kinabank';
		const MOD_PREFIX      = 'kb_';
		const MOD_TEXT_DOMAIN = 'kinawp';

		const TRANSACTION_TYPE_CHARGE = 'charge';
		const TRANSACTION_TYPE_AUTHORIZATION = 'authorization';

		const LOGO_TYPE_BANK       = 'bank';
		const LOGO_TYPE_SYSTEMS    = 'systems';

		const MOD_TRANSACTION_TYPE = self::MOD_PREFIX . 'transaction_type';

		const SUPPORTED_CURRENCIES = ['PGK'];
		const ORDER_TEMPLATE       = 'Order #%1$s';

		const KB_ORDER    = 'ORDER';
		const KB_ORDER_ID = 'order_id';

		const KB_RRN      = self::MOD_PREFIX . 'RRN';
		const KB_INT_REF  = self::MOD_PREFIX . 'INT_REF';
		const KB_APPROVAL = self::MOD_PREFIX . 'APPROVAL';
		const KB_CARD     = self::MOD_PREFIX . 'CARD';

		//e-Commerce Gateway merchant interface (CGI/WWW forms version)
		//Appendix A: P_SIGN creation/verification in the Merchant System
		//https://github.com/TkhConsult/KinaBankGateway/blob/master/doc/e-Gateway_Merchant_CGI_2.1.pdf
		const KB_SIGNATURE_FIRST   = '0001';
		const KB_SIGNATURE_PREFIX  = '3020300C06082A864886F70D020505000410';
		const KB_SIGNATURE_PADDING = '00';
		#endregion

		public function __construct() {
			$plugin_dir = plugin_dir_url(__FILE__);

			$this->logger = wc_get_logger();

			$this->id                 = self::MOD_ID;
			$this->method_title       = self::MOD_TITLE;
			$this->method_description = 'WooCommerce Payment Gateway for Kinabank';
			$this->icon               = apply_filters('woocommerce_kinabank_icon', $plugin_dir . 'assets/img/kinabank.png');
			$this->has_fields         = false;
			$this->supports           = array('products', 'refunds');

			$this->init_form_fields();
			$this->init_settings();

			#region Initialize user set variables
			$this->enabled           = $this->get_option('enabled', 'yes');
			$this->title             = $this->get_option('title', $this->method_title);
			$this->description       = $this->get_option('description');

			$this->logo_type         = $this->get_option('logo_type', self::LOGO_TYPE_BANK);
			$this->bank_logo         = $plugin_dir . 'assets/img/kinabank.png';
			$this->systems_logo      = $plugin_dir . 'assets/img/paymentsystems.png';
			$plugin_icon             = ($this->logo_type === self::LOGO_TYPE_BANK ? $this->bank_logo : $this->systems_logo);
			$this->icon              = apply_filters('woocommerce_kinabank_icon', $plugin_icon);

			$this->testmode          = 'yes' === $this->get_option('testmode', 'no');
			$this->debug             = 'yes' === $this->get_option('debug', 'no');

			$this->log_context       = array('source' => $this->id);
			$this->log_threshold     = $this->debug ? WC_Log_Levels::DEBUG : WC_Log_Levels::NOTICE;
			$this->logger            = new WC_Logger(null, $this->log_threshold);

			$this->transaction_type     = $this->get_option('transaction_type', self::TRANSACTION_TYPE_CHARGE);
			$this->transaction_auto     = false; //'yes' === $this->get_option('transaction_auto', 'no');
			$this->order_template       = $this->get_option('order_template', self::ORDER_TEMPLATE);

			$this->kb_merchant_id       = $this->get_option('kb_merchant_id');
			$this->kb_merchant_terminal = $this->get_option('kb_merchant_terminal');
			$this->kb_merchant_name     = $this->get_option('kb_merchant_name');
			$this->kb_merchant_url      = $this->get_option('kb_merchant_url');
			$this->kb_merchant_address  = $this->get_option('kb_merchant_address');

			$this->kb_prod_key_file      = $this->get_option('kb_prod_key_file');
			$this->kb_test_key_file      = $this->get_option('kb_test_key_file');

			$this->kb_prod_key           = $this->get_option('kb_prod_key');
			$this->kb_test_key           = $this->get_option('kb_test_key');
			#endregion

			$this->initialize_keys();

			$this->update_option('kb_callback_url', $this->get_callback_url());

			if(is_admin()) {
				//Save options
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			}

			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

			if($this->transaction_auto) {
				add_filter('woocommerce_order_status_completed', array($this, 'order_status_completed'));
				add_filter('woocommerce_order_status_cancelled', array($this, 'order_status_cancelled'));
				add_filter('woocommerce_order_status_refunded', array($this, 'order_status_refunded'));
			}

			#region Payment listener/API hook
			add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_response'));
			add_action('woocommerce_api_wc_' . $this->id . '_redirect', array($this, 'check_redirect'));
			#endregion
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'         => array(
					'title'       => __('Enable/Disable', self::MOD_TEXT_DOMAIN),
					'type'        => 'checkbox',
					'label'       => __('Enable this gateway', self::MOD_TEXT_DOMAIN),
					'default'     => 'yes'
				),
				'title'           => array(
					'title'       => __('Title', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'desc_tip'    => __('Payment method title that the customer will see during checkout.', self::MOD_TEXT_DOMAIN),
					'default'     => self::MOD_TITLE
				),
				'description'     => array(
					'title'       => __('Description', self::MOD_TEXT_DOMAIN),
					'type'        => 'textarea',
					'desc_tip'    => __('Payment method description that the customer will see during checkout.', self::MOD_TEXT_DOMAIN),
					'default'     => ''
				),
				'logo_type' => array(
					'title'       => __('Logo', self::MOD_TEXT_DOMAIN),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'desc_tip'    => __('Payment method logo image that the customer will see during checkout.', self::MOD_TEXT_DOMAIN),
					'default'     => self::LOGO_TYPE_BANK,
					'options'     => array(
						self::LOGO_TYPE_BANK    => __('Bank logo', self::MOD_TEXT_DOMAIN),
						self::LOGO_TYPE_SYSTEMS => __('Payment systems logos', self::MOD_TEXT_DOMAIN)
					)
				),

				'testmode'        => array(
					'title'       => __('Test mode', self::MOD_TEXT_DOMAIN),
					'type'        => 'checkbox',
					'label'       => __('Enabled', self::MOD_TEXT_DOMAIN),
					'desc_tip'    => __('Use Test or Live bank gateway to process the payments. Disable when ready to accept live payments.', self::MOD_TEXT_DOMAIN),
					'default'     => 'no'
				),
				'debug'           => array(
					'title'       => __('Debug mode', self::MOD_TEXT_DOMAIN),
					'type'        => 'checkbox',
					'label'       => __('Enable logging', self::MOD_TEXT_DOMAIN),
					'default'     => 'no',
					'description' => sprintf('<a href="%2$s">%1$s</a>', __('View logs', self::MOD_TEXT_DOMAIN), self::get_logs_url()),
					'desc_tip'    => __('Save debug messages to the WooCommerce System Status logs. Note: this may log personal information. Use this for debugging purposes only and delete the logs when finished.', self::MOD_TEXT_DOMAIN)
				),

				'transaction_type' => array(
					'title'       => __('Transaction type', self::MOD_TEXT_DOMAIN),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'desc_tip'    => __('Select how transactions should be processed. Charge submits all transactions for settlement, Authorization simply authorizes the order total for capture later.', self::MOD_TEXT_DOMAIN),
					'default'     => self::TRANSACTION_TYPE_CHARGE,
					'options'     => array(
						self::TRANSACTION_TYPE_CHARGE        => __('Charge (Purchase/Sale)', self::MOD_TEXT_DOMAIN),
						self::TRANSACTION_TYPE_AUTHORIZATION => __('Authorization', self::MOD_TEXT_DOMAIN)
					)
				),
				/*'transaction_auto' => array(
					'title'       => __('Transaction auto', self::MOD_TEXT_DOMAIN),
					'type'        => 'checkbox',
					//'label'       => __('Enabled', self::MOD_TEXT_DOMAIN),
					'label'       => __('Automatically complete/reverse bank transactions when order status changes', self::MOD_TEXT_DOMAIN),
					'default'     => 'no'
				),*/
				'order_template'  => array(
					'title'       => __('Order description', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'description' => __('Format: <code>%1$s</code> - Order ID, <code>%2$s</code> - Order items summary', self::MOD_TEXT_DOMAIN),
					'desc_tip'    => __('Order description that the customer will see on the bank payment page.', self::MOD_TEXT_DOMAIN),
					'default'     => self::ORDER_TEMPLATE
				),

				'merchant_settings' => array(
					'title'       => __('Merchant Data', self::MOD_TEXT_DOMAIN),
					'description' => __('Merchant information that the customer will see on the bank payment page.', self::MOD_TEXT_DOMAIN),
					'type'        => 'title'
				),
				'kb_merchant_name' => array(
					'title'       => __('Merchant name', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					"desc_tip"    => 'Latin symbols',
					'description' => $blogInfoName = get_bloginfo('name'),
					'default'     => $blogInfoName,
					'custom_attributes' => array(
						'maxlength' => '50'
					)
				),
				'kb_merchant_url' => array(
					'title'       => __('Merchant URL', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'description' => $homeUrl = home_url(),
					'default'     => $homeUrl,
					'custom_attributes' => array(
						'maxlength' => '250'
					)
				),
				'kb_merchant_address' => array(
					'title'       => __('Merchant address', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'description' => $storeAddress = $this->get_store_address(),
					'default'     => $storeAddress,
					'custom_attributes' => array(
						'maxlength' => '250'
					)
				),
				'kb_merchant_id'  => array(
					'title'       => __('Card acceptor ID', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'description' => 'Example: 498000049812345',
					'default'     => '',
					'custom_attributes' => array(
						'maxlength' => '15'
					)
				),
				'kb_merchant_terminal' => array(
					'title'       => __('Terminal ID', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'description' => 'Example: 49812345',
					'default'     => '',
					'custom_attributes' => array(
						'maxlength' => '8'
					)
				),

				'connection_settings' => array(
					'title'       => __('Connection Settings', self::MOD_TEXT_DOMAIN),
					'description' => sprintf('%1$s<br /><br /><a href="#" id="woocommerce_kinabank_basic_settings" class="button">%2$s</a>&nbsp;%3$s&nbsp;<a href="#" id="woocommerce_kinabank_advanced_settings" class="button">%4$s</a>',
						__('Use Basic settings to upload the key files received from the bank or configure manually using Advanced settings below.', self::MOD_TEXT_DOMAIN),
						__('Basic settings&raquo;', self::MOD_TEXT_DOMAIN),
						__('or', self::MOD_TEXT_DOMAIN),
						__('Advanced settings&raquo;', self::MOD_TEXT_DOMAIN)),
					'type'        => 'title'
				),
				'kb_test_key_file' => array(
					'title'       => __('MAC secret key file for TEST', self::MOD_TEXT_DOMAIN),
					'type'        => 'file',
					'description' => '<code>test.key</code>',
					'custom_attributes' => array(
						'accept' => '.key'
					)
				),
                'kb_prod_key_file' => array(
                    'title'       => __('MAC secret key file for PROD', self::MOD_TEXT_DOMAIN),
                    'type'        => 'file',
                    'description' => '<code>prod.key</code>',
                    'custom_attributes' => array(
                        'accept' => '.key'
                    )
                ),
				'kb_test_key'  => array(
					'title'       => __('MAC secret key path for TEST', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					'description' => '<code>/path/to/test.key</code>',
					'default'     => ''
				),
                'kb_prod_key'   => array(
                    'title'       => __('MAC secret key path for PROD', self::MOD_TEXT_DOMAIN),
                    'type'        => 'text',
                    'description' => '<code>/path/to/prod.key</code>',
                    'default'     => ''
                ),
				'payment_notification' => array(
					'title'       => __('Payment Notification', self::MOD_TEXT_DOMAIN),
					'description' => __('Provide this URL to the bank to enable online payment notifications.', self::MOD_TEXT_DOMAIN),
					'type'        => 'title'
				),
				'kb_callback_url'  => array(
					'title'       => __('Callback URL', self::MOD_TEXT_DOMAIN),
					'type'        => 'text',
					//'default'     => $this->get_callback_url(),
					//'disabled'    => true
					'custom_attributes' => array(
						'readonly' => 'readonly'
					),
					'description' => sprintf('<a href="#" id="woocommerce_kinabank_payment_notification_advanced" class="button">%1$s</a>',
						__('Advanced&raquo;', self::MOD_TEXT_DOMAIN)),
				),
				'kb_callback_data'  => array(
					'title'       => __('Process callback data', self::MOD_TEXT_DOMAIN),
					'description' => '<a href="#" id="woocommerce_kinabank_callback_data_process" class="button">Process</a>',
					'type'        => 'textarea',
					'desc_tip'    => __('Manually process bank transaction response callback data received by email as part of the backup procedure.', self::MOD_TEXT_DOMAIN),
					'placeholder' => __('Bank transaction response callback data', self::MOD_TEXT_DOMAIN),
				)
			);
		}

		public function is_valid_for_use() {
			if(!in_array(get_option('woocommerce_currency'), self::SUPPORTED_CURRENCIES)) {
				return false;
			}

			return true;
		}

		public function is_available() {
			if(!$this->is_valid_for_use())
				return false;

			if(!$this->check_settings())
				return false;

			return parent::is_available();
		}

		public function needs_setup() {
			return !$this->check_settings();
		}

		public function admin_options() {
			$this->validate_settings();
			$this->display_errors();

			wc_enqueue_js('
				jQuery(function() {
					var kb_connection_basic_fields_ids      = "#woocommerce_kinabank_kb_prod_key_file, #woocommerce_kinabank_kb_test_key_file";
					var kb_connection_advanced_fields_ids   = "#woocommerce_kinabank_kb_prod_key, #woocommerce_kinabank_kb_test_key";
					var kb_notification_advanced_fields_ids = "#woocommerce_kinabank_kb_callback_data";

					var kb_connection_basic_fields      = jQuery(kb_connection_basic_fields_ids).closest("tr");
					var kb_connection_advanced_fields   = jQuery(kb_connection_advanced_fields_ids).closest("tr");
					var kb_notification_advanced_fields = jQuery(kb_notification_advanced_fields_ids).closest("tr");

					jQuery(document).ready(function() {
						kb_connection_basic_fields.hide();
						kb_connection_advanced_fields.hide();
						kb_notification_advanced_fields.hide();
					});

					jQuery("#woocommerce_kinabank_basic_settings").on("click", function() {
						kb_connection_advanced_fields.hide();
						kb_connection_basic_fields.show();
						return false;
					});

					jQuery("#woocommerce_kinabank_advanced_settings").on("click", function() {
						kb_connection_basic_fields.hide();
						kb_connection_advanced_fields.show();
						return false;
					});

					jQuery("#woocommerce_kinabank_payment_notification_advanced").on("click", function() {
						kb_notification_advanced_fields.show();
						return false;
					});

					jQuery("#woocommerce_kinabank_callback_data_process").on("click", function() {
						if(!confirm("' . esc_js(__('Are you sure you want to process the entered bank transaction response callback data?', self::MOD_TEXT_DOMAIN)) . '"))
							return false;

						var $this = jQuery(this);

						if($this.attr("disabled"))
							return false;

						$this.attr("disabled", true);
						var callback_data = jQuery("#woocommerce_kinabank_kb_callback_data").val();

						jQuery.ajax({
							type: "POST",
							data: {
								_ajax_nonce: "' . wp_create_nonce('callback_data_process') . '",
								action: "kinabank_callback_data_process",
								callback_data: callback_data
							},
							dataType: "json",
							url: ajaxurl,
							complete: function(response, textStatus) {
								$this.attr("disabled", false);

								if(response.responseJSON && response.responseJSON.data) {
									alert(response.responseJSON.data);
								} else {
									alert(response.responseText);
								}
							}
						});

						return false;
					});
				});
			');

			parent::admin_options();
		}

		public function process_admin_options() {
			unset($_POST['woocommerce_kinabank_kb_callback_data']);

			$this->process_file_setting('woocommerce_kinabank_kb_prod_key_file', $this->kb_prod_key_file, 'woocommerce_kinabank_kb_prod_key', 'prod.key');
			$this->process_file_setting('woocommerce_kinabank_kb_test_key_file', $this->kb_test_key_file, 'woocommerce_kinabank_kb_test_key', 'test.key');

			return parent::process_admin_options();
		}

		protected function check_settings() {
			return !self::string_empty($this->kb_prod_key)
				&& !self::string_empty($this->kb_test_key);
		}

		protected function validate_settings() {
			$validate_result = true;

			if(!$this->is_valid_for_use()) {
				$this->add_error(sprintf('<strong>%1$s: %2$s</strong>. %3$s: %4$s',
					__('Unsupported store currency', self::MOD_TEXT_DOMAIN),
					get_option('woocommerce_currency'),
					__('Supported currencies', self::MOD_TEXT_DOMAIN),
					join(', ', self::SUPPORTED_CURRENCIES)));

				$validate_result = false;
			}

			if(!$this->check_settings()) {
				$this->add_error(sprintf('<strong>%1$s</strong>: %2$s', __('Connection Settings', self::MOD_TEXT_DOMAIN), __('Not configured', self::MOD_TEXT_DOMAIN)));
				$validate_result = false;
			}

			$result = $this->validate_prod_key($this->kb_prod_key);
			if(!self::string_empty($result)) {
				$this->add_error(sprintf('<strong>%1$s</strong>: %2$s', __('PROD key file', self::MOD_TEXT_DOMAIN), $result));
				$validate_result = false;
			}

			$result = $this->validate_test_key($this->kb_test_key);
			if(!self::string_empty($result)) {
				$this->add_error(sprintf('<strong>%1$s</strong>: %2$s', __('TEST key file', self::MOD_TEXT_DOMAIN), $result));
				$validate_result = false;
			}

			if(ini_get('allow_url_fopen') != 1) {
				$this->add_error(sprintf('<strong>PHP %1$s</strong>: %2$s', 'allow_url_fopen', __('Current server settings do not allow web requests to the bank payment gateway. See <a href="https://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen" target="_blank">PHP Runtime Configuration</a> for details.', self::MOD_TEXT_DOMAIN)));
				$validate_result = false;
			}

			return $validate_result;
		}

		protected function settings_admin_notice() {
			if(self::is_wc_admin()) {
				$message = sprintf(__('Please review the <a href="%1$s">payment method settings</a> page for log details and setup instructions.', self::MOD_TEXT_DOMAIN), self::get_settings_url());
				wc_add_notice($message, 'error');
			}
		}

		#region Keys
		protected function process_file_setting($pemFieldId, $pemOptionValue, $pemTargetFieldId, $pemType) {
			try {
				if(array_key_exists($pemFieldId, $_FILES)) {
					$pemFile = $_FILES[$pemFieldId];
					$tmpName = $pemFile['tmp_name'];

					if($pemFile['error'] == UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
						$pemData = file_get_contents($tmpName);

						if($pemData !== false) {
							$result = $this->save_temp_file($pemData, $pemType);

							if(!self::string_empty($result)) {
								//Overwrite advanced setting value
								$_POST[$pemTargetFieldId] = $result;
								//Save uploaded file to settings
								$_POST[$pemFieldId] = $pemData;

								return;
							}
						}
					}
				}
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
			}

			//Preserve existing value
			$_POST[$pemFieldId] = $pemOptionValue;
		}

		protected function initialize_keys() {
			$this->initialize_key($this->kb_prod_key, $this->kb_prod_key_file, 'kb_prod_key', 'prod.key');
			$this->initialize_key($this->kb_test_key, $this->kb_test_key_file, 'kb_test_key', 'test.key');
		}

		protected function initialize_key(&$pemFile, $pemData, $pemOptionName, $pemType) {
			try {
				if(!is_readable($pemFile)) {
					if(self::is_overwritable($pemFile)) {
						if(!self::string_empty($pemData)) {
							$result = $this->save_temp_file($pemData, $pemType);

							if(!self::string_empty($result)) {
								$this->update_option($pemOptionName, $result);
								$pemFile = $result;
							}
						}
					}
				}
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
			}
		}

		protected function validate_prod_key($keyFile) {
			try {
				$validateResult = $this->validate_file($keyFile);
				if(!self::string_empty($validateResult))
					return $validateResult;

				$keyData = file_get_contents($keyFile);

				if($keyData == '') {
					return __('Invalid public key', self::MOD_TEXT_DOMAIN);
				}
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
				return __('Could not validate public key', self::MOD_TEXT_DOMAIN);
			}
		}

		protected function validate_test_key($keyFile) {
			try {
				$validateResult = $this->validate_file($keyFile);
				if(!self::string_empty($validateResult))
					return $validateResult;

				$keyData = file_get_contents($keyFile);

				if($keyData == '') {
					return __('Invalid test key', self::MOD_TEXT_DOMAIN);
				}
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
				return __('Could not validate test key', self::MOD_TEXT_DOMAIN);
			}
		}

		protected function validate_file($file) {
			try {
				if(self::string_empty($file))
					return __('Invalid value', self::MOD_TEXT_DOMAIN);

				if(!file_exists($file))
					return __('File not found', self::MOD_TEXT_DOMAIN);

				if(!is_readable($file))
					return __('File not readable', self::MOD_TEXT_DOMAIN);
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
				return __('Could not validate file', self::MOD_TEXT_DOMAIN);
			}
		}

		protected function log_openssl_errors() {
			while($opensslError = openssl_error_string())
				$this->log($opensslError, WC_Log_Levels::ERROR);
		}

		protected function save_temp_file($fileData, $fileSuffix = '') {
			//http://www.pathname.com/fhs/pub/fhs-2.3.html#TMPTEMPORARYFILES
			$tempFileName = sprintf('%1$s%2$s_', self::MOD_PREFIX, $fileSuffix);
			$temp_file = tempnam(get_temp_dir(),  $tempFileName);

			if(!$temp_file) {
				$this->log(sprintf(__('Unable to create temporary file: %1$s', self::MOD_TEXT_DOMAIN), $temp_file), WC_Log_Levels::ERROR);
				return null;
			}

			if(false === file_put_contents($temp_file, $fileData)) {
				$this->log(sprintf(__('Unable to save data to temporary file: %1$s', self::MOD_TEXT_DOMAIN), $temp_file), WC_Log_Levels::ERROR);
				return null;
			}

			return $temp_file;
		}

		static function is_temp_file($fileName) {
			$temp_dir = get_temp_dir();
			return strncmp($fileName, $temp_dir, strlen($temp_dir)) === 0;
		}

		static function is_overwritable($fileName) {
			return self::string_empty($fileName) || self::is_temp_file($fileName);
		}
		#endregion

		protected function init_kb_client() {
			$kinaBankGateway = new KinaBankGateway();

			$gatewayUrl = ($this->testmode ? 'https://devegateway.kinabank.com.pg/cgi-bin/cgi_link' : 'https://devegateway.kinabank.com.pg/cgi-bin/cgi_link'); #ALT TEST kb19.kinabank.md
			$sslVerify  = !$this->testmode;

			//Set basic info
			$kinaBankGateway
				->setGatewayUrl($gatewayUrl)
				->setSslVerify($sslVerify)
				->setMerchantId($this->kb_merchant_id)
				->setMerchantTerminal($this->kb_merchant_terminal)
				->setMerchantUrl($this->kb_merchant_url)
				->setMerchantName($this->kb_merchant_name)
				->setMerchantAddress($this->kb_merchant_address)
				->setTimezone(wc_timezone_string())
				->setDefaultLanguage($this->get_language());
				//->setCountryCode(WC()->countries->get_base_country())
				//->setDefaultCurrency(get_woocommerce_currency())
				//->setDebug($this->debug)

			//Set security options - provided by the bank
			$kinaBankGateway->setSecurityOptions(
				self::KB_SIGNATURE_FIRST,
				self::KB_SIGNATURE_PREFIX,
				self::KB_SIGNATURE_PADDING,
				$this->kb_prod_key,
				$this->kb_test_key);

			return $kinaBankGateway;
		}

		public function process_payment($order_id) {
			if(!$this->check_settings()) {
				$message = sprintf(__('%1$s is not properly configured.', self::MOD_TEXT_DOMAIN), $this->method_title);

				wc_add_notice($message, 'error');
				$this->settings_admin_notice();

				return array(
					'result'   => 'failure',
					'messages' => $message
				);
			}

			if(is_ajax()) {
				$order = wc_get_order($order_id);

				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}

			$this->receipt_page($order_id);
		}

		#region Order status
		public function order_status_completed($order_id) {
			$this->log(sprintf('%1$s: OrderID=%2$s', __FUNCTION__, $order_id));

			if(!$this->transaction_auto)
				return;

			$order = wc_get_order($order_id);

			if($order && $order->get_payment_method() === $this->id) {
				if($order->has_status('completed') && $order->is_paid()) {
					$transaction_type = get_post_meta($order_id, self::MOD_TRANSACTION_TYPE, true);

					if($transaction_type === self::TRANSACTION_TYPE_AUTHORIZATION) {
						return $this->complete_transaction($order_id, $order);
					}
				}
			}
		}

		public function order_status_cancelled($order_id) {
			$this->log(sprintf('%1$s: OrderID=%2$s', __FUNCTION__, $order_id));

			if(!$this->transaction_auto)
				return;

			$order = wc_get_order($order_id);

			if($order && $order->get_payment_method() === $this->id) {
				if($order->has_status('cancelled') && $order->is_paid()) {
					$transaction_type = get_post_meta($order_id, self::MOD_TRANSACTION_TYPE, true);

					if($transaction_type === self::TRANSACTION_TYPE_AUTHORIZATION) {
						return $this->refund_transaction($order_id, $order);
					}
				}
			}
		}

		public function order_status_refunded($order_id) {
			$this->log(sprintf('%1$s: OrderID=%2$s', __FUNCTION__, $order_id));

			$order = wc_get_order($order_id);

			if($order && $order->get_payment_method() === $this->id) {
				if($order->has_status('refunded') && $order->is_paid()) {
					return $this->refund_transaction($order_id, $order);
				}
			}
		}
		#endregion

		public function complete_transaction($order_id, $order) {
			$this->log(sprintf('%1$s: OrderID=%2$s', __FUNCTION__, $order_id));

			$rrn = get_post_meta($order_id, strtolower(self::KB_RRN), true);
			$intRef = get_post_meta($order_id, strtolower(self::KB_INT_REF), true);
			$order_total = $this->get_order_net_total($order);
			$order_currency = $order->get_currency();

			//Funds locked on bank side - transfer the product/service to the customer and request completion
			$validate_result = false;
			try {
				$kinaBankGateway = $this->init_kb_client();
				$completion_result = $kinaBankGateway->requestCompletion($order_id, $order_total, $rrn, $intRef, $order_currency);
				$validate_result = self::validate_response_form($completion_result);
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
			}

			if(!$validate_result) {
				$message = sprintf(__('Payment completion via %1$s failed', self::MOD_TEXT_DOMAIN), $this->method_title);
				$message = $this->get_order_message($message);
				$order->add_order_note($message);

				return new WP_Error('error', $message);
			}

			return $validate_result;
		}

		public function refund_transaction($order_id, $order, $amount = null) {
			$this->log(sprintf('%1$s: OrderID=%2$s Amount=%3$s', __FUNCTION__, $order_id, $amount));

			$rrn = get_post_meta($order_id, strtolower(self::KB_RRN), true);
			$intRef = get_post_meta($order_id, strtolower(self::KB_INT_REF), true);
			$order_total = $order->get_total();
			$order_currency = $order->get_currency();

			if(!isset($amount)) {
				//Refund entirely if no amount is specified
				$amount = $order_total;
			}

			if($amount <= 0 || $amount > $order_total) {
				$message = sprintf(__('Invalid refund amount', self::MOD_TEXT_DOMAIN));
				$this->log($message, WC_Log_Levels::ERROR);

				return new WP_Error('error', $message);
			}

			$validate_result = false;
			try {
				$kinaBankGateway = $this->init_kb_client();
				$reversal_result = $kinaBankGateway->requestReversal($order_id, $amount, $rrn, $intRef, $order_currency);
				$validate_result = self::validate_response_form($reversal_result);
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
			}

			if(!$validate_result) {
				$message = sprintf(__('Refund of %1$s %2$s via %3$s failed', self::MOD_TEXT_DOMAIN), $amount, $order_currency, $this->method_title);
				$message = $this->get_order_message($message);
				$order->add_order_note($message);

				return new WP_Error('error', $message);
			}

			return $validate_result;
		}

		protected function check_transaction(WC_Order $order, $bankResponse) {
			$amount   = $bankResponse->{Response::AMOUNT};
			$currency = $bankResponse->{Response::CURRENCY};
			$trxType  = $bankResponse::TRX_TYPE;

			$order_total = $order->get_total();
			$order_currency = $order->get_currency();

			//Validate currency
			if(strtolower($currency) !== strtolower($order_currency))
				return false;

			//Validate amount
			if($amount <= 0)
				return false;

			if($trxType === KinaBankGateway::TRX_TYPE_REVERSAL)
				return $amount <= $order_total;

			return $amount == $order_total;
		}

		public function check_redirect() {
			$this->log_request(__FUNCTION__);

			//Received payment data from KB here instead of CallbackURL?
			if($_SERVER['REQUEST_METHOD'] === 'POST')
				$this->process_response_data($_POST);

			$order_id = $_REQUEST[self::KB_ORDER_ID];
            $order_id = wc_clean($order_id);

			if(self::string_empty($order_id)) {
				$message = sprintf(__('Payment verification failed: Order ID not received from %1$s.', self::MOD_TEXT_DOMAIN), $this->method_title);
				$this->log($message, WC_Log_Levels::ERROR);

				wc_add_notice($message, 'error');
				$this->settings_admin_notice();

				wp_safe_redirect(wc_get_cart_url());
				return false;
			}

			$order = wc_get_order($order_id);
			if(!$order) {
				$message = sprintf(__('Order #%1$s not found as received from %2$s.', self::MOD_TEXT_DOMAIN), $order_id, $this->method_title);
				$this->log($message, WC_Log_Levels::ERROR);

				wc_add_notice($message, 'error');
				$this->settings_admin_notice();

				wp_safe_redirect(wc_get_cart_url());
				return false;
			}

			if($order->is_paid()) {
				WC()->cart->empty_cart();

				$message = sprintf(__('Order #%1$s paid successfully via %2$s.', self::MOD_TEXT_DOMAIN), $order_id, $this->method_title);
				$this->log($message, WC_Log_Levels::INFO);

				wc_add_notice($message, 'success');

				wp_safe_redirect($this->get_return_url($order));
				return true;
			} else {
				$message = sprintf(__('Order #%1$s payment failed via %2$s.', self::MOD_TEXT_DOMAIN), $order_id, $this->method_title);
				$this->log($message, WC_Log_Levels::ERROR);

				wc_add_notice($message, 'error');
				$this->settings_admin_notice();

				wp_safe_redirect($order->get_checkout_payment_url()); //wc_get_checkout_url()
				return false;
			}
		}

		public function check_response() {
			$this->log_request(__FUNCTION__);

			if($_SERVER['REQUEST_METHOD'] === 'GET') {
				$message = __('This Callback URL works and should not be called directly.', self::MOD_TEXT_DOMAIN);

				wc_add_notice($message, 'notice');

				wp_safe_redirect(wc_get_cart_url());
				return false;
			}

			return $this->process_response_data($_POST);
		}

		public function process_response_data($kbdata) {
			$this->log(sprintf('%1$s: %2$s', __FUNCTION__, self::print_var($kbdata)));

			try {
				$kinaBankGateway = $this->init_kb_client();
				$bankResponse = $kinaBankGateway->getResponseObject($kbdata);
				$check_result = $bankResponse->isValid();
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);
			}

			#region Extract bank response params
			$order_id  = KinaBankGateway::deNormalizeOrderId($bankResponse->{Response::ORDER});
			$amount    = $bankResponse->{Response::AMOUNT};
			$currency  = $bankResponse->{Response::CURRENCY};
			$approval  = $bankResponse->{Response::APPROVAL};
			$rrn       = $bankResponse->{Response::RRN};
			$intRef    = $bankResponse->{Response::INT_REF};
			$timeStamp = $bankResponse->{Response::TIMESTAMP};
			$text      = $bankResponse->{Response::TEXT};
			$bin       = $bankResponse->{Response::BIN};
			$card      = $bankResponse->{Response::CARD};

			$bankParams = array(
				'ORDER'     => $order_id,
				'AMOUNT'    => $amount,
				'CURRENCY'  => $currency,
				'TEXT'      => $text,
				'APPROVAL'  => $approval,
				'RRN'       => $rrn,
				'INT_REF'   => $intRef,
				'TIMESTAMP' => $timeStamp,
				'BIN'       => $bin,
				'CARD'      => $card
			);
			#endregion

			#region Validate order
			if(self::string_empty($order_id)) {
				$message = sprintf(__('Order ID not received from %1$s.', self::MOD_TEXT_DOMAIN), $this->method_title);
				$this->log($message, WC_Log_Levels::ERROR);
				return false;
			}

			$order = wc_get_order($order_id);
			if(!$order) {
				$message = sprintf(__('Order #%1$s not found as received from %2$s.', self::MOD_TEXT_DOMAIN), $order_id, $this->method_title);
				$this->log($message, WC_Log_Levels::ERROR);
				return false;
			}
			#endregion

			if($check_result && $this->check_transaction($order, $bankResponse)) {
				switch($bankResponse::TRX_TYPE) {
					case KinaBankGateway::TRX_TYPE_AUTHORIZATION:
						if($order->is_paid())
							return true; //Duplicate callback notification from the bank

						#region Update order payment metadata
						self::set_post_meta($order_id, self::MOD_TRANSACTION_TYPE, $this->transaction_type);

						foreach($bankParams as $key => $value)
							self::set_post_meta($order_id, strtolower(self::MOD_PREFIX . $key), $value);
						#endregion

						$message = sprintf(__('Payment authorized via %1$s: %2$s', self::MOD_TEXT_DOMAIN), $this->method_title, http_build_query($bankParams));
						$message = $this->get_order_message($message);
						$this->log($message, WC_Log_Levels::INFO);
						$order->add_order_note($message);

						$this->mark_order_paid($order, $intRef);

						switch($this->transaction_type) {
							case self::TRANSACTION_TYPE_CHARGE:
								$this->complete_transaction($order_id, $order);
								break;

							case self::TRANSACTION_TYPE_AUTHORIZATION:
								break;

							default:
								$this->log(sprintf('Unknown transaction type: %1$s Order ID: %2$s', $this->transaction_type, $order_id), WC_Log_Levels::ERROR);
								break;
						}

						return true;
						break;

					case KinaBankGateway::TRX_TYPE_COMPLETION:
						//Funds successfully transferred on bank side
						$message = sprintf(__('Payment completed via %1$s: %2$s', self::MOD_TEXT_DOMAIN), $this->method_title, http_build_query($bankParams));
						$message = $this->get_order_message($message);
						$this->log($message, WC_Log_Levels::INFO);
						$order->add_order_note($message);

						$this->mark_order_paid($order, $intRef);

						return true;
						break;

					case KinaBankGateway::TRX_TYPE_REVERSAL:
						//Reversal successfully applied on bank side
						$message = sprintf(__('Refund of %1$s %2$s via %3$s approved: %4$s', self::MOD_TEXT_DOMAIN), $amount, $currency, $this->method_title, http_build_query($bankParams));
						$message = $this->get_order_message($message);
						$this->log($message, WC_Log_Levels::INFO);
						$order->add_order_note($message);

						if($order->get_total() == $order->get_total_refunded())
							$this->mark_order_refunded($order);

						return true;
						break;

					default:
						$this->log(sprintf('Unknown bank response TRX_TYPE: %1$s Order ID: %2$s', $bankResponse::TRX_TYPE, $order_id), WC_Log_Levels::ERROR);
						break;
				}
			}

			$this->log(sprintf(__('Payment transaction check failed for order #%1$s.', self::MOD_TEXT_DOMAIN), $order_id), WC_Log_Levels::ERROR);
			$this->log(self::print_var($bankResponse), WC_Log_Levels::ERROR);

			$message = sprintf(__('%1$s payment transaction check failed: %2$s', self::MOD_TEXT_DOMAIN), $this->method_title, join('; ', $bankResponse->getErrors()) . ' ' . http_build_query($bankParams));
			$message = $this->get_order_message($message);
			$order->add_order_note($message);
			return false;
		}

		public function callback_data_process() {
			self::static_log_request(__FUNCTION__);

			//https://codex.wordpress.org/AJAX_in_Plugins
			//https://developer.wordpress.org/plugins/javascript/ajax/

			//https://developer.wordpress.org/reference/functions/check_ajax_referer/
			check_ajax_referer('callback_data_process');

			if(!self::is_wc_admin()) {
				//https://developer.wordpress.org/reference/functions/wp_die/
				$message = get_status_header_desc(WP_Http::FORBIDDEN);
				self::static_log($message, WC_Log_Levels::ERROR);
				wp_die($message, WP_Http::FORBIDDEN);
				return;
			}

			$callback_data = $_POST['callback_data'];
			if(!self::string_empty($callback_data)) {
				$kbdata = self::parse_response_post($callback_data);

				if(!empty($kbdata)) {
					$plugin = new self();
					if($plugin->is_available() && $plugin->enabled) {
						$response = $plugin->process_response_data($kbdata);

						if($response) {
							$message = sprintf(__('Processed successfully', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
							self::static_log($message, WC_Log_Levels::INFO);
							wp_send_json_success($response);
						} else {
							$message = sprintf(__('Processing error', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
							self::static_log($message, WC_Log_Levels::ERROR);
							wp_send_json_error($message);
						}
					} else {
						$message = sprintf(__('%1$s is not configured', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
						self::static_log($message, WC_Log_Levels::ERROR);
						wp_send_json_error($message);
					}
				} else {
					$message = sprintf(__('Invalid message', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
					self::static_log($message, WC_Log_Levels::ERROR);
					wp_send_json_error($message);
				}
			} else {
				$message = sprintf(__('Empty message', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
				self::static_log($message, WC_Log_Levels::ERROR);
				wp_send_json_error($message);
			}

			wp_die();
		}

		protected function get_order_message($message) {
			if($this->testmode)
				$message = 'TEST: ' . $message;

			return $message;
		}

		protected function validate_response_form($kbresponse) {
			$this->log(sprintf('%1$s: %2$s', __FUNCTION__, self::print_var($kbresponse)));

			if($kbresponse === false) {
				$error = error_get_last();
				if($error) {
					$message = $error['message'];

					$this->log($message, WC_Log_Levels::ERROR);
					$this->log(self::print_var($error));
				}

				return false;
			}

			return true;
		}

		protected function process_response_form($kbresponse) {
			$this->log(sprintf('%1$s: %2$s', __FUNCTION__, self::print_var($kbresponse)));

			$kbform = self::parse_response_form($kbresponse);
			if(empty($kbform))
				return false;

			return $this->process_response_data($kbform);
		}

		protected function parse_response_form($kbformhtml) {
			return self::parse_response_regex($kbformhtml, '/<input.+name="(\w+)".+value="(.*)"/i');
		}

		static function parse_response_post($kbpost) {
			return self::parse_response_regex($kbpost, '/^(\w+)=(.*)$/im');
		}

		static function parse_response_regex($kbresponse, $regex) {
			$matchResult = preg_match_all($regex, $kbresponse, $matches, PREG_SET_ORDER);
			if(empty($matchResult))
				return false;

			$kbdata = [];
			foreach($matches as $match)
				if(count($match) === 3)
					$kbdata[$match[1]] = $match[2];

			return $kbdata;
		}

		protected function mark_order_paid($order, $intRef) {
			if(!$order->is_paid())
				$order->payment_complete($intRef);
		}

		protected function mark_order_refunded($order) {
			$message = sprintf(__('Order fully refunded via %1$s.', self::MOD_TEXT_DOMAIN), $this->method_title);
			$message = $this->get_order_message($message);

			//Mark order as refunded if not already set
			if(!$order->has_status('refunded'))
				$order->update_status('refunded', $message);
			else
				$order->add_order_note($message);
		}

		protected function generate_form($order) {
			$order_id = $order->get_id();
			$order_total = $this->price_format($order->get_total());
			$order_currency = $order->get_currency();
			$order_description = $this->get_order_description($order);
			$order_email = $order->get_billing_email();
			$language = $this->get_language();

			$backRefUrl = add_query_arg(self::KB_ORDER_ID, urlencode($order_id), $this->get_redirect_url());

			//Request payment authorization - redirects to the banks page
			$kinaBankGateway = $this->init_kb_client();
			$kinaBankGateway->requestAuthorization(
				$order_id,
				$order_total,
				$backRefUrl,
				$order_currency,
				$order_description,
				$order_email,
				$language);
		}

		public function receipt_page($order_id) {
			try {
				$order = wc_get_order($order_id);
				$this->generate_form($order);
			} catch(Exception $ex) {
				$this->log($ex, WC_Log_Levels::ERROR);

				$message = sprintf(__('Payment initiation failed via %1$s.', self::MOD_TEXT_DOMAIN), $this->method_title);
				wc_add_notice($message, 'error');
				$this->settings_admin_notice();
			}
		}

		public function process_refund($order_id, $amount = null, $reason = '') {
			$order = wc_get_order($order_id);
			return $this->refund_transaction($order_id, $order, $amount);
		}

		protected function get_order_net_total($order) {
			//https://github.com/woocommerce/woocommerce/issues/17795
			//https://github.com/woocommerce/woocommerce/pull/18196
			$total_refunded = 0;
			if(method_exists(WC_Order_Refund::class, 'get_refunded_payment')) {
				$order_refunds = $order->get_refunds();
				foreach($order_refunds as $refund) {
					if($refund->get_refunded_payment())
						$total_refunded += $refund->get_amount();
				}
			}
			else
			{
				$total_refunded = $order->get_total_refunded();
			}

			$order_total = $order->get_total();
			return $order_total - $total_refunded;
		}

		/**
		 * Format prices
		 *
		 * @param  float|int $price
		 *
		 * @return float|int
		 */
		protected function price_format($price) {
			$decimals = 2;

			return number_format($price, $decimals, '.', '');
		}

		protected function get_order_description($order) {
			return sprintf(__($this->order_template, self::MOD_TEXT_DOMAIN),
				$order->get_id(),
				$this->get_order_items_summary($order)
			);
		}

		protected function get_order_items_summary($order) {
			$items = $order->get_items();
			$items_names = array_map(function($item) { return $item->get_name(); }, $items);

			return join(', ', $items_names);
		}

		protected function get_language() {
			$lang = get_locale();
			return substr($lang, 0, 2);
		}

		static function get_client_ip() {
			return WC_Geolocation::get_ip_address();
		}

		protected function get_callback_url() {
			//https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
			//return get_home_url(null, 'wc-api/' . get_class($this));

			//https://codex.wordpress.org/Function_Reference/home_url
			return add_query_arg('wc-api', get_class($this), home_url('/'));
		}

		protected function get_redirect_url() {
			//return get_home_url(null, 'wc-api/' . get_class($this) . '_redirect');

			return add_query_arg('wc-api', get_class($this) . '_redirect', home_url('/'));
		}

		static function get_logs_url() {
			return add_query_arg(
				array(
					'page'    => 'wc-status',
					'tab'     => 'logs',
					//'log_file' => ''
				),
				admin_url('admin.php')
			);
		}

		static function get_logs_path() {
			return WC_Log_Handler_File::get_log_file_path(self::MOD_ID);
		}

		static function get_settings_url() {
			return add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => self::MOD_ID
				),
				admin_url('admin.php')
			);
		}

		static function set_post_meta($post_id, $meta_key, $meta_value) {
			//https://developer.wordpress.org/reference/functions/add_post_meta/#comment-465
			if(!add_post_meta($post_id, $meta_key, $meta_value, true)) {
				update_post_meta($post_id, $meta_key, $meta_value);
			 }
		}

        protected function log($message, $level = WC_Log_Levels::DEBUG) {
			//https://woocommerce.wordpress.com/2017/01/26/improved-logging-in-woocommerce-2-7/
			//https://stackoverflow.com/questions/1423157/print-php-call-stack
			$this->logger->log($level, $message, $this->log_context);
		}

		static function static_log($message, $level = WC_Log_Levels::DEBUG) {
			$logger = wc_get_logger();
			$log_context = array('source' => self::MOD_ID);
			$logger->log($level, $message, $log_context);
		}

		protected function log_request($source) {
			$this->log(sprintf('%1$s: %2$s %3$s %4$s', $source, self::get_client_ip(), $_SERVER['REQUEST_METHOD'], self::print_var($_REQUEST)));
		}

		static function static_log_request($source) {
			self::static_log(sprintf('%1$s: %2$s %3$s %4$s', $source, self::get_client_ip(), $_SERVER['REQUEST_METHOD'], self::print_var($_REQUEST)));
		}

		static function print_var($var) {
			//https://docs.woocommerce.com/wc-apidocs/function-wc_print_r.html
			return wc_print_r($var, true);
		}

		protected static function string_empty($string) {
			return strlen($string) === 0;
		}

		#region Admin
		static function plugin_links($links) {
			$plugin_links = array(
				sprintf('<a href="%1$s">%2$s</a>', esc_url(self::get_settings_url()), __('Settings', self::MOD_TEXT_DOMAIN))
			);

			return array_merge($plugin_links, $links);
		}

		static function order_actions($actions) {
			global $theorder;
			if(!$theorder->is_paid() || $theorder->get_payment_method() !== self::MOD_ID) {
				return $actions;
			}

			$transaction_type = get_post_meta($theorder->get_id(), self::MOD_TRANSACTION_TYPE, true);
			if($transaction_type !== self::TRANSACTION_TYPE_AUTHORIZATION) {
				return $actions;
			}

			$actions['kinabank_complete_transaction'] = sprintf(__('Complete %1$s transaction', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
			//$actions['kinabank_reverse_transaction'] = sprintf(__('Reverse %1$s transaction', self::MOD_TEXT_DOMAIN), self::MOD_TITLE);
			return $actions;
		}

		static function action_complete_transaction($order) {
			$order_id = $order->get_id();

			$plugin = new self();
			return $plugin->complete_transaction($order_id, $order);
		}

		static function action_reverse_transaction($order) {
			$order_id = $order->get_id();

			$plugin = new self();
			return $plugin->refund_transaction($order_id, $order);
		}
		#endregion

		static function email_order_meta_fields($fields, $sent_to_admin, $order) {
			if(!$order->is_paid() || $order->get_payment_method() !== self::MOD_ID) {
				return $fields;
			}

			$fields[self::KB_RRN] = array(
				'label' => __('Retrieval Reference Number (RRN)'),
				'value' => $order->get_meta(strtolower(self::KB_RRN), true),
			);

			$fields[self::KB_APPROVAL] = array(
				'label' => __('Authorization code'),
				'value' => $order->get_meta(strtolower(self::KB_APPROVAL), true),
			);

			$fields[self::KB_CARD] = array(
				'label' => __('Card number'),
				'value' => $order->get_meta(strtolower(self::KB_CARD), true),
			);

			return $fields;
		}

		static function add_gateway($methods) {
			$methods[] = self::class;
			return $methods;
		}

		protected function get_store_address() {
			$address = array(
				'address_1' => WC()->countries->get_base_address(),
				'address_2' => WC()->countries->get_base_address_2(),
				'city'      => WC()->countries->get_base_city(),
				'state'     => WC()->countries->get_base_state(),
				'postcode'  => WC()->countries->get_base_postcode(),
				'country'   => WC()->countries->get_base_country()
			);

			return WC()->countries->get_formatted_address($address, ', ');
		}

		public static function is_wc_active() {
			//https://docs.woocommerce.com/document/query-whether-woocommerce-is-activated/
			return class_exists('WooCommerce');
		}

		public static function is_wc_admin() {
			//https://developer.wordpress.org/reference/functions/current_user_can/
			return current_user_can('manage_woocommerce');
		}
	}

	//Check if WooCommerce is active
	if(!WC_KinaBank::is_wc_active())
		return;

	//Add gateway to WooCommerce
	add_filter('woocommerce_payment_gateways', array(WC_KinaBank::class, 'add_gateway'));

	#region Admin init
	if(is_admin()) {
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(WC_KinaBank::class, 'plugin_links'));

		//Add WooCommerce order actions
		add_filter('woocommerce_order_actions', array(WC_KinaBank::class, 'order_actions'));
		add_action('woocommerce_order_action_kinabank_complete_transaction', array(WC_KinaBank::class, 'action_complete_transaction'));
		//add_action('woocommerce_order_action_kinabank_reverse_transaction', array(WC_KinaBank::class, 'action_reverse_transaction'));

		add_action('wp_ajax_kinabank_callback_data_process', array(WC_KinaBank::class, 'callback_data_process'));
	}
	#endregion

	//Add WooCommerce email templates actions
	add_filter('woocommerce_email_order_meta_fields', array(WC_KinaBank::class, 'email_order_meta_fields'), 10, 3);
}