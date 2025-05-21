<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Echezona Blocks Payment Method
 */
class WC_ECZP_Blocks_Payment_Method extends AbstractPaymentMethodType
{
    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'echezona_payment';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_echezona_payment_settings', array());
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $asset_path = ECZP_PLUGIN_DIR . 'build/index.asset.php';
        $version = ECZP_VERSION;

        if (file_exists($asset_path)) {
            $asset = require $asset_path;
            $version = $asset['version'];
        }

        wp_register_script(
            'echezona-blocks-payment-method',
            ECZP_PLUGIN_URL . 'build/index.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-i18n'),
            $version,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('echezona-blocks-payment-method', 'echezona-payments');
        }

        return array('echezona-blocks-payment-method');
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return array(
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => array('products'),
            'icon' => ECZP_PLUGIN_URL . 'assets/images/logo.png',
            'testmode' => 'yes' === $this->get_setting('testmode'),
        );
    }
}
