<?php

/**
 * Plugin Name: Echezona Payments
 * Plugin URI: https://echezona.com
 * Description: Echezona Payment Gateway for WooCommerce
 * Version: 1.1.6
 * Author: Favour Max-Oti
 * Author URI: https://github.com/kellslte
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: echezona-payments
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ECZP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ECZP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECZP_VERSION', '1.1.6');
define('ECZP_MAIN_FILE', __FILE__);

// Load version manager
require_once ECZP_PLUGIN_DIR . 'includes/class-eczp-version-manager.php';

// Declare HPOS and Blocks compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Load text domain
function eczp_load_textdomain()
{
    load_plugin_textdomain('echezona-payments', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'eczp_load_textdomain');

// Initialize the plugin
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
                $payment_method_registry->register(new WC_ECZP_Blocks_Support());
            }
        );
    }
}
add_action('plugins_loaded', 'eczp_init', 20);

// Add the gateway to WooCommerce
function eczp_add_gateway($methods)
{
    $methods[] = 'WC_ECZP_Gateway';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'eczp_add_gateway');

// Display WooCommerce missing notice
function eczp_woocommerce_missing_notice()
{
?>
    <div class="error">
        <p><?php _e('Echezona Payments requires WooCommerce to be installed and active.', 'echezona-payments'); ?></p>
    </div>
<?php
}

// Add settings link on plugin page
function eczp_add_settings_link($links)
{
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=echezona_payment')) . '">' . __('Settings', 'echezona-payments') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'eczp_add_settings_link');

// Activation hook
register_activation_hook(__FILE__, 'eczp_activate');
function eczp_activate()
{
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and active.', 'echezona-payments'));
    }
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'eczp_deactivate');
function eczp_deactivate()
{
    flush_rewrite_rules();
}

// Enqueue styles
function eczp_enqueue_styles()
{
    if (is_checkout() || is_wc_endpoint_url('order-pay')) {
        wp_enqueue_style('echezona-styles', ECZP_PLUGIN_URL . 'assets/css/style.css', array(), ECZP_VERSION);
    }
}
add_action('wp_enqueue_scripts', 'eczp_enqueue_styles');
