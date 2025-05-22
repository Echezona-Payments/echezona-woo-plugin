<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class WC_ECZP_Gateway extends WC_Payment_Gateway
{
    /**
     * Supported currencies
     *
     * @var array
     */
    private $supported_currencies = array(
        'NGN', // Nigerian Naira
        'USD', // US Dollar
        'GBP', // British Pound
        'EUR', // Euro
        'GHS', // Ghanaian Cedi
        'KES', // Kenyan Shilling
        'UGX', // Ugandan Shilling
        'ZAR', // South African Rand
    );

    /**
     * Is test mode active?
     *
     * @var bool
     */
    public $testmode;

    /**
     * Should orders be marked as complete after payment?
     * 
     * @var bool
     */
    public $autocomplete_order;

    /**
     * Echezona API key.
     *
     * @var string
     */
    public $api_key;

    /**
     * Echezona Base URL
     * @var string
     */
    public $base_url = 'https://api.echezona.com/api';

    /**
     * @var string
     */
    private $callback_url;

    public function __construct()
    {
        $this->id = 'echezona_payment';
        $this->icon = ECZP_PLUGIN_URL . 'assets/images/logo.png';
        $this->has_fields = true;
        $this->method_title = __('Echezona Payment Gateway', 'echezona-payments');
        $this->method_description = __('Accept payments via Echezona Payment Gateway', 'echezona-payments');
        $this->supports = array(
            'products',
            'refunds'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title', __('Echezona Payment', 'echezona-payments'));
        $this->description = $this->get_option('description', __('Pay securely using Echezona Payment Gateway', 'echezona-payments'));
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->api_key = $this->get_option('api_key');
        $this->callback_url = $this->get_option('callback_url');
        $this->autocomplete_order = 'yes' === $this->get_option('autocomplete_order');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_echezona_payment_callback', array($this, 'check_eczp_response'));
        add_action('woocommerce_api_echezona_payment_webhook', array($this, 'handle_webhook'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Add test mode notice
        if ($this->testmode) {
            add_action('admin_notices', array($this, 'test_mode_notice'));
        }

        // Get setting values
        // $this->api_key = $this->testmode ? $this->test_api_key : $this->api_key;

        // Debug log
        error_log('Echezona Gateway initialized with ID: ' . $this->id);
        error_log('Gateway enabled: ' . ($this->enabled ? 'yes' : 'no'));
        error_log('Test mode: ' . ($this->testmode ? 'yes' : 'no'));
        error_log('API key set: ' . (!empty($this->api_key) ? 'yes' : 'no'));
    }

    /**
     * Check if this gateway is enabled and available in the user's country.
     */
    public function is_valid_for_use()
    {
        $currency = get_woocommerce_currency();
        $is_valid = in_array($currency, $this->supported_currencies);
        error_log('Currency ' . $currency . ' is ' . ($is_valid ? 'valid' : 'invalid') . ' for Echezona Gateway');
        return $is_valid;
    }

    /**
     * Check if this gateway is available for use.
     */
    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            error_log('Echezona Gateway is disabled');
            return false;
        }

        if (!$this->testmode && empty($this->api_key)) {
            error_log('Echezona Gateway: API key is missing');
            return false;
        }

        if ($this->testmode && empty($this->api_key)) {
            error_log('Echezona Gateway: API key is missing');
            return false;
        }

        if (!in_array(get_woocommerce_currency(), $this->supported_currencies)) {
            error_log('Echezona Gateway: Currency ' . get_woocommerce_currency() . ' is not supported');
            return false;
        }

        $is_available = parent::is_available();
        error_log('Echezona Gateway is ' . ($is_available ? 'available' : 'not available'));
        return $is_available;
    }

    public function payment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if (!$this->is_available()) {
            return;
        }

        wp_enqueue_script('echezona', ECZP_PLUGIN_URL . 'assets/js/payment.js', array('jquery'), ECZP_VERSION, true);
        wp_localize_script('echezona', 'echezona_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('echezona-payment-nonce'),
            'error_message' => __('An error occurred while processing your payment. Please try again.', 'echezona-payments'),
            'testmode' => $this->testmode,
            'api_key' => $this->api_key,
        ));
    }

    public function test_mode_notice()
    {
        if (current_user_can('manage_options')) {
            echo '<div class="error"><p>' .
                sprintf(
                    __('Echezona Payment Gateway is in test mode. Click %s to disable it when you want to start accepting live payments.', 'echezona-payments'),
                    '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=echezona_payment')) . '">' . __('here', 'echezona-payments') . '</a>'
                ) .
                '</p></div>';
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'echezona-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Echezona Payment Gateway', 'echezona-payments'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'echezona-payments'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'echezona-payments'),
                'default' => __('Echezona Payment', 'echezona-payments'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'echezona-payments'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'echezona-payments'),
                'default' => __('Pay securely using Echezona Payment Gateway', 'echezona-payments'),
            ),
            'testmode' => array(
                'title' => __('Test mode', 'echezona-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'echezona-payments'),
                'default' => 'yes',
                'description' => __('Place the payment gateway in test mode.', 'echezona-payments'),
            ),
            'api_key' => array(
                'title' => __('API Key', 'echezona-payments'),
                'type' => 'text',
                'description' => __('Enter your API key', 'echezona-payments'),
                'default' => '',
                'desc_tip' => true,
            ),
            'callback_url' => array(
                'title' => __('Callback URL', 'echezona-payments'),
                'type' => 'text',
                'description' => __('Enter the callback URL you would like to use', 'echezona-payments'),
                'default' => '',
                'desc_tip' => true,
            ),
            'autocomplete_order' => array(
                'title' => __('Autocomplete Order', 'echezona-payments'),
                'type' => 'checkbox',
                'label' => __('Automatically complete order after successful payment', 'echezona-payments'),
                'default' => 'yes',
                'description' => __('If enabled, orders will be automatically marked as completed after successful payment.', 'echezona-payments'),
            ),
        );
    }

    /**
     * Output payment fields.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /**
     * Process the payment.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        try {
            // Get the payment URL from Echezona
            $payment_url = $this->get_payment_url($order);

            if (!$payment_url) {
                throw new Exception(__('Could not get payment URL from Echezona.', 'echezona-payments'));
            }

            // Store the payment URL in the order meta
            $order->update_meta_data('_echezona_payment_url', $payment_url);
            $order->save();

            // Return success with redirect
            return array(
                'result' => 'success',
                'redirect' => $payment_url,
            );
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'failure',
                'messages' => $e->getMessage(),
            );
        }
    }

    /**
     * Generate a unique transaction ID for the order.
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    private function generate_transaction_id($order)
    {
        $order_id = $order->get_id();
        $timestamp = time();
        $random = wp_generate_password(6, false);

        // Format: ECZ-{order_id}-{timestamp}-{random}
        $transaction_id = sprintf('ECZ-%d-%d-%s', $order_id, $timestamp, $random);

        // Store the transaction ID in order meta
        $order->update_meta_data('_echezona_transaction_id', $transaction_id);
        $order->save();

        return $transaction_id;
    }

    /**
     * Get payment URL from Echezona.
     *
     * @param WC_Order $order Order object.
     * @return string|false
     */
    private function get_payment_url($order)
    {
        // Generate unique transaction ID
        $transaction_id = $this->generate_transaction_id($order);

        // Log the API request for debugging
        error_log('Echezona API Request - URL: ' . $this->base_url . '/payment/initialize');
        error_log('Echezona API Request - Order ID: ' . $order->get_id());
        error_log('Echezona API Request - Transaction ID: ' . $transaction_id);
        error_log('Echezona API Request - Amount: ' . $order->get_total());

        $request_body = array(
            'amount' => (string)$order->get_total(),
            'currency' => $order->get_currency(),
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'callbackUrl' => $this->get_callback_url($order),
            'transactionId' => $transaction_id,
            'mode' => $this->testmode ? 'Test' : 'Live',
            'metadata' => [
                [
                    'name' => 'Order Id',
                    'value' => (string)$order->get_id(),
                ],
                [
                    'name' => 'Customer Id',
                    'value' => (string)$order->get_customer_id(),
                ],
                [
                    'name' => 'Customer Name',
                    'value' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                ],
                [
                    'name' => 'Customer Email',
                    'value' => $order->get_billing_email(),
                ],
                [
                    'name' => 'Customer Address',
                    'value' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                ],
                [
                    'name' => 'Customer City',
                    'value' => $order->get_billing_city(),
                ],
            ],
            'productId' => (string)$order->get_id(),
            'producDescription' => 'Payment with Echezona payment gateway for WooCommerce order',
            'applyConviniencyCharge' => false
        );

        error_log('Echezona API Request - Body: ' . json_encode($request_body));

        $response = wp_remote_post(
            $this->base_url . '/Payments/Initialize',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($request_body),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            error_log('Echezona API Error: ' . $response->get_error_message());
            throw new Exception(__('Could not connect to Echezona payment gateway. Please try again.', 'echezona-payments'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('Echezona API Response Code: ' . $response_code);
        error_log('Echezona API Response Body: ' . $response_body);

        if ($response_code !== 200) {
            throw new Exception(sprintf(
                __('Echezona payment gateway error: %s', 'echezona-payments'),
                $response_body
            ));
        }

        $body = json_decode($response_body, true);

        if (!isset($body['data']['paymentUrl'])) {
            $error_message = isset($body['message']) ? $body['message'] : __('Unknown error occurred', 'echezona-payments');
            throw new Exception(sprintf(
                __('Could not get payment URL from Echezona: %s', 'echezona-payments'),
                $error_message
            ));
        }

        // store the access code on the order details
        $order->update_meta_data('_echezona_access_code', $body['data']['accessCode']);
        $order->save();

        $prod_url = $body['data']['paymentUrl'] . "?iframe=1";
        // $staging_url = "https://checkout.staging.echezona.com/".$body['data']['accessCode']."?iframe=1";

        return $prod_url;
    }

    private function get_callback_url($order)
    {
        return add_query_arg(array(
            'wc-api' => 'echezona_payment_callback',
            'order_id' => $order->get_id()
        ), home_url('/'));
    }

    public function handle_webhook()
    {
        $payload = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_ECHEZONA_SIGNATURE']) ? $_SERVER['HTTP_X_ECHEZONA_SIGNATURE'] : '';

        // Verify webhook signature
        if (!$this->verify_webhook_signature($payload, $signature)) {
            status_header(401);
            exit('Invalid signature');
        }

        $data = json_decode($payload, true);

        if (!isset($data['reference'])) {
            status_header(400);
            exit('Missing reference');
        }

        $reference = sanitize_text_field($data['reference']);
        $reference_parts = explode('_', $reference);

        if (count($reference_parts) < 2) {
            status_header(400);
            exit('Invalid reference format');
        }

        $order_id = $reference_parts[1];
        $order = wc_get_order($order_id);

        if (!$order) {
            status_header(404);
            exit('Order not found');
        }

        if ($data['status'] === 'success') {
            $order->payment_complete();
            if ($this->autocomplete_order) {
                $order->update_status('completed');
            }
            $order->add_order_note(sprintf(__('Payment verified via webhook. Reference: %s', 'echezona-payments'), $reference));
        } else {
            $order->update_status('failed', sprintf(__('Payment failed via webhook. Reference: %s', 'echezona-payments'), $reference));
        }

        status_header(200);
        exit('Webhook processed');
    }

    private function verify_webhook_signature($payload, $signature)
    {
        $expected_signature = hash_hmac('sha256', $payload, $this->api_key);
        return hash_equals($expected_signature, $signature);
    }

    public function check_eczp_response()
    {
        $reference = isset($_GET['orderReference']) ? sanitize_text_field($_GET['orderReference']) : '';
        $responseCode = isset($_GET['responseCode']) ? sanitize_text_field($_GET['responseCode']) : '';

        if ($responseCode !== '00') {
            wp_die(__('Invalid callback parameters', 'echezona-payments'));
        }

        $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : '';;
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die(__('Order not found', 'echezona-payments'));
        }

        //  fetch the payment information
        $access_code = $order->get_meta('_echezona_access_code');
        $payment_info_response = wp_remote_get($this->base_url . "/Payments/GetPaymentInfo/" . $access_code);
        $payment_info_response_body = json_decode(wp_remote_retrieve_body($payment_info_response), true);

        // get the payment validation token
        $payment_validation_token = $payment_info_response_body['data']['token'];

        // extract transaction id from order meta
        $transaction_id = $order->get_meta('_echezona_transaction_id');

        $response = wp_remote_post($this->base_url . '/Payments/VerifyPayment?caller=merchant', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $payment_validation_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode([
                'transactionId' => (string)$transaction_id
            ]),
        ));

        if (is_wp_error($response)) {
            wp_die($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['responseCode']) && $body['responseCode'] === '04') {
            // Payment successful
            $order->payment_complete();
            $order->add_order_note(sprintf(__('Payment completed via Echezona. Reference: %s', 'echezona-payments'), $transaction_id));
            if($this->autocomplete_order === 'yes') $order->update_status('completed');
            wp_redirect($this->get_return_url($order));
            exit;
        } else {
            // Payment failed
            $order->update_status('failed', sprintf(__('Payment failed via Echezona. Reference: %s', 'echezona-payments'), $transaction_id));
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }
    }

    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);
        echo '<p>' . __('Thank you for your order. Please click the button below to pay with Echezona.', 'echezona-payments') . '</p>';
        echo '<p><a class="button" href="' . esc_url($order->get_checkout_payment_url(true)) . '">' . __('Pay Now', 'echezona-payments') . '</a></p>';
    }
}
