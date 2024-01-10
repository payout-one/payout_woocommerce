<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Payout_Gateway_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = WC_PAYOUT_GATEWAY_ID;

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[$this->name];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     */
    public function get_payment_method_script_handles() {
        $script_path       = '/assets/js/blocks/blocks.js';
        $script_asset_path = WC_Payout_One::plugin_abspath() . 'assets/js/blocks/blocks.asset.php';
        $script_asset      = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
            'dependencies' => [],
            'version'      => WC_PAYOUT_GATEWAY_VERSION
        ];
        $script_url = WC_Payout_One::plugin_url() . $script_path;

        $script_handle = 'wc-payout-payments-blocks';

        wp_register_script(
            $script_handle,
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        return [$script_handle];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => ['products']
        ];
    }
}
