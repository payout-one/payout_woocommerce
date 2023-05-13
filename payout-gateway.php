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

if (!defined('ABSPATH')) {
    exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

if (!class_exists('WC_Payout_One')) {
    class WC_Payout_One {

        private static $instance = false;

        private function __construct() {
            require_once __DIR__ . '/lib/Payout/init.php';
            require_once __DIR__ . '/includes/class-wc-payout-logger.php';
            require_once __DIR__ . '/includes/class-wc-gateway-payout.php';

            add_action('init', [$this, 'load_plugin_textdomain']);
            add_filter('woocommerce_payment_gateways', [$this, 'add_to_wc_gateways']);
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
            add_action('woocommerce_thankyou', [$this, 'insert_script_payout']);
            add_action('wp_ajax_nopriv_checkOrderStatus', [$this, 'checkOrderStatus']);
            add_action('wp_ajax_checkOrderStatus', [$this, 'checkOrderStatus']);
            add_action('template_redirect', [$this, 'response_redirect']);
            add_action('in_plugin_update_message-' . plugin_basename(__FILE__), 'update_message', 10, 2);
        }

        public static function get_instance() {
            if (!self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
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
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payout_gateway') . '">' . __('Settings', 'payout-payment-gateway') . '</a>'
            ];

            return array_merge($plugin_links, $links);
        }

        public function insert_script_payout($oid) {
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

        public function checkOrderStatus() {
            $oid = $_POST["oid"];
            echo get_post_meta($oid, 'payout_order_status', true);
            die();
        }

        public function response_redirect() {
            /* do nothing if we are not on the appropriate page */
            if (!is_wc_endpoint_url('order-received')) {
                return;
            }

            global $wp;

            $order_id = $wp->query_vars['order-received'];
            $order    = wc_get_order($order_id);

            if ('payout_gateway' == $order->get_payment_method()) {
                add_action('wp_head', [$this, 'temporary_style']);
            }
        }

        public function temporary_style() {
            ?>
            <style>
                .woocommerce-order {
                    opacity: 0;
                }
            </style>
        <?php
}

    }

    add_action('plugins_loaded', ['WC_Payout_One', 'get_instance'], 11);
}