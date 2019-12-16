<?php
/**
 * Plugin Name: Payout Gateway
 * Plugin URI: https://www.payout.one/
 * Description: Official Payout gateway plugin for WooCommerce.
 * Author: Seduco
 * Author URI: https://www.seduco.sk/
 * Version: 1.0.1
 * Text Domain: wc-payout
 *
 * Domain Path: /lang
 * Copyright (c) 2019, Seduco
 *
 * @package   wc-payout
 * @author    Seduco
 * @category  Admin
 * @copyright Copyright (c) 2019, Seduco
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


load_plugin_textdomain( 'wc-payout', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );


// Payout API

$plugin_dir = plugin_dir_path(__FILE__);

require($plugin_dir . '/Payout/Client.php');
require($plugin_dir . '/Payout/Connection.php');
require($plugin_dir . '/Payout/Checkout.php');



use Payout\Client;




/**
 * Add the gateway to WC Available Gateways
 */
function wc_payout_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Payout_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_payout_add_to_gateways' );


/**
 * Adds plugin page links
 */
function wc_payout_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payout_gateway' ) . '">' . __( 'Settings', 'wc-payout' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_payout_gateway_plugin_links' );


add_action( 'plugins_loaded', 'wc_payout_gateway_init', 11 );

function wc_payout_gateway_init() {

	class WC_Payout_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'payout_gateway';
			$this->has_fields         = false;
			$this->method_title       = __( 'Payout', 'wc-payout' );
			$this->method_description = __( 'Payout gateway integration.', 'wc-payout' ). '<br><strong style="color:green;">'. __( "Notification URL: ", 'payout' ). '</strong>' . add_query_arg('wc-api', 'payout_gateway', home_url());
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_api_' . $this->id , array( $this, 'payout_callback' ) );
		
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );


			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}


		function payout_callback() {

		 	$notification = json_decode(file_get_contents('php://input'));


		    $config = array(
		        'client_id' => $this->get_option( 'client_id' ),
		        'client_secret' => $this->get_option( 'client_secret' ),
		        'sandbox' => $this->get_option( 'sandbox' )
		    );

		    // Initialize Payout
		    $payout = new Client($config);

		    
	

		    $external_id = $notification->external_id;
		    $store_payout_order_status = $notification->data->status;

			if (isset($external_id) && isset($store_payout_order_status)) {
		   		if (!$payout->verifySignature(array($external_id, $notification->type, $notification->nonce), $notification->signature)) {

		          die();
		    } 


		   	$order = wc_get_order( $external_id);
			
		  	update_post_meta($external_id, 'payout_order_status', $store_payout_order_status);




     		 if ($order->get_payment_method() == $this->id) {
      	
	   
				if ($store_payout_order_status == "succeeded" || $store_payout_order_status == "successful") { 

					$order->payment_complete();
			

				} else if ($store_payout_order_status == "in_transit" || $store_payout_order_status == "processing" || $store_payout_order_status == "pending") {

					$order->update_status('on-hold' , 'Payout : on-hold');

				} else if ($store_payout_order_status == "refunded") {

					$order->update_status('refunded', 'Payout : refunded');

				} else {

					$order->update_status('failed' , 'Payout : failed');

				}



			 }


			

			}


		}

		


			
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'payout_gateway_form_fields', array(

		  
				'enabled' => array(
					'title'   => __( 'Gateway allow', 'wc-payout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Allow gateway', 'wc-payout' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Gateway name', 'wc-payout' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-payout' ),
					'desc_tip'    => true,
				),


				 'sandbox' => array(
		          'title' => __('Status', 'wc-payout'),
		          'type' => 'select',
		          'description' => '',
		          'options' => array(
		            true => __('Test', 'wc-payout'),
		            false => __('Production', 'wc-payout')
		          ),
		          'default' => true
		        ),

				'client_id' => array(
					'title'       => __( 'Client ID', 'wc-payout' ),
					'type'        => 'text',
					'description' => __( 'Your Cilent ID', 'wc-payout' ),
					'desc_tip'    => true,
				),


				'client_secret' => array(
					'title'       => __( 'Client Secret', 'wc-payout' ),
					'type'        => 'text',
					'description' => __( 'Your Client Secret', 'wc-payout' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-payout' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-payout' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-payout' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-payout' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}



	
	
		/**
		 * Add content to the WC emails.
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 */
		public function process_payment( $order_id ) {


			try {
			    // Config Payout API
			    $config = array(
			        'client_id' => $this->get_option( 'client_id' ),
			        'client_secret' => $this->get_option( 'client_secret' ),
			        'sandbox' => $this->get_option( 'sandbox' )
			    );

			    // Initialize Payout
			    $payout = new Client($config);

			  
			     $order = wc_get_order($order_id); 
			     $billingData = $order->get_address();


			    // Create checkout
			    $checkout_data = array(
			        'amount' => $order->get_total(),
			        'currency' => $order->get_currency(),
			        'customer' => [
			            'first_name' => $order->get_billing_first_name(),
			            'last_name' => $order->get_billing_last_name(),
			            'email' => $order->get_billing_email()
			        ],
			        'external_id' => $order->get_id(),
			        'redirect_url' =>  $this->get_return_url($order)
			    );


			    	

						

			    $response = $payout->createCheckout($checkout_data);
			   

				 if ($response->status == "processing") {
				    	$order->update_status('pending');
				  }



			   return array(
						'result' => 'success',
						'redirect' => $response->checkout_url,
				);


			} catch (Exception $e) {
			    echo $e->getMessage();
			    return false;
			}



				
					
						
				
			
		}
	
  } 
}




add_action( 'template_redirect', 'payout_response_redirect' );
 
function payout_response_redirect(){
 
	/* do nothing if we are not on the appropriate page */
	if( !is_wc_endpoint_url( 'order-received' ) || empty( $_GET['key'] ) ) {
		return;
	}
 
	$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
	$order = wc_get_order( $order_id );






	if( 'payout_gateway' == $order->get_payment_method() ) { /* WC 3.0+ */


		$payout_order_status = get_post_meta( $order_id, 'payout_order_status', true);

 			// expired , failed , processing, in_transit

			if ($payout_order_status == "succeeded" || $payout_order_status == "successful" || $payout_order_status == "pending" || $payout_order_status == "in_transit" || $payout_order_status == "processing" || $payout_order_status == "refunded") {
				return;
			} else {
			   $redirect = $order->get_checkout_payment_url();
			   wp_redirect(  $redirect );
			}

		
		
		exit;


	} else {

		return;
	
	}


	
 

}
