<?php

if (!defined('ABSPATH')) {
    exit;
}

use Payout\Client as Client;

class WC_Payout_Gateway extends WC_Payment_Gateway {

    public $debug_enabled;
    public $instructions;
    public $client_id;
    public $client_secret;
    public $sandbox;

    public function __construct() {
        $this->id                 = 'payout_gateway';
        $this->has_fields         = false;
        $this->method_title       = __('Payout', 'payout-payment-gateway');
        $this->method_description = __('Payout gateway integration.', 'payout-payment-gateway') . '<br><strong style="color:green;">' . __('Notification URL: ', 'payout-payment-gateway') . '</strong>' . add_query_arg('wc-api', 'payout_gateway', home_url() . '/');
        $this->supports           = ['refunds'];

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title         = $this->get_option('title');
        $this->description   = $this->get_option('description');
        $this->instructions  = $this->get_option('instructions', $this->description);
        $this->debug_enabled = $this->get_option('debug') === 'yes' ? true : false;
        $this->client_id     = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->sandbox       = $this->get_option('sandbox');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_' . $this->id, [$this, 'payout_webhook_callback']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thank_you_page_instructions']);
        add_action('woocommerce_order_status_changed', [$this, 'log_woocommerce_status_change'], 10, 3);
        add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);
    }

    function payout_webhook_callback() {
        $notification = json_decode(file_get_contents('php://input'));

        if (!isset($notification->data->object)
            || $notification->data->object !== 'checkout'
            || !isset($notification->data->status)
            || !isset($notification->data->id)
            || !isset($notification->external_id)
            || !isset($notification->type)
            || !isset($notification->nonce)
            || !isset($notification->signature)
        ) {
            return;
        }

        $order_id        = $notification->external_id;
        $checkout_status = $notification->data->status;
        $checkout_id     = $notification->data->id;

        $config = [
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'sandbox'       => $this->sandbox
        ];
        $payout_client = new Client($config);

        if (!$payout_client->verifySignature([$order_id, $notification->type, $notification->nonce], $notification->signature)) {
            header_remove();
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Bad signature']);
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $order->update_meta_data('payout_order_status', $checkout_status);
        $order->update_meta_data('payout_checkout_id', $checkout_id);
        $order->save();

        $current_order_status = $order->get_status();

        if ($this->debug_enabled) {
            WC_Payout_Logger::log('Received payment notification status: ' . json_encode($checkout_status));
        }

        $completed_statuses = apply_filters(
            'payout_webhook_callback_completed_statuses',
            ['processing', 'packing', 'completed', 'shipping', 'ready-for-pickup', 'picked-up', 'cancelled', 'refunded', 'failed']
        );

        if ($checkout_status === 'succeeded') {
            $order->payment_complete();
        } else if ($checkout_status === 'expired' && !in_array($current_order_status, $completed_statuses)) {
            $order->update_status('failed', 'Payout : failed');
            if ($this->debug_enabled) {
                WC_Payout_Logger::log('Payment notification JSON: ' . json_encode($notification));
            }
        }
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = apply_filters('payout_gateway_form_fields', [
            'enabled'         => [
                'title'   => __('Gateway allow', 'payout-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Allow gateway', 'payout-payment-gateway'),
                'default' => 'yes'
            ],
            'payment_id'      => [
                'title' => __('Payment method', 'payout-payment-gateway'),
                'type'  => 'text'
            ],
            'language'        => [
                'title'       => __('Language', 'payout-payment-gateway'),
                'type'        => 'text',
                'description' => __('For example: sk,en', 'payout-payment-gateway'),
                'desc_tip'    => true
            ],
            'title'           => [
                'title'       => __('Gateway name', 'payout-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', 'payout-payment-gateway'),
                'desc_tip'    => true
            ],
            'sandbox'         => [
                'title'       => __('Status', 'payout-payment-gateway'),
                'type'        => 'select',
                'description' => '',
                'options'     => [
                    true  => __('Test', 'payout-payment-gateway'),
                    false => __('Production', 'payout-payment-gateway')
                ],
                'default'     => true
            ],
            'client_id'       => [
                'title'       => __('Client ID', 'payout-payment-gateway'),
                'type'        => 'text',
                'description' => __('Your Cilent ID', 'payout-payment-gateway'),
                'desc_tip'    => true
            ],
            'client_secret'   => [
                'title'       => __('Client Secret', 'payout-payment-gateway'),
                'type'        => 'password',
                'description' => __('Your Client Secret', 'payout-payment-gateway'),
                'desc_tip'    => true
            ],
            'description'     => [
                'title'       => __('Description', 'payout-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'payout-payment-gateway'),
                'desc_tip'    => true
            ],
            'instructions'    => [
                'title'       => __('Instructions', 'payout-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'payout-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true
            ],
            'debug'           => [
                'title'   => __('Debug', 'payout-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Allow', 'payout-payment-gateway'),
                'default' => 'no'
            ],
            'idempotency_key' => [
                'title'       => __('Send idempotency key', 'payout-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __("Disclaimer: If it's allowed, order id is used as unique key to block creating multiple checkouts with same order id. When system change the order's amount and order id will be same, new checkout will not be created so old amount will be used for payment.", 'payout-payment-gateway'),
                'label'       => __('Allow', 'payout-payment-gateway'),
                'default'     => 'no'
            ]
        ]);
    }

    /**
     * Output for the order received page.
     */
    public function thank_you_page_instructions() {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
    }

    /**
     * Log the order status changes
     */
    public function log_woocommerce_status_change($order_id, $old_status, $new_status) {
        if ($this->debug_enabled) {
            WC_Payout_Logger::log('Status change: ' . json_encode('Order ID: ' . $order_id . ' | ' . 'Old status: ' . $old_status . ' | ' . 'New status: ' . $new_status));
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
        $client_id     = $this->client_id;
        $client_secret = $this->client_secret;

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
        $string_to_hash = [$amount, $currency, $external_id, $iban, $nonce, $client_secret];
        $message        = implode('|', $string_to_hash);

        $signature = hash('sha256', pack('A*', $message));

        // RETRIEVE AN SECURITY TOKEN
        $body = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret

        ];

        $endpoint = $this->sandbox ? "https://sandbox.payout.one/api/v1/authorize" : "https://app.payout.one/api/v1/authorize";

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

        $endpoint_r = $this->sandbox ? "https://sandbox.payout.one/api/v1/refunds" : "https://app.payout.one/api/v1/refunds";
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

        if ($this->debug_enabled) {
            WC_Payout_Logger::log('Refund response:' . json_encode($data_r));
        }

        if (($response_message == "OK") && ($response_code == 200)) {
            return true;
        } else {
            return new WP_Error('error', __('Refund failed.', 'woocommerce') . '- ' . $error_message);
            return false;
        }
    }

    private function float_to_cents($amount) {
        return (int) round($amount * 100);
    }

    private function get_products_array($order) {
        $products = [];
        foreach ($order->get_items() as $item_id => $item) {
            $qty          = $item->get_quantity();
            $unit_price   = $item->get_total() / $qty;
            $product_data = [
                'name'       => $item->get_name(),
                'quantity'   => $qty,
                'unit_price' => $this->float_to_cents($unit_price)
            ];
            $products[] = $product_data;
        }
        return $products;
    }

    private function get_checkout_data($order) {
        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $order_id   = $order->get_id();

        $checkout_data = [
            'amount'       => $order->get_total(),
            'currency'     => $order->get_currency(),
            'customer'     => [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone()
            ],
            'products'     => $this->get_products_array($order),
            'external_id'  => $order_id,
            'redirect_url' => $this->get_return_url($order)
        ];

        // All of these billing details are required by default and empty address line 2 is valid
        $checkout_data['billing_address'] = [
            'address_line_1' => $order->get_billing_address_1(),
            'address_line_2' => $order->get_billing_address_2(),
            'city'           => $order->get_billing_city(),
            'country_code'   => $order->get_billing_country(),
            'name'           => $first_name . ' ' . $last_name,
            'postal_code'    => $order->get_billing_postcode()
        ];

        $shipping_address1 = $order->get_shipping_address_1();
        if (!empty($shipping_address1)) {
            $checkout_data['shipping_address'] = [
                'address_line_1' => $shipping_address1,
                'address_line_2' => $order->get_shipping_address_2(),
                'city'           => $order->get_shipping_city(),
                'country_code'   => $order->get_shipping_country(),
                'name'           => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'postal_code'    => $order->get_shipping_postcode()
            ];
        }

        if ($this->get_option('idempotency_key') === 'yes') {
            $checkout_data['idempotency_key'] = $order_id;
        }

        return $checkout_data;
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Payout error: Order not found.');
            }

            if ($order->get_total() == 0) {
                wc_add_notice(sprintf(__('Payment gateway %s : Order value must be greater than 0.', 'payout-payment-gateway'), $this->method_title), 'error');
                return false;
            }

            $checkout_data = $this->get_checkout_data($order);

            $stored_redirect_url = $order->get_meta('payout_redirect_url');

            $config = [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'sandbox'       => $this->sandbox
            ];
            $payout_client = new Client($config);

            if (!empty($stored_redirect_url)) {
                $redirect_url = $stored_redirect_url;
            } else {
                $response = $payout_client->createCheckout($checkout_data);
                if ($this->debug_enabled) {
                    WC_Payout_Logger::log('Amount: ' . json_encode($checkout_data['amount']));
                    WC_Payout_Logger::log('External id: ' . json_encode($checkout_data['external_id']));
                    $idempotency_key = array_key_exists('idempotency_key', $checkout_data) ? $checkout_data['idempotency_key'] : null;
                    WC_Payout_Logger::log('Idempotency key: ' . json_encode($idempotency_key));
                    WC_Payout_Logger::log('ID (response): ' . json_encode($response->id));
                    WC_Payout_Logger::log('Payout status (response): ' . json_encode($response->status));
                }

                if ($response->status === 'processing') {
                    $order->update_status('pending');
                }

                $redirect_url = $response->checkout_url;

                $payment_id = $this->get_option('payment_id');
                $language   = $this->get_option('language');
                $query_data = [];

                if (!empty($payment_id)) {
                    $query_data['payment_method'] = $payment_id;
                }

                if (!empty($language)) {
                    $query_data['locale'] = $language;
                }

                if (count($query_data) > 0) {
                    $query_op = parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?';
                    $redirect_url .= $query_op . http_build_query($query_data);
                }

                $order->update_meta_data('payout_redirect_url', $redirect_url);
                $order->save();
            }

            return [
                'result'   => 'success',
                'redirect' => $redirect_url
            ];
        } catch (Exception $e) {
            wc_add_notice(sprintf(__('Payment gateway %s : There is a problem, contact your webmaster.', 'payout-payment-gateway'), $this->method_title), 'error');
            if ($this->debug_enabled) {
                WC_Payout_Logger::log('Error: ' . $e->getMessage());
            }
            return false;
        }
    }
}