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
        $this->id                 = WC_PAYOUT_GATEWAY_ID;
        $this->has_fields         = false;
        $this->method_title       = __('Payout', 'payout-payment-gateway');
        $this->method_description = __('Payout gateway integration.', 'payout-payment-gateway') . '<br><strong style="color:green;">' . __('Notification URL: ', 'payout-payment-gateway') . '</strong>' . add_query_arg('wc-api', WC_PAYOUT_GATEWAY_ID, home_url() . '/');
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
                'description' => __('Your Client ID', 'payout-payment-gateway'),
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
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Payout error: ' . __('Order not found', 'payout-payment-gateway'));
            }

            $checkout_id = $order->get_meta('payout_checkout_id');
            if (empty($checkout_id)) {
                throw new Exception('Payout error: ' . __('checkout_id missing', 'payout-payment-gateway'));
            }

            if ($order->get_payment_method() !== $this->id) {
                throw new Exception('Payout error: ' . __('Payment method mismatch', 'payout-payment-gateway'));
            }

            $refund_amount = is_null($amount) ? 0 : $amount;

            // Payout API docs says -> This attribute is obsolete. It's required and you can send it with empty value.
            $iban = '';

            // Empty for now
            $statement_descriptor = '';

            $refund_data = [
                // This has to be in cents, PHP lib doesn't convert it
                'amount'               => $this->float_to_cents($refund_amount),
                'checkout_id'          => $order_id,
                // Lib moves this to 'checkout_id' before making API call
                'payout_id'            => $checkout_id,
                'statement_descriptor' => $statement_descriptor,
                'iban'                 => $iban,
                'currency'             => $order->get_currency()
            ];

            $config = [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'sandbox'       => $this->sandbox
            ];
            $payout_client = new Client($config);

            $response = $payout_client->createRefund($refund_data, null);

            if ($this->debug_enabled) {
                WC_Payout_Logger::log('Refund response: ' . json_encode($response));
            }

            return true;
        } catch (Exception $e) {
            if ($this->debug_enabled) {
                WC_Payout_Logger::log($e->getMessage());
            }
            return new WP_Error('error', __('Refund failed.', 'woocommerce') . ' - ' . $e->getMessage());
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
                'unit_price' => $this->float_to_cents($unit_price) // This has to be in cents, PHP lib doesn't convert it
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
            'amount'       => $order->get_total(), // This has to be float because PHP lib converts it to cents
            'currency' => $order->get_currency(),
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
                WC_Payout_Logger::log($e->getMessage());
            }
            return false;
        }
    }
}