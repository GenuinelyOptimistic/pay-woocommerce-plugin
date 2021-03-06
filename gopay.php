<?php

/**
 * @link              www.wearego.com/pay
 * @since             1.0.0
 * @package           GoPay
 *
 * @wordpress-plugin
 * Plugin Name:       GoPay
 * Plugin URI:        www.wearego.com/pay
 * Description:       Payment gateway plug-in to connect a Wordpress woocommerce web shop to GOPay
 * Version:           1.0.0
 * Author:            Genuinely Optimistic
 * Author URI:        www.wearego.com
 * License:           MIT
 * License URI:       https://github.com/denzildoyle/gopay-woocommerce-plugin/blob/master/LICENSE
 * Text Domain:       GOPay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'GOPay', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-gopay-activator.php
 */
function activate_gopay() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gopay-activator.php';
	GOpay_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-gopay-deactivator.php
 */
function deactivate_gopay() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gopay-deactivator.php';
	GOpay_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_gopay' );
register_deactivation_hook( __FILE__, 'deactivate_gopay' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-gopay.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *lo
 * @since    1.0.0
 */
function run_gopay() {

	$plugin = new GOpay();
	$plugin->run();

}
run_gopay();


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_gopayment_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_GOPayment';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_gopayment_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_gopayment_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gopay' ) . '">' . __( 'Configure', 'wc-gopay' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_gopayment_gateway_plugin_links' );


/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_GOPayment
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_gopayment_gateway_init', 11 );

function wc_gopayment_gateway_init() {

	class WC_GOPayment extends WC_Payment_Gateway {


        public static $log_enabled = true;
        public static $log = true;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'gopay';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'GOPay', 'wc-gopay' );
			$this->method_description = __( 'GOPay redirects customers to GOPay to login to their account and confirm payment.', 'wc-gopay' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  	$this->debug = $this->get_option('debug');
			self::$log_enabled = $this->debug;


			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  	add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_api_' . $this->id, array($this, 'webhook'));
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
        /**
        * Logging method
        * @param  string $message
        */
        public static function log( $message ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) ) {
                    self::$log = new WC_Logger();
                }
                self::$log->add( 'twocheckout', $message );
            }
        }


		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			$this->form_fields = apply_filters( 'wc_gopayment_form_fields', array(
				'enabled' => array(
					'title'   => __( 'Enabled/Disabled', 'wc-gopay' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable GOPay Payment', 'wc-gopay' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'wc-gopay' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gopay' ),
					'default'     => __( 'GOPay', 'wc-gopay' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'wc-gopay' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gopay' ),
					'default'     => __( '', 'wc-gopay' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gopay' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gopay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'api_key' => array(
					'title'       => __( 'API Key', 'wc-gopay' ),
					'type'        => 'password',
					'description' => __( 'This is the API Key provided by GOPay when you signed up for an account.', 'wc-gopay' ),
					'default'     => __( '', 'wc-gopay' ),
					'desc_tip'    => true,
				),
				'trans_key' => array(
					'title'       => __( 'Transaction Key', 'wc-gopay' ),
					'type'        => 'text',
					'description' => __( 'This is the Transaction Key provided by GOPay when you signed up for an account.', 'wc-gopay' ),
					'default'     => __( '', 'wc-gopay' ),
					'desc_tip'    => true,
				),
				'test_mode' => array(
					'title'   => __( 'Enable/Disable Test Mode', 'wc-gopay' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Test Mode', 'wc-gopay' ),
					'default' => 'yes'
				)
			));
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	    public function receipt_page( $order_id) {
	        echo '<p>'.__('Thank you for your order, please click the button below to pay with PayU.', 'mrova').'</p>';
	        // echo $this -> generate_payu_form($order);
	    }

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;

			// Get this Order's information so that we know
			// who to charge and how much
			$order = new WC_Order( $order_id );
			
			// // checking for transiction
			// $environment = ( $this->test_mode == "yes" ) ? 'TRUE' : 'FALSE';

			// // Decide which URL to post to
			// $environment_url = ( "FALSE" == $environment ) ? 'http://localhost:90/epay/api/v1/order?testmode=true': 'http://localhost:90/epay/user/login';

			// // Mark as processing (we're awaiting the payment)
			// $order->update_status( 'processing', 'Processing payment.');

			// // Reduce stock levels
			// $order->reduce_order_stock();
			
			// // Remove cart
			// WC()->cart->empty_cart();
			
			// This is where the fun stuff begins
			$payload = array(
				//API Info
				"order_id"				=> $order_id,
				"merchant_id"			=> 1,
				// "tran_key"           	=> $this->trans_key,
				"tran_key"           	=> "dfsdfsdfsdf",

				// Order total
				"amount"             	=> (  WC()->version < '2.7.0' ) ? $order->order_total : $order->get_total(),
				
				"type"               	=> 'AUTH_CAPTURE',
				"invoice_num"        	=> str_replace( "#", "", (  WC()->version < '2.7.0' ) ? $order->order_number: $order->get_order_number() ),
				// "test_request"       	=> $environment,
				"test_request"       	=> "sdfsdfsdfdsffdsfd",
				"delim_char"         	=> '|',
				"encap_char"         	=> '',
				"delim_data"         	=> "TRUE",
				"relay_response"     	=> "FALSE",
				"method"             	=> "CC",
				
				// Billing Information
				"first_name"         	=> ( WC()->version < '2.7.0' ) ? $order->billing_first_name: $order->get_billing_first_name(),
				"last_name"          	=> ( WC()->version < '2.7.0' ) ? $order->billing_last_name: $order->get_billing_last_name(),
				"address"            	=> ( WC()->version < '2.7.0' ) ? $order->billing_address_1: $order->get_billing_address_1(),
				"city"              	=> ( WC()->version < '2.7.0' ) ? $order->billing_city: $order->get_billing_city(),
				"state"              	=> ( WC()->version < '2.7.0' ) ? $order->billing_state: $order->get_billing_state(),
				"zip"                	=> ( WC()->version < '2.7.0' ) ? $order->billing_postcode: $order->get_billing_postcode(),
				"country"            	=> ( WC()->version < '2.7.0' ) ? $order->billing_country: $order->get_billing_country(),
				"phone"              	=> ( WC()->version < '2.7.0' ) ? $order->billing_phone: $order->get_billing_phone(),
				"email"              	=> ( WC()->version < '2.7.0' ) ? $order->billing_email: $order->get_billing_email(),
				
				// Shipping Information
				"ship_to_first_name" 	=> ( WC()->version < '2.7.0' ) ? $order->shipping_first_name: $order->get_shipping_first_name(),
				"ship_to_last_name"  	=> ( WC()->version < '2.7.0' ) ? $order->shipping_last_name: $order->get_shipping_last_name(),
				"ship_to_company"    	=> ( WC()->version < '2.7.0' ) ? $order->shipping_company: $order->get_shipping_company(),
				"ship_to_address"    	=> ( WC()->version < '2.7.0' ) ? $order->shipping_address_1: $order->get_shipping_address_1(),
				"ship_to_city"       	=> ( WC()->version < '2.7.0' ) ? $order->shipping_city: $order->get_shipping_city(),
				"ship_to_country"    	=> ( WC()->version < '2.7.0' ) ? $order->shipping_country: $order->get_shipping_country(),
				"ship_to_state"      	=> ( WC()->version < '2.7.0' ) ? $order->shipping_state: $order->get_shipping_state(),
				"ship_to_zip"        	=> ( WC()->version < '2.7.0' ) ? $order->shipping_postcode: $order->get_shipping_postcode(),
				
				// Some Customer Information
				"cust_id"            	=> ( WC()->version < '2.7.0' ) ? $order->user_id: $order->get_user_id(),
				"customer_ip"        	=> $_SERVER['REMOTE_ADDR'],
			);

			// Return thankyou redirect 
			return array(
				'result' 	=> 'success',
				'redirect'	=> 'http://localhost:90/epay/'
			);

			// $response = wp_remote_post( $environment_url, array(
			// $response = wp_remote_post( 'http://localhost:90/epay/api/v1/order?testmode=true', array(
			// 	'method'    => 'POST',
			// 	'body'      => http_build_query( $payload ),
			// 	'timeout'   => 90,
			// 	'sslverify' => false,
			// ));

			// if ( is_wp_error( $response ) ) 
			// 	throw new Exception('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.');

			// if ( empty( $response['body'] ) )
			// 	throw new Exception('Response was empty.') );
		
			// // Retrieve the body's resopnse if no errors found
			// $response_body = wp_remote_retrieve_body( $response );

			// // Parse the response into something we can read
			// foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
			// 	$resp = explode( "|", $line );
			// }

			// // Get the values we need
			// $r['response_code']             = $resp[0];
			// $r['response_sub_code']         = $resp[1];
			// $r['response_reason_code']      = $resp[2];
			// $r['response_reason_text']      = $resp[3];

			// // Test the code to know if the transaction went through or not.
			// // 1 or 4 means the transaction was a success
			// if ( ( $r['response_code'] == 1 ) || ( $r['response_code'] == 4 ) ) {
			// Payment has been successful
			// $order->update_status( 'completed', 'GOPay payment completed.');

			// Mark order as Paid
			// $order->payment_complete();

			// Empty the cart (Very important step)
			// $woocommerce->cart->empty_cart();

				// Redirect to thank you page
				// return array(
				// 	'result'   => 'success',
				// 	'redirect' => $this->get_return_url( $order ),
				// );
			// } else {
			// 	// Transaction was not succesful
			// 	// Add notice to the cart
			// 	wc_add_notice( $r['response_reason_text'], 'error' );
			// 	// Add note to the order for your reference
			// 	$order->add_order_note( 'Error: '. $r['response_reason_text'] );
			// }
			

		}

				/**
		 *
		 * @access public
		 * @return void
		 */
		function webhook() { 

      $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
      $order = wc_get_order( $order_id );
      $order->payment_complete();
      wc_reduce_stock_levels($order_id);

      return "true";

			// if($_GET['reason']){
			// 	wp_redirect( WC_Cart::get_cart_url());
			// 	wc_add_notice( $_GET['reason'], 'error' );
			// 	exit;
			// }

			// global $woocommerce;

			// @ob_clean();

			// $wc_order_id 	= $_REQUEST['order_id'];

			// $compare_string = $_REQUEST['order_id'].$_REQUEST['total'].$this->api_key;

			// $compare_hash1 = md5($compare_string);


			// $compare_hash2 = $_REQUEST['hash'];
			// if ($compare_hash1 != $compare_hash2) {
			// 	wp_redirect( WC_Cart::get_cart_url());
			// 	wc_add_notice("Wipay Hash Mismatch... check your secret word.", 'error' );
			// 	exit;
			// } else {
			// 	$wc_order 	= new WC_Order( absint( $wc_order_id ) );
			// 	// Mark order complete
			// 	$wc_order->payment_complete();
			// 	// Empty cart and clear session
			// 	$woocommerce->cart->empty_cart();
			// 	wp_redirect( $this->get_return_url( $wc_order ) );
			// 	wc_add_notice('WIPAY ORDER NUMBER ' . $_REQUEST['order_number'] , 'success' );
			// 	exit;
			// }
		}
  } // end \WC_GOPayment class
}