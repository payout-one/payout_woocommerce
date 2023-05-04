<?php

/**
 * Plugin Name: Payout payment gateway
 * Plugin URI: https://www.payout.one/
 * Description: Official Payout payment gateway plugin for WooCommerce.
 * Author: Seduco
 * Author URI: https://www.seduco.sk/
 * Version: 1.0.15
 * Text Domain: payout-payment-gateway
 * Domain Path: languages
 * Copyright (c) 2020, Seduco
 * WC tested up to: 5.1.0
 * WC requires at least: 3.0.0
 * @package   payout-payment-gateway
 * @author    Seduco
 * @category  Admin
 * @copyright Copyright (c) 2020, Seduco
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */

defined('ABSPATH') or exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Payout API
$plugin_dir = plugin_dir_path(__FILE__);
require $plugin_dir . '/lib/Payout/init.php';

use Payout\Client;

function payout_load_plugin_textdomain() {
    // Load translations from the languages directory.
    $locale = get_locale();

    // This filter is documented in /wp-includes/l10n.php.
    $locale = apply_filters('plugin_locale', $locale, 'payout-payment-gateway'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

    load_textdomain('payout-payment-gateway', plugin_dir_path(__FILE__) . 'languages/payout-payment-gateway-' . $locale . '.mo');

    load_plugin_textdomain('payout-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'payout_load_plugin_textdomain');

/**
 * Add the gateway to WC Available Gateways
 */
function wc_payout_add_to_gateways($gateways) {
    $gateways[] = 'WC_Payout_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_payout_add_to_gateways');

/**
 * Adds plugin page links
 */
function wc_payout_gateway_plugin_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payout_gateway') . '">' . __('Settings', 'payout-payment-gateway') . '</a>'
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_payout_gateway_plugin_links');

add_action('plugins_loaded', 'wc_payout_gateway_init', 11);
function wc_payout_gateway_init() {
    class WC_Payout_Gateway extends WC_Payment_Gateway {
        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id                 = 'payout_gateway';
            $this->has_fields         = false;
            $this->method_title       = __('Payout', 'payout-payment-gateway');
            $this->method_description = __('Payout gateway integration.', 'payout-payment-gateway') . '<br><strong style="color:green;">' . __('Notification URL: ', 'payout-payment-gateway') . '</strong>' . add_query_arg('wc-api', 'payout_gateway', home_url() . '/');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);
            $this->supports     = array('refunds');

            // Actions
            add_action('woocommerce_update_options_payment_gateways', array($this, 'gateway_info'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . $this->id, array($this, 'payout_callback'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_order_status_changed', array($this, 'log_woocommerce_status_change'), 10, 3);
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        function payout_callback() {
            $notification = json_decode(file_get_contents('php://input'));

            $debug = $this->get_option('debug');

            if ($debug == "yes") {
                $logger = wc_get_logger();
            }

            $config = array(
                'client_id'     => $this->get_option('client_id'),
                'client_secret' => $this->get_option('client_secret'),
                'sandbox'       => $this->get_option('sandbox')
            );

            // Initialize Payout
            $payout = new Client($config);

            $external_id               = $notification->external_id;
            $store_payout_order_status = $notification->data->status;
            $payout_checkout_id        = $notification->data->id;
            $notification_type         = $notification->data->object;

            if ($notification_type != "checkout") {
                return;
            }

            if (isset($external_id) && isset($store_payout_order_status)) {
                if (!$payout->verifySignature(array($external_id, $notification->type, $notification->nonce), $notification->signature)) {
                    $result = array('error' => "Bad signature");
                    header_remove();
                    header('Content-Type: application/json');
                    http_response_code(401);
                    echo json_encode($result, true);
                    exit();
                }

                $order = wc_get_order($external_id);

                update_post_meta($external_id, 'payout_order_status', $store_payout_order_status);
                update_post_meta($external_id, 'payout_checkout_id', $payout_checkout_id);

                $current_order_status = $order->get_status();

                if ($debug == 'yes') {
                    $logger->log('debug', 'Recieved payment notification: ' . json_encode($store_payout_order_status), array('source' => 'payout'));
                }

                $completed_statuses = ["processing", "packing", "completed", "shipping", "ready-for-pickup", "picked-up", "cancelled", "refunded", "failed"];

                if ($store_payout_order_status == "succeeded") {
                    $order->payment_complete();
                } else if ($store_payout_order_status == "expired" && !in_array($current_order_status, $completed_statuses)) {
                    $order->update_status('failed', 'Payout : failed');
                }
            }
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
            $this->form_fields = apply_filters('payout_gateway_form_fields', array(
                'enabled'         => array(
                    'title'   => __('Gateway allow', 'payout-payment-gateway'),
                    'type'    => 'checkbox',
                    'label'   => __('Allow gateway', 'payout-payment-gateway'),
                    'default' => 'yes'
                ),
                'payment_id'      => array(
                    'title' => __('Payment method', 'payout-payment-gateway'),
                    'type'  => 'text'
                ),
                'language'        => array(
                    'title'       => __('Language', 'payout-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('For example: sk,en', 'payout-payment-gateway'),
                    'desc_tip'    => true
                ),
                'title'           => array(
                    'title'       => __('Gateway name', 'payout-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'payout-payment-gateway'),
                    'desc_tip'    => true
                ),
                'sandbox'         => array(
                    'title'       => __('Status', 'payout-payment-gateway'),
                    'type'        => 'select',
                    'description' => '',
                    'options'     => array(
                        true  => __('Test', 'payout-payment-gateway'),
                        false => __('Production', 'payout-payment-gateway')
                    ),
                    'default'     => true
                ),
                'client_id'       => array(
                    'title'       => __('Client ID', 'payout-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('Your Cilent ID', 'payout-payment-gateway'),
                    'desc_tip'    => true
                ),
                'client_secret'   => array(
                    'title'       => __('Client Secret', 'payout-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('Your Client Secret', 'payout-payment-gateway'),
                    'desc_tip'    => true
                ),
                'description'     => array(
                    'title'       => __('Description', 'payout-payment-gateway'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'payout-payment-gateway'),
                    'desc_tip'    => true
                ),
                'instructions'    => array(
                    'title'       => __('Instructions', 'payout-payment-gateway'),
                    'type'        => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'payout-payment-gateway'),
                    'default'     => '',
                    'desc_tip'    => true
                ),
                'debug'           => array(
                    'title'   => __('Debug', 'payout-payment-gateway'),
                    'type'    => 'checkbox',
                    'label'   => __('Allow', 'payout-payment-gateway'),
                    'default' => 'no'
                ),
                'idempotency_key' => array(
                    'title'       => __('Send idempotency key', 'payout-payment-gateway'),
                    'type'        => 'checkbox',
                    'description' => __("Disclaimer: If it's allowed, order id is used as unique key to block creating multiple checkouts with same order id. When system change the order's amount and order id will be same, new checkout will not be created so old amount will be used for payment.", 'payout-payment-gateway'),
                    'label'       => __('Allow', 'payout-payment-gateway'),
                    'default'     => 'no'
                )
            ));
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Log the order status changes
         */
        public function log_woocommerce_status_change($order_id, $old_status, $new_status) {
            $debug = $this->get_option('debug');
            if ($debug == "yes") {
                $logger = wc_get_logger();
                $logger->log('debug', 'Status change: ' . json_encode('Order ID: ' . $order_id . ' | ' . 'Old status: ' . $old_status . ' | ' . 'New status: ' . $new_status), array('source' => 'payout'));
            }
        }

        /**
         * Add content to the WC emails.
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }

        public function process_refund($order_id, $amount = null, $reason = '') {
            // Get neccesary data
            $client_id     = $this->get_option('client_id');
            $client_secret = $this->get_option('client_secret');

            // Initialize Payout due to accessing get Signature function

            // Checkout id
            $checkout_id = get_post_meta($order_id, 'payout_checkout_id', true);

            // nonce
            $bytes = random_bytes(5);
            $nonce = bin2hex($bytes);

            // Statement - for now empty string
            $statement = '';

            // Order object - to exclude some data
            $order = wc_get_order($order_id);

            // Necessary data for signature
            $amount      = bcmul($amount, 100);
            $currency    = $order->get_currency();
            $external_id = $order_id;

            // Temporrary assign there an order ID
            $iban = "";

            // Creating signature from neccesary data
            $string_to_hash = array($amount, $currency, $external_id, $iban, $nonce, $client_secret);
            $message        = implode('|', $string_to_hash);

            $signature = hash('sha256', pack('A*', $message));

            // RETRIEVE AN SECURITY TOKEN
            $body = [
                'client_id'     => $client_id,
                'client_secret' => $client_secret

            ];

            $endpoint = ($this->get_option('sandbox')) ? "https://sandbox.payout.one/api/v1/authorize" : "https://app.payout.one/api/v1/authorize";

            $body = wp_json_encode($body);

            $options = [
                'body'        => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json'
                ],
                'timeout'     => 60,
                'method'      => 'POST',
                'redirection' => 5,
                'blocking'    => true,
                'httpversion' => '1.0',
                'sslverify'   => true,
                'data_format' => 'body'
            ];

            $data    = wp_remote_request($endpoint, $options);
            $decoded = json_decode($data['body']);
            $token   = $decoded->token;

            // REFUND REQUEST
            $body_r = [
                'amount'               => $amount,
                'checkout_id'          => $checkout_id,
                'statement_descriptor' => $statement,
                'nonce'                => $nonce,
                'signature'            => $signature
            ];

            $endpoint_r = ($this->get_option('sandbox')) ? "https://sandbox.payout.one/api/v1/refunds" : "https://app.payout.one/api/v1/refunds";
            $basicauth  = 'Bearer ' . $token;

            $body_r = wp_json_encode($body_r);

            $options_r = [
                'body'        => $body_r,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => $basicauth,
                    'Accept'        => 'application/json'
                ],
                'timeout'     => 60,
                'redirection' => 5,
                'blocking'    => true,
                'httpversion' => '1.0',
                'sslverify'   => true,
                'data_format' => 'body'
            ];

            $data_r = wp_remote_post($endpoint_r, $options_r);

            $response_message = $data_r['response']['message'];
            $response_code    = $data_r['response']['code'];
            $error_message    = $data_r['body'];

            $debug = $this->get_option('debug');
            if ($debug == "yes") {
                $logger = wc_get_logger();
                $logger->log('debug', 'Refund response:' . json_encode($data_r), array('source' => 'payout'));
            }

            if (($response_message == "OK") && ($response_code == 200)) {
                return true;
            } else {
                return new WP_Error('error', __('Refund failed.', 'woocommerce') . '- ' . $error_message);
                return false;
            }
        }

        /**
         * Process the payment and return the result
         */
        public function process_payment($order_id) {
            try {
                // Config Payout API
                $config = array(
                    'client_id'     => $this->get_option('client_id'),
                    'client_secret' => $this->get_option('client_secret'),
                    'sandbox'       => $this->get_option('sandbox')
                );

                // Initialize Payout
                $payout = new Client($config);

                $order       = wc_get_order($order_id);
                $billingData = $order->get_address();

                $first_name  = $order->get_billing_first_name();
                $last_name   = $order->get_billing_last_name();
                $order_total = $order->get_total();

                $products = array();
                // Get and Loop Over Order Items
                foreach ($order->get_items() as $item_id => $item) {
                    $product      = $item->get_product();
                    $product_data = [
                        'name'       => $item->get_name(),
                        'quantity'   => $item->get_quantity(),
                        'unit_price' => bcmul($product->get_price(), 100)

                    ];
                    array_push($products, $product_data);
                }

                // Create checkout
                $checkout_data = array(
                    'amount'       => $order_total,
                    'currency'     => $order->get_currency(),
                    'customer'     => [
                        'first_name' => $first_name,
                        'last_name'  => $last_name,
                        'email'      => $order->get_billing_email(),
                        'phone'      => $order->get_billing_phone()
                    ],
                    'products'     => $products,
                    'external_id'  => $order->get_id(),
                    'redirect_url' => $this->get_return_url($order)
                );

                if ($order->get_billing_address_1() && $order->get_billing_city() && $order->get_billing_postcode()) {
                    $checkout_data['billing_address'] = [
                        'address_line_1' => $order->get_billing_address_1(),
                        'address_line_2' => $order->get_billing_address_2(),
                        'city'           => $order->get_billing_city(),
                        'country_code'   => $order->get_billing_country(),
                        'name'           => $first_name . ' ' . $last_name,
                        'postal_code'    => $order->get_billing_postcode()

                    ];
                }

                if ($order->get_shipping_address_1()) {
                    $checkout_data['shipping_address'] = [
                        'address_line_1' => $order->get_shipping_address_1(),
                        'address_line_2' => $order->get_shipping_address_2(),
                        'city'           => $order->get_shipping_city(),
                        'country_code'   => $order->get_shipping_country(),
                        'name'           => $first_name . ' ' . $last_name,
                        'postal_code'    => $order->get_shipping_postcode()

                    ];
                }

                if ('yes' === $this->get_option('idempotency_key')) {
                    $checkout_data['idempotency_key'] = $order->get_id();
                }

                if ($order->get_total() == 0) {
                    wc_add_notice(sprintf(__('Payment gateway %s : Order value must be greater than 0.', 'payout-payment-gateway'), $this->method_title), 'error');
                    return false;
                }

                $debug = $this->get_option('debug');

                $stored_redirect_url = get_post_meta($order_id, 'payout_redirect_url', true);

                if ($stored_redirect_url) {
                    $redirect_url = $stored_redirect_url;
                } else {
                    $response = $payout->createCheckout($checkout_data);
                    if ($debug == "yes") {
                        $logger = wc_get_logger();
                        $logger->log('debug', 'Amount: ' . json_encode($checkout_data['amount']), array('source' => 'payout'));
                        $logger->log('debug', 'External id: ' . json_encode($checkout_data['external_id']), array('source' => 'payout'));

                        if (array_key_exists('idempotency_key', $checkout_data)) {
                            $idempotency_key = $checkout_data['idempotency_key'];
                        } else {
                            $idempotency_key = null;
                        }

                        $logger->log('debug', 'Idempotency key: ' . json_encode($idempotency_key), array('source' => 'payout'));

                        $logger->log('debug', 'ID(response): ' . json_encode($response->id), array('source' => 'payout'));
                        $logger->log('debug', 'Payout status(response): ' . json_encode($response->status), array('source' => 'payout'));
                    }

                    if ($response->status == "processing") {
                        $order->update_status('pending');
                    }

                    $redirect_url = $response->checkout_url;

                    $payment_id = $this->get_option('payment_id');
                    $language   = $this->get_option('language');

                    if ($payment_id != "") {
                        $redirect_url = $response->checkout_url . '?payment_method=' . $payment_id;
                    }

                    if ($language != "") {
                        $redirect_url = $response->checkout_url . '?locale=' . $language;
                    }
                }

                update_post_meta($order_id, 'payout_redirect_url', $redirect_url);

                return array(
                    'result'   => 'success',
                    'redirect' => $redirect_url
                );
            } catch (Exception $e) {
                wc_add_notice(sprintf(__('Payment gateway %s : There is a problem, contact your webmaster.', 'payout-payment-gateway'), $this->method_title), 'error');
                return false;
            }
        }
    }
}

add_action('woocommerce_thankyou', 'insert_script_payout');
function insert_script_payout($oid) {
    /* do nothing if we are not on the appropriate page */
    if (!is_wc_endpoint_url('order-received')) {
        return;
    }

    global $wp;

    $order_id = $wp->query_vars['order-received'];
    $order    = wc_get_order($order_id);

    if ('payout_gateway' == $order->get_payment_method()) {
        /* WC 3.0+ */
        $payment_url = $order->get_checkout_payment_url();
        ?>
		<script>
			jQuery(document).ready(function($) {
				jQuery('.woocommerce-order').addClass('processing').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: .25
					}
				});

				jQuery('.woocommerce-order').css('opacity', '1');
				jQuery('.woocommerce-order > *').css('opacity', '0');

				var counter = 0;
				var checkingTime = 8;
				var checkInterval = 1000;
				var oid = '<?php echo $oid; ?>';

				$params = {
					action: 'checkOrderStatus',
					oid: oid,
				}

				var checkingInterval = setInterval(function() {
					$.ajax({
						type: "POST",
						dataType: "html",
						url: "<?php echo admin_url('admin-ajax.php') ?>",
						data: $params,
						success: function(data) {

							if ((data == "succeeded") || data == "processing") {
								clearInterval(checkingInterval);
								jQuery('.woocommerce-order').addClass('done').unblock();
								jQuery('.woocommerce-order > *').css('opacity', '1');
							}

							//console.log(data);
							counter++;

							// Redirect to payment URL if response is different than "succeeded" or "processing"
							if ((counter > checkingTime)) {
								clearInterval(checkingInterval);
								window.location.replace('<?php echo $payment_url; ?>');
							}
						},
						error: function(jqXHR, textStatus, errorThrown) {
							console.log('Cannot retrieve data.');
						}
					});
				}, checkInterval);
			});
		</script>
	<?php
}
}

function checkOrderStatus() {
    $oid = $_POST["oid"];
    echo get_post_meta($oid, 'payout_order_status', true);
    die();
}
add_action('wp_ajax_nopriv_checkOrderStatus', 'checkOrderStatus');
add_action('wp_ajax_checkOrderStatus', 'checkOrderStatus');

function payout_response_redirect() {
    /* do nothing if we are not on the appropriate page */
    if (!is_wc_endpoint_url('order-received')) {
        return;
    }

    global $wp;

    $order_id = $wp->query_vars['order-received'];
    $order    = wc_get_order($order_id);

    if ('payout_gateway' == $order->get_payment_method()) {
        add_action('wp_head', 'payout_temporary_style');
    }
}
add_action('template_redirect', 'payout_response_redirect');

function payout_temporary_style() {
    ?>
	<style>
		.woocommerce-order {
			opacity: 0;
		}
	</style>
<?php
}

function payout_update_message($data, $response) {
    if (isset($data['upgrade_notice'])) {
        echo '<br><br>' . $data['upgrade_notice'];
    }
}
add_action('in_plugin_update_message-payout-payment-gateway/payout-gateway.php', 'payout_update_message', 10, 2);
