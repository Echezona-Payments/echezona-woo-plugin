<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Echezona Blocks Support
 */
class WC_ECZP_Blocks_Support implements IntegrationInterface
{
    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    private $name = 'echezona_payment';

    /**
     * Initializes the class.
     */
    public function __construct()
    {
        add_action('init', array($this, 'register_blocks_support'));
    }

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * Initialize the integration.
     */
    public function initialize()
    {
        $this->register_blocks_support();
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return array
     */
    public function get_script_handles()
    {
        return array('echezona-payment-blocks');
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return array
     */
    public function get_editor_script_handles()
    {
        return array('echezona-payment-blocks');
    }

    /**
     * Returns an array of script handles to enqueue in the admin context.
     *
     * @return array
     */
    public function get_payment_method_script_handles_for_admin()
    {
        return array('echezona-payment-blocks');
    }

    /**
     * Returns an array of script handles to enqueue for the payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        return array('echezona-payment-blocks');
    }

    /**
     * Returns an array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data()
    {
        return array(
            'name' => $this->name,
            'title' => __('Echezona Payment', 'echezona-payments'),
            'description' => __('Pay securely using Echezona Payment Gateway', 'echezona-payments'),
            'supports' => array('products'),
            'icon' => ECZP_PLUGIN_URL . 'assets/images/logo.png',
            'testmode' => 'yes' === get_option('woocommerce_echezona_payment_testmode', 'yes'),
        );
    }

    /**
     * Returns an array of key, value pairs of data made available to the payment method on the client side.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return array(
            'name' => $this->name,
            'title' => __('Echezona Payment', 'echezona-payments'),
            'description' => __('Pay securely using Echezona Payment Gateway', 'echezona-payments'),
            'supports' => array('products'),
            'icon' => ECZP_PLUGIN_URL . 'assets/images/logo.png',
            'testmode' => 'yes' === get_option('woocommerce_echezona_payment_testmode', 'yes'),
        );
    }

    /**
     * Whether the integration is active or not.
     *
     * @return boolean
     */
    public function is_active()
    {
        return true;
    }

    /**
     * Register blocks support.
     */
    public function register_blocks_support()
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        require_once dirname(__FILE__) . '/class-wc-eczp-blocks-payment-method.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ($registry) {
                $registry->register(new WC_ECZP_Blocks_Payment_Method());
            }
        );
    }

}
