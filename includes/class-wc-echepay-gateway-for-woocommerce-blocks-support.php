<?php

/**
 * Echezona Payment Gateway Blocks Support
 *
 * Handles the integration of Echezona Payment Gateway with WooCommerce Blocks.
 *
 * @package Echezona_Payments
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
  exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

/**
 * WC_ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_Blocks_Support Class
 *
 * Extends the WooCommerce Blocks payment method type to provide Echezona payment support.
 *
 * @since 1.0.0
 */
final class WC_ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_Blocks_Support extends AbstractPaymentMethodType
{
  /**
   * Payment method name/id/slug.
   *
   * @var string
   */
  protected $name = 'echezona_payment';

  /**
   * Log error message
   *
   * @param string $message Error message
   * @param mixed $data Additional data to log
   */
  private function log_error($message, $data = null)
  {
    $log_message = '[Echezona Payment] ' . $message;
    if ($data !== null) {
      $log_message .= ' Data: ' . print_r($data, true);
    }
    error_log($log_message);
  }

  /**
   * Initializes the payment method type.
   */
  public function initialize()
  {
    try {
      $this->log_error('Initializing payment method');
      $this->settings = get_option('woocommerce_echezona_payment_settings', array());
      $this->log_error('Settings loaded', $this->settings);
      add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'failed_payment_notice'), 8, 2);
    } catch (Exception $e) {
      $this->log_error('Error initializing payment method: ' . $e->getMessage());
    }
  }

  /**
   * Returns if this payment method should be active. If false, the scripts will not be enqueued.
   *
   * @return boolean
   */
  public function is_active()
  {
    try {
      $payment_gateways_class = WC()->payment_gateways();
      $payment_gateways = $payment_gateways_class->payment_gateways();
      $is_active = isset($payment_gateways['echezona_payment']) && $payment_gateways['echezona_payment']->is_available();
      $this->log_error('Payment method active status: ' . ($is_active ? 'true' : 'false'));
      return $is_active;
    } catch (Exception $e) {
      $this->log_error('Error checking payment method active status: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Returns an array of scripts/handles to be registered for this payment method.
   *
   * @return array
   */
  public function get_payment_method_script_handles()
  {
    try {
      $script_asset_path = ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_DIR . 'build/blocks.asset.php';
      $this->log_error('Loading script asset from: ' . $script_asset_path);

      $script_asset = file_exists($script_asset_path)
        ? require $script_asset_path
        : array(
          'dependencies' => array(),
          'version' => ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_VERSION,
        );

      $script_url = ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_URL . 'build/blocks.js';
      $this->log_error('Script URL: ' . $script_url);

      wp_register_script(
        'wc-echezona-blocks',
        $script_url,
        $script_asset['dependencies'],
        $script_asset['version'],
        true
      );

      if (function_exists('wp_set_script_translations')) {
        wp_set_script_translations('wc-echezona-blocks', 'echezona-payments');
      }

      return array('wc-echezona-blocks');
    } catch (Exception $e) {
      $this->log_error('Error registering payment method scripts: ' . $e->getMessage());
      return array();
    }
  }

  /**
   * Returns an array of key=>value pairs of data made available to the payment methods script.
   *
   * @return array
   */
  public function get_payment_method_data()
  {
    try {
      $payment_gateways_class = WC()->payment_gateways();
      $payment_gateways = $payment_gateways_class->payment_gateways();
      $gateway = $payment_gateways['echezona_payment'];

      $data = array(
        'title' => $this->get_setting('title'),
        'description' => $this->get_setting('description'),
        'supports' => array_filter($gateway->supports, array($gateway, 'supports')),
        'allow_saved_cards' => $gateway->saved_cards && is_user_logged_in(),
        'testmode' => $gateway->testmode,
        'api_key' => $gateway->api_key,
        'logo_url' => ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_URL . 'assets/images/logo.png',
      );

      $this->log_error('Payment method data', $data);
      return $data;
    } catch (Exception $e) {
      $this->log_error('Error getting payment method data: ' . $e->getMessage());
      return array();
    }
  }

  /**
   * Add failed payment notice to the payment details.
   *
   * @param PaymentContext $context Holds context for the payment.
   * @param PaymentResult $result Result object for the payment.
   */
  public function failed_payment_notice(PaymentContext $context, PaymentResult &$result)
  {
    if ('echezona_payment' === $context->payment_method) {
      $this->log_error('Processing failed payment notice');
      add_action(
        'wc_gateway_echezona_process_payment_error',
        function ($failed_notice) use (&$result) {
          $this->log_error('Payment failed: ' . $failed_notice);
          $payment_details = $result->payment_details;
          $payment_details['errorMessage'] = wp_strip_all_tags($failed_notice);
          $result->set_payment_details($payment_details);
        }
      );
    }
  }
}
