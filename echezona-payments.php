<?php

/**
 * Plugin Name: Echezona Payment Gateway for WooCommerce
 * Plugin URI: https://echezona.com
 * Description: Echezona Payment Gateway for WooCommerce
 * Version: 1.1.13
 * Author: Favour Max-Oti
 * Author URI: https://github.com/kellslte
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: echezona-payment-gateway-for-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.1
 *
 * @package Echezona_Payments
 */

if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_VERSION', '1.1.13');
define('ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_MAIN_FILE', __FILE__);

// Load version manager
require_once ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-echepay-gateway-for-woocommerce-version-manager.php';

/**
 * Declare HPOS and Blocks compatibility
 */
add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
  }
});

/**
 * Initialize the plugin
 */
function echepay_gateway_for_woocommerce_init()
{
  if (!class_exists('WC_Payment_Gateway')) {
    add_action('admin_notices', 'echepay_gateway_for_woocommerce_woocommerce_missing_notice');
    return;
  }

  require_once ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-wc-echepay-gateway-for-woocommerce-gateway.php';

  // Initialize Blocks support
  if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    require_once ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-wc-echepay-gateway-for-woocommerce-blocks-support.php';

    // Register the payment method type
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
        $payment_method_registry->register(
          new WC_ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_Blocks_Support()
        );
      }
    );
  }
}
add_action('plugins_loaded', 'echepay_gateway_for_woocommerce_init', 20);

/**
 * Add the gateway to WooCommerce
 *
 * @param array $methods Array of payment methods.
 * @return array
 */
function echepay_gateway_for_woocommerce_add_gateway($methods)
{
  $methods[] = 'WC_ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_Gateway';
  return $methods;
}
add_filter('woocommerce_payment_gateways', 'echepay_gateway_for_woocommerce_add_gateway');

/**
 * Display WooCommerce missing notice
 */
function echepay_gateway_for_woocommerce_woocommerce_missing_notice()
{
  ?>
  <div class="error">
    <p>
      <?php esc_html_e('Echezona Payment Gateway for WooCommerce requires WooCommerce to be installed and active.', 'echezona-payment-gateway-for-woocommerce'); ?>
    </p>
  </div>
  <?php
}
