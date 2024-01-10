<?php

/**
 * Plugin Name: Payout payment gateway
 * Plugin URI: https://www.payout.one/
 * Description: Official Payout payment gateway plugin for WooCommerce.
 * Author: Seduco
 * Author URI: https://www.seduco.sk/
 * Version: 1.1.2
 * Text Domain: payout-payment-gateway
 * Domain Path: languages
 * Copyright (c) 2023, Seduco
 * Requires at least: 5.0.19
 * Tested up to: 6.4.2
 * WC tested up to: 8.4.0
 * WC requires at least: 4.0.1
 * @package   payout-payment-gateway
 * @author    Seduco
 * @category  Admin
 * @copyright Copyright (c) 2023, Seduco
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

if (!class_exists('WC_Payout_One')) {
    defined('WC_PAYOUT_GATEWAY_ID') or define('WC_PAYOUT_GATEWAY_ID', 'payout_gateway');
    defined('WC_PAYOUT_GATEWAY_VERSION') or define('WC_PAYOUT_GATEWAY_VERSION', '1.1.2');

    class WC_Payout_One {

        private static $instance = false;

        private function __construct() {
            require_once __DIR__ . '/lib/Payout/init.php';
            require_once __DIR__ . '/includes/class-wc-payout-logger.php';
            require_once __DIR__ . '/includes/class-wc-gateway-payout.php';

            add_action('init', [$this, 'load_plugin_textdomain']);
            add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
            add_filter('woocommerce_payment_gateways', [$this, 'add_to_wc_gateways']);
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
            add_action('wp_ajax_nopriv_order_payout_status', [$this, 'order_payout_status']);
            add_action('wp_ajax_order_payout_status', [$this, 'order_payout_status']);
            add_action('wp_enqueue_scripts', [$this, 'thank_you_scripts']);
            add_action('in_plugin_update_message-' . plugin_basename(__FILE__), [$this, 'update_message'], 10, 2);
        }

        public static function get_instance() {
            if (!self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
        }

        public function declare_hpos_compatibility() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        }

        public function load_plugin_textdomain() {
            load_plugin_textdomain('payout-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        /**
         * Add the gateway to WC Available Gateways
         */
        public function add_to_wc_gateways($gateways) {
            $gateways[] = 'WC_Payout_Gateway';
            return $gateways;
        }

        public function update_message($data, $response) {
            if (isset($data['upgrade_notice'])) {
                echo '<br><br>' . $data['upgrade_notice'];
            }
        }

        /**
         * Adds plugin page links
         */
        public function plugin_action_links($links) {
            $plugin_links = [
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . WC_PAYOUT_GATEWAY_ID) . '">' . __('Settings', 'payout-payment-gateway') . '</a>'
            ];

            return array_merge($plugin_links, $links);
        }

        public function order_payout_status() {
            $oid = sanitize_text_field($_POST["oid"]);
            if (empty($oid)) {
                die;
            }
            $order = wc_get_order($oid);
            if ($order) {
                echo $order->get_meta('payout_order_status');
            }
            die;
        }

        public function thank_you_scripts() {
            if (!is_wc_endpoint_url('order-received')) {
                return;
            }

            global $wp;

            if (empty($wp->query_vars['order-received'])) {
                return;
            }

            $order_id = $wp->query_vars['order-received'];
            $order    = wc_get_order($order_id);

            if (!$order || $order->get_payment_method() !== WC_PAYOUT_GATEWAY_ID) {
                return;
            }

            $payment_url = $order->get_meta('payout_redirect_url');
            if ($payment_url === false) {
                $payment_url = $order->get_checkout_payment_url();
            }

            // filemtime is used for versioning based on the file’s last modified time
            wp_enqueue_style('payout-thank-you-style', plugins_url('assets/css/payout-thank-you.css', __FILE__), [], filemtime(__DIR__ . '/assets/css/payout-thank-you.css'));

            wp_register_script('payout-thank-you-script', plugins_url('assets/js/payout-thank-you.js', __FILE__), [], filemtime(__DIR__ . '/assets/js/payout-thank-you.js'), true);
            wp_localize_script('payout-thank-you-script', 'payout_thank_you_data',
                [
                    'ajax_url'    => admin_url('admin-ajax.php'),
                    'payment_url' => $payment_url,
                    'order_id'    => $order_id
                ]
            );
            wp_enqueue_script('payout-thank-you-script');
        }

        public static function plugin_url() {
            return untrailingslashit(plugins_url('/', __FILE__));
        }

        public static function plugin_abspath() {
            return trailingslashit(plugin_dir_path(__FILE__));
        }

        /**
         * Registers WooCommerce Blocks integration.
         */
        public static function register_block_support() {
            add_action('woocommerce_blocks_loaded', [__CLASS__, 'gateway_payout_woocommerce_block_support']);
        }

        /**
         * Loads WooCommerce Blocks integration.
         */
        public static function gateway_payout_woocommerce_block_support() {
            if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                require_once __DIR__ . '/includes/blocks/class-wc-gateway-payout-blocks.php';

                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                        $payment_method_registry->register(new WC_Payout_Gateway_Blocks_Support());
                    }
                );
            }
        }
    }

    add_action('plugins_loaded', ['WC_Payout_One', 'get_instance'], 11);

    // Hook in Blocks integration. This action is called in a callback on plugins_loaded,
    // so current plugin class implementation is too late
    WC_Payout_One::register_block_support();
}
