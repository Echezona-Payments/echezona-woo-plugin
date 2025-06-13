<?php

/**
 * Plugin Name: Echezona Woo Payments
 * Plugin URI: https://echezona.com
 * Description: Echezona Payment Gateway for WooCommerce
 * Version: 1.1.12
 * Author: Favour Max-Oti
 * Author URI: https://github.com/kellslte
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: echezona-woo-payments
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.1
 *
 * @package Echezona_Payments
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ECZP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ECZP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECZP_VERSION', '1.1.12');
define('ECZP_MAIN_FILE', __FILE__);

// Load version manager
require_once ECZP_PLUGIN_DIR . 'includes/class-eczp-version-manager.php';

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
 * Load plugin text domain
 */
function eczp_load_textdomain()
{
    load_plugin_textdomain('echezona-woo-payments', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'eczp_load_textdomain');

/**
 * Initialize the plugin
 */
function eczp_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'eczp_woocommerce_missing_notice');
        return;
    }

    require_once ECZP_PLUGIN_DIR . 'includes/class-wc-eczp-gateway.php';

    // Initialize Blocks support
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once ECZP_PLUGIN_DIR . 'includes/class-wc-eczp-blocks-support.php';

        // Register the payment method type
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(
                    new WC_ECZP_Blocks_Support()
                );
            }
        );
    }
}
add_action('plugins_loaded', 'eczp_init', 20);

/**
 * Add the gateway to WooCommerce
 *
 * @param array $methods Array of payment methods.
 * @return array
 */
function eczp_add_gateway($methods)
{
    $methods[] = 'WC_ECZP_Gateway';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'eczp_add_gateway');

/**
 * Display WooCommerce missing notice
 */
function eczp_woocommerce_missing_notice()
{
?>
    <div class="error">
        <p><?php esc_html_e('Echezona Payments requires WooCommerce to be installed and active.', 'echezona-woo-payments'); ?></p>
    </div>
<?php
}
