/**
* Echezona Payment Gateway for WooCommerce
*
* @package Echezona_Payments
* @since 1.0.0
*/

if (!defined('ABSPATH')) {
exit;
}

if (!class_exists('WC_Payment_Gateway')) {
return;
}

/**
* WC_ECZP_Gateway Class
*
* Handles the Echezona payment gateway integration with WooCommerce.
* Extends WC_Payment_Gateway_CC to provide credit card payment functionality.
*
* @since 1.0.0
*/
class WC_ECZP_Gateway extends WC_Payment_Gateway_CC
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
*
* @var string
*/
public $base_url = 'https://api.echezona.com/api';

/**
* Callback URL for payment notifications
*
* @var string
*/
private $callback_url;

/**
* Should we save customer cards?
*
* @var bool
*/
public $saved_cards;

/**
* Should split payment be enabled.
*
* @var bool
*/
public $split_payment;

/**
* Should custom metadata be enabled?
*
* @var bool
*/
public $custom_metadata;

/**
* Payment channels.
*
* @var array
*/
public $payment_channels = array();

/**
* Logger instance
*
* @var WC_Logger
*/
private $logger;

public function __construct()
{
$this->id = 'echezona_payment';
$this->icon = ECZP_PLUGIN_URL . 'assets/images/logo.png';
$this->has_fields = true;
$this->method_title = __('Echezona Payment Gateway', 'echezona-payments');
$this->method_description = __('Accept payments via Echezona Payment Gateway', 'echezona-payments');
$this->supports = array(
'products',
'refunds',
'tokenization',
'subscriptions',
'multiple_subscriptions'
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
$this->saved_cards = 'yes' === $this->get_option('saved_cards');
$this->split_payment = 'yes' === $this->get_option('split_payment');
$this->custom_metadata = 'yes' === $this->get_option('custom_metadata');

// Actions
add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
add_action('woocommerce_api_echezona_payment_callback', array($this, 'check_eczp_response'));
add_action('woocommerce_api_echezona_payment_webhook', array($this, 'handle_webhook'));
add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'process_subscription_payment'), 10, 2);

// Add test mode notice
if ($this->testmode) {
add_action('admin_notices', array($this, 'test_mode_notice'));
}

// Debug log
$this->log('Echezona Gateway initialized with ID: ' . $this->id);
$this->log('Gateway enabled: ' . ($this->enabled ? 'yes' : 'no'));
$this->log('Test mode: ' . ($this->testmode ? 'yes' : 'no'));
$this->log('API key set: ' . (!empty($this->api_key) ? 'yes' : 'no'));
}

/**
* Log messages to WooCommerce logger
*/
private function log($message)
{
if ($this->testmode) {
if (empty($this->logger)) {
$this->logger = wc_get_logger();
}
$this->logger->debug($message, array('source' => 'echezona-payments'));
}
}

/**
* Check if this gateway is enabled and available in the user's country.
*/
public function is_valid_for_use()
{
$currency = get_woocommerce_currency();
$is_valid = in_array($currency, $this->supported_currencies);
$this->log('Currency ' . $currency . ' is ' . ($is_valid ? 'valid' : 'invalid') . ' for Echezona Gateway');
return $is_valid;
}

/**
* Check if this gateway is available for use.
*/
public function is_available()
{
if ('yes' !== $this->enabled) {
$this->log('Echezona Gateway is disabled');
return false;
}

if (!$this->testmode && empty($this->api_key)) {
$this->log('Echezona Gateway: API key is missing');
return false;
}

if ($this->testmode && empty($this->api_key)) {
$this->log('Echezona Gateway: API key is missing');
return false;
}

if (!in_array(get_woocommerce_currency(), $this->supported_currencies)) {
$this->log('Echezona Gateway: Currency ' . get_woocommerce_currency() . ' is not supported');
return false;
}

$is_available = parent::is_available();
$this->log('Echezona Gateway is ' . ($is_available ? 'available' : 'not available'));
return $is_available;
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
'desc_tip' => true,
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
'description' => __('Enter your Echezona API Key', 'echezona-payments'),
'default' => '',
'desc_tip' => true,
),
'saved_cards' => array(
'title' => __('Saved Cards', 'echezona-payments'),
'type' => 'checkbox',
'label' => __('Enable Payment via Saved Cards', 'echezona-payments'),
'default' => 'no',
'description' => __('If enabled, users will be able to pay with a saved card during checkout.', 'echezona-payments'),
),
'split_payment' => array(
'title' => __('Split Payment', 'echezona-payments'),
'type' => 'checkbox',
'label' => __('Enable Split Payment', 'echezona-payments'),
'default' => 'no',
'description' => __('If enabled, payments can be split between multiple accounts.', 'echezona-payments'),
),
'custom_metadata' => array(
'title' => __('Custom Metadata', 'echezona-payments'),
'type' => 'checkbox',
'label' => __('Enable Custom Metadata', 'echezona-payments'),
'default' => 'no',
'description' => __('If enabled, additional order information will be sent to Echezona.', 'echezona-payments'),
),
'autocomplete_order' => array(
'title' => __('Autocomplete Order', 'echezona-payments'),
'type' => 'checkbox',
'label' => __('Autocomplete Order After Payment', 'echezona-payments'),
'default' => 'yes',
'description' => __('If enabled, the order will be marked as completed after successful payment.', 'echezona-payments'),
),
);
}

public function payment_fields()
{
if ($this->saved_cards && is_user_logged_in()) {
$tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
if (!empty($tokens)) {
echo '<div class="echezona-saved-cards">';
    echo '<h3>' . __('Saved Cards', 'echezona-payments') . '</h3>';
    foreach ($tokens as $token) {
    echo '<div class="echezona-saved-card">';
        echo '<input type="radio" name="echezona_token" value="' . esc_attr($token->get_id()) . '" />';
        echo '<label>' . esc_html($token->get_display_name()) . '</label>';
        echo '</div>';
    }
    echo '<div class="echezona-saved-card">';
        echo '<input type="radio" name="echezona_token" value="new" checked />';
        echo '<label>' . __('Use a new card', 'echezona-payments') . '</label>';
        echo '</div>';
    echo '</div>';
}
}

parent::payment_fields();
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
'saved_cards' => $this->saved_cards,
));
}

public function test_mode_notice()
{
if (current_user_can('manage_options')) {
echo '<div class="error">
    <p>' .
        sprintf(
        __('Echezona Payment Gateway is in test mode. Click %s to disable it when you want to start accepting live payments.', 'echezona-payments'),
        '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=echezona_payment')) . '">' . __('here', 'echezona-payments') . '</a>'
        ) .
        '</p>
</div>';
}
}

/**
* Process the payment and return the result.
*
* @param int $order_id Order ID.
* @return array
*/
public function process_payment($order_id) {
$order = wc_get_order($order_id);

if (!$order) {
return array(
'result' => 'fail',
'redirect' => '',
'messages' => __('Invalid order.', 'echezona-payments')
);
}

if ($this->saved_cards && isset($_POST['echezona_token']) && 'new' !== $_POST['echezona_token']) {
return $this->process_token_payment(sanitize_text_field($_POST['echezona_token']), $order_id);
}

try {
$response = $this->get_payment_url($order);

if (!isset($response['payment_url']) || empty($response['payment_url'])) {
throw new Exception(__('Could not generate payment URL', 'echezona-payments'));
}

// Store transaction ID in order meta
$order->update_meta_data('_echezona_transaction_id', $response['transaction_id']);
$order->save();

return array(
'result' => 'success',
'redirect' => $response['payment_url']
);
} catch (Exception $e) {
$this->log('Payment processing error: ' . $e->getMessage());
wc_add_notice($e->getMessage(), 'error');
return array(
'result' => 'fail',
'redirect' => '',
'messages' => $e->getMessage()
);
}
}

/**
* Process payment with a saved token.
*
* @param string $token_id Token ID.
* @param int $order_id Order ID.
* @return array
* @throws Exception If payment fails.
*/
public function process_token_payment($token_id, $order_id) {
$token = WC_Payment_Tokens::get($token_id);
$order = wc_get_order($order_id);

if (!$token || $token->get_user_id() !== get_current_user_id()) {
throw new Exception(__('Invalid payment token', 'echezona-payments'));
}

try {
// Process payment with saved token
$response = $this->process_payment_with_token($token, $order);

if ($response['status'] === 'success') {
$order->payment_complete($response['transaction_id']);
$order->add_order_note(
sprintf(
__('Payment completed via saved card (Token ID: %s)', 'echezona-payments'),
$token_id
)
);

if ($this->autocomplete_order) {
$order->update_status('completed');
}

WC()->cart->empty_cart();
return array(
'result' => 'success',
'redirect' => $this->get_return_url($order)
);
} else {
throw new Exception($response['message']);
}
} catch (Exception $e) {
$this->log('Token payment error: ' . $e->getMessage());
wc_add_notice($e->getMessage(), 'error');
return array(
'result' => 'fail',
'redirect' => '',
'messages' => $e->getMessage()
);
}
}

/**
* Process subscription payment.
*
* @param float $amount Amount to charge.
* @param WC_Order $order Order object.
* @return bool
*/
public function process_subscription_payment($amount, $order) {
try {
$token = WC_Payment_Tokens::get_customer_default_token($order->get_customer_id());

if (!$token) {
throw new Exception(__('No saved payment token found', 'echezona-payments'));
}

$response = $this->process_payment_with_token($token, $order);

if ($response['status'] === 'success') {
$order->payment_complete($response['transaction_id']);
$order->add_order_note(
sprintf(
__('Subscription payment completed via saved card (Token ID: %s)', 'echezona-payments'),
$token->get_id()
)
);
return true;
} else {
throw new Exception($response['message']);
}
} catch (Exception $e) {
$this->log('Subscription payment error: ' . $e->getMessage());
$order->add_order_note(
sprintf(
__('Subscription payment failed: %s', 'echezona-payments'),
$e->getMessage()
)
);
return false;
}
}

private function process_payment_with_token($token, $order)
{
// Implement token-based payment processing with Echezona API
// This is a placeholder - implement according to Echezona's API documentation
return array(
'status' => 'success',
'transaction_id' => 'TRX_' . uniqid(),
'message' => 'Payment successful'
);
}

/**
* Handle incoming webhooks from Echezona.
*/
public function handle_webhook() {
$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_ECHEZONA_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_X_ECHEZONA_SIGNATURE']) : '';

if (!$this->verify_webhook_signature($payload, $signature)) {
status_header(401);
exit('Invalid signature');
}

$data = json_decode($payload, true);

if (!$data || !isset($data['event'])) {
status_header(400);
exit('Invalid payload');
}

$this->log('Webhook received: ' . wp_json_encode($data));

try {
// Process webhook based on event type
switch ($data['event']) {
case 'charge.success':
$this->handle_successful_payment($data);
break;
case 'charge.failed':
$this->handle_failed_payment($data);
break;
case 'refund.processed':
$this->handle_refund($data);
break;
default:
$this->log('Unhandled webhook event: ' . sanitize_text_field($data['event']));
break;
}

status_header(200);
exit('Webhook processed');
} catch (Exception $e) {
$this->log('Webhook processing error: ' . $e->getMessage());
status_header(500);
exit('Webhook processing failed');
}
}

/**
* Handle successful payment webhook.
*
* @param array $data Webhook data.
*/
private function handle_successful_payment($data) {
$order_id = isset($data['data']['metadata']['order_id']) ? absint($data['data']['metadata']['order_id']) : 0;

if (!$order_id) {
$this->log('No order ID found in webhook data');
return;
}

$order = wc_get_order($order_id);
if (!$order) {
$this->log('Order not found: ' . $order_id);
return;
}

if ($order->get_status() === 'completed') {
$this->log('Order already completed: ' . $order_id);
return;
}

$reference = isset($data['data']['reference']) ? sanitize_text_field($data['data']['reference']) : '';
$order->payment_complete($reference);
$order->add_order_note(
sprintf(
__('Payment completed via webhook. Transaction Reference: %s', 'echezona-payments'),
$reference
)
);

if ($this->autocomplete_order) {
$order->update_status('completed');
}
}

/**
* Handle failed payment webhook.
*
* @param array $data Webhook data.
*/
private function handle_failed_payment($data) {
$order_id = isset($data['data']['metadata']['order_id']) ? absint($data['data']['metadata']['order_id']) : 0;

if (!$order_id) {
$this->log('No order ID found in webhook data');
return;
}

$order = wc_get_order($order_id);
if (!$order) {
$this->log('Order not found: ' . $order_id);
return;
}

$reference = isset($data['data']['reference']) ? sanitize_text_field($data['data']['reference']) : '';
$order->update_status(
'failed',
sprintf(
__('Payment failed. Transaction Reference: %s', 'echezona-payments'),
$reference
)
);
}

/**
* Handle refund webhook.
*
* @param array $data Webhook data.
*/
private function handle_refund($data) {
$order_id = isset($data['data']['metadata']['order_id']) ? absint($data['data']['metadata']['order_id']) : 0;

if (!$order_id) {
$this->log('No order ID found in webhook data');
return;
}

$order = wc_get_order($order_id);
if (!$order) {
$this->log('Order not found: ' . $order_id);
return;
}

$refund_amount = isset($data['data']['amount']) ? floatval($data['data']['amount']) / 100 : 0; // Convert from kobo to naira
$reference = isset($data['data']['reference']) ? sanitize_text_field($data['data']['reference']) : '';

$order->add_order_note(
sprintf(
__('Refund processed. Amount: %s. Transaction Reference: %s', 'echezona-payments'),
wc_price($refund_amount),
$reference
)
);
}

private function verify_webhook_signature($payload, $signature)
{
// Implement webhook signature verification according to Echezona's documentation
// This is a placeholder - implement according to Echezona's security requirements
return true;
}

public function process_refund($order_id, $amount = null, $reason = '')
{
$order = wc_get_order($order_id);
$transaction_id = $order->get_meta('_echezona_transaction_id');

if (!$transaction_id) {
return new WP_Error('error', __('No transaction ID found', 'echezona-payments'));
}

try {
// Implement refund processing with Echezona API
// This is a placeholder - implement according to Echezona's API documentation
$response = array(
'status' => 'success',
'refund_id' => 'REF_' . uniqid()
);

if ($response['status'] === 'success') {
$order->add_order_note(sprintf(__('Refund processed. Amount: %s. Reason: %s', 'echezona-payments'), wc_price($amount), $reason));
return true;
} else {
throw new Exception($response['message'] ?? __('Refund failed', 'echezona-payments'));
}
} catch (Exception $e) {
$this->log('Refund error: ' . $e->getMessage());
return new WP_Error('error', $e->getMessage());
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
* @return array
* @throws Exception If payment URL generation fails.
*/
private function get_payment_url($order)
{
// Generate unique transaction ID
$transaction_id = $this->generate_transaction_id($order);

// Log the API request for debugging
$this->log('Echezona API Request - URL: ' . $this->base_url . '/payment/initialize');
$this->log('Echezona API Request - Order ID: ' . $order->get_id());
$this->log('Echezona API Request - Transaction ID: ' . $transaction_id);
$this->log('Echezona API Request - Amount: ' . $order->get_total());

$request_body = array(
'amount' => $order->get_total(),
'currency' => $order->get_currency(),
'email' => $order->get_billing_email(),
'firstName' => $order->get_billing_first_name(),
'lastName' => $order->get_billing_last_name(),
'callbackUrl' => $this->get_callback_url($order),
'transactionId' => $transaction_id,
'mode' => $this->testmode ? 'Test' : 'Live',
'metadata' => array(
array(
'name' => 'Order Id',
'value' => (string) $order->get_id(),
),
array(
'name' => 'Customer Id',
'value' => (string) $order->get_customer_id(),
),
array(
'name' => 'Customer Name',
'value' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
),
array(
'name' => 'Customer Email',
'value' => $order->get_billing_email(),
),
array(
'name' => 'Customer Address',
'value' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
),
array(
'name' => 'Customer City',
'value' => $order->get_billing_city(),
),
),
'productId' => (string) $order->get_id(),
'producDescription' => 'Payment with Echezona payment gateway for WooCommerce order',
'applyConviniencyCharge' => false
);

$this->log('Echezona API Request - Body: ' . wp_json_encode($request_body));

$response = wp_remote_post(
$this->base_url . '/Payments/Initialize',
array(
'headers' => array(
'Authorization' => 'Bearer ' . $this->api_key,
'Content-Type' => 'application/json',
),
'body' => wp_json_encode($request_body),
'timeout' => 30,
)
);

if (is_wp_error($response)) {
$this->log('Echezona API Error: ' . $response->get_error_message());
throw new Exception(__('Could not connect to Echezona payment gateway. Please try again.', 'echezona-payments'));
}

$response_code = wp_remote_retrieve_response_code($response);
$response_body = wp_remote_retrieve_body($response);

$this->log('Echezona API Response Code: ' . $response_code);
$this->log('Echezona API Response Body: ' . $response_body);

if ($response_code !== 200) {
throw new Exception(
sprintf(
__('Echezona payment gateway error: %s', 'echezona-payments'),
$response_body
)
);
}

$body = json_decode($response_body, true);

if (!isset($body['data']['paymentUrl'])) {
$error_message = isset($body['message']) ? sanitize_text_field($body['message']) : __('Unknown error occurred', 'echezona-payments');
throw new Exception(
sprintf(
__('Could not get payment URL from Echezona: %s', 'echezona-payments'),
$error_message
)
);
}

// Store the access code on the order details
if (isset($body['data']['accessCode'])) {
$order->update_meta_data('_echezona_access_code', sanitize_text_field($body['data']['accessCode']));
$order->save();
}

return array(
'payment_url' => esc_url_raw($body['data']['paymentUrl']),
'transaction_id' => $transaction_id
);
}

/**
* Get callback URL for payment notifications.
*
* @param WC_Order $order Order object.
* @return string
*/
private function get_callback_url($order) {
return add_query_arg(
array(
'wc-api' => 'echezona_payment_callback',
'order_id' => $order->get_id()
),
home_url('/')
);
}

/**
* Check Echezona payment response.
*/
public function check_eczp_response() {
// Verify nonce for AJAX requests
if (wp_doing_ajax() && !check_ajax_referer('echezona-payment-nonce', 'nonce', false)) {
wp_send_json_error(__('Invalid security token', 'echezona-payments'));
exit;
}

$reference = isset($_GET['orderReference']) ? sanitize_text_field($_GET['orderReference']) : '';
$response_code = isset($_GET['responseCode']) ? sanitize_text_field($_GET['responseCode']) : '';

if ($response_code !== '00') {
wc_add_notice(__('Invalid callback parameters', 'echezona-payments'), 'error');
wp_safe_redirect(wc_get_checkout_url());
exit;
}

$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$order = wc_get_order($order_id);

if (!$order) {
wc_add_notice(__('Order not found', 'echezona-payments'), 'error');
wp_safe_redirect(wc_get_checkout_url());
exit;
}

// Extract transaction ID from order meta
$transaction_id = $order->get_meta('_echezona_transaction_id');

try {
$response = wp_remote_post(
$this->base_url . '/Payments/VerifyPayment',
array(
'headers' => array(
'Authorization' => 'Bearer ' . $this->api_key,
'Content-Type' => 'application/json',
),
'body' => wp_json_encode(array(
'transactionId' => (string) $transaction_id
)),
'timeout' => 30,
)
);

if (is_wp_error($response)) {
throw new Exception($response->get_error_message());
}

$body = json_decode(wp_remote_retrieve_body($response), true);

if (
(isset($body['responseCode']) && $body['responseCode'] === '00') ||
(isset($body['data']['isSuccessful']) && $body['data']['isSuccessful'] === true)
) {
// Payment successful
$order->payment_complete();
$order->add_order_note(
sprintf(
__('Payment completed via Echezona. Reference: %s', 'echezona-payments'),
$transaction_id
)
);

if ($this->autocomplete_order === 'yes') {
$order->update_status('completed');
}

wp_safe_redirect($this->get_return_url($order));
exit;
} else {
// Payment failed
$error_message = isset($body['message']) ? sanitize_text_field($body['message']) : __('Payment failed. Please try again.', 'echezona-payments');
$order->update_status(
'failed',
sprintf(
__('Payment failed via Echezona. Reference: %s', 'echezona-payments'),
$transaction_id
)
);

wc_add_notice($error_message, 'error');
wp_safe_redirect($order->get_checkout_payment_url());
exit;
}
} catch (Exception $e) {
$this->log('Payment verification error: ' . $e->getMessage());
wc_add_notice($e->getMessage(), 'error');
wp_safe_redirect(wc_get_checkout_url());
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