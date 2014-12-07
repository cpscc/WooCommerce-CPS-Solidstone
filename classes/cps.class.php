<?php
/**
 * Cornerstone Payment Systems Payment Gateway
 *
 * Provides a Cornerstone Payment Systems (Solidstone) Payment Gateway.
 *
 * @class 		woocommerce_CPS
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Timothy Snowden (based on Woo code)
 *
 *
 * Table Of Contents
 *
 * __construct()
 * init_form_fields()
 * add_testmode_admin_settings_notice()
 * plugin_url()
 * is_valid_for_use()
 * admin_options()
 * generate_CPS_form()
 * process_payment()
 * receipt_page()
 * setup_constants()
 * log()
 * validate_ip()
 * CPS_callback_handler()
 */
class WC_Gateway_CPS extends WC_Payment_Gateway {

	public $version = '1.0';

	public function __construct() {
        global $woocommerce;
        $this->id			= 'CPS';
        $this->method_title = __( 'CPS / Solidstone', 'woothemes' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;
        $this->debug_email 	= get_option( 'admin_email' );

		// Setup available currency codes.
		$this->available_currencies = array( 'USD' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup default merchant data.
		$this->url = $this->settings['live_url'];
		$this->title = $this->settings['title'];

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			$this->add_testmode_admin_settings_notice();
			$this->url = 'https://give.cornerstone.cc/The+Page+of+Infinite+Testing/checkout';
		}

		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_CPS', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_wc_gateway_cps', array( $this, 'CPS_callback_handler' ) );
        
		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }
    
	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woothemes' ),
											'label' => __( 'Enable CPS Solidstone', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woothemes' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
    										'default' => __( 'Cornerstone Payment Systems', 'woothemes' )
    									),
							'description' => array(
											'title' => __( 'Description', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
											'default' => ''
										),
    						'live_url' => array(
    										'title' => __( 'Live URL', 'woothemes' ),
    										'type' => 'text',
    										'description' => __( 'This is the payment gateway URL provided to you by Cornerstone.', 'woothemes' ),
    										'default' => 'https://give.cornerstone.cc/The+Page+of+Infinite+Testing/checkout'
    									),
    						'sandbox_url' => array(
    										'title' => __( 'Sandbox URL', 'woothemes' ),
    										'type' => 'text',
    										'description' => __( 'This is the sandbox payment gateway URL provided to you by Cornerstone for testing.', 'woothemes' ),
    										'default' => 'https://give.cornerstone.cc/The+Page+of+Infinite+Testing/checkout'
    									),
							'testmode' => array(
											'title' => __( 'CPS Solidstone Sandbox', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'Place the payment gateway in development mode.', 'woothemes' ),
											'default' => 'yes'
										),
							'send_debug_email' => array(
											'title' => __( 'Send Debug Emails', 'woothemes' ),
											'type' => 'checkbox',
											'label' => __( 'Send debug e-mails for transactions through the CPS gateway (sends on successful transaction as well).', 'woothemes' ),
											'default' => 'yes'
										),
							'debug_email' => array(
											'title' => __( 'Who Receives Debug E-mails?', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'The e-mail address to which debugging error e-mails are sent when in test mode.', 'woothemes' ),
											'default' => get_option( 'admin_email' )
										)
							);

    } // End init_form_fields()

    /**
     * add_testmode_admin_settings_notice()
     *
     * Add a notice to the merchant_key and merchant_id fields when in test mode.
     *
     * @author Woo & Timothy Snowden
     * @since 1.0.0
     */
    function add_testmode_admin_settings_notice () {
    	$this->form_fields['title']['description'] .= ' <strong>' . __( 'CPS currently in test mode.', 'woothemes' ) . '</strong>';
    } // End add_testmode_admin_settings_notice()

    /**
	 * Get the plugin URL
	 *
     * @author Woo
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     * @author Woo
     */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $is_available_currency && $this->enabled == 'yes' )
			$is_available = true;

        return $is_available;
	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
     * @author Woo & Timothy Snowden
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}

    	?>
    	<h3><?php _e( 'CPS', 'woothemes' ); ?></h3>
    	<p><?php printf( __( 'CPS Solidstone works by sending the user to %sCPS\' Solidstone gateway%s to enter their payment information.', 'woothemes' ), '<a href="https://github.com/CPScc/wiki/blob/master/checkout-api.textile">', '</a>' ); ?></p>

    	<?php
    	if ( 'USD' == get_option( 'woocommerce_currency' ) ) {
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table--><?php
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong> <?php echo sprintf( __( 'Choose United States dollars as your store currency in <a href="%s">Pricing Options</a> to enable the CPS Gateway.', 'woocommerce' ), admin_url( '?page=woocommerce&tab=catalog' ) ); ?></p></div>
		<?php
		} // End check currency
		?>
    	<?php
    } // End admin_options()

	/**
	 * Generate the CPS button link.
	 *
	 * @since 1.0.0
     * @author Woo & Timothy Snowden
	 */
    public function generate_CPS_form( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

		$shipping_name = explode(' ', $order->shipping_method);

		// Construct variables for post
	    $this->data_to_send = array(
	        // Merchant details
	        'merchant_id' => $this->settings['merchant_id'],
	        'merchant_key' => $this->settings['merchant_key'],
	        'callback' => str_replace( 'https:', 'http:', add_query_arg( 'orderid', $order_id, add_query_arg( 'wc-api', 'WC_Gateway_CPS', home_url( '/' ) ) ) ),

	        // Item details
	        'memo[Order No. ]' => $order->get_order_number(),
	    	'memo[Description]' => sprintf( __( 'New order from %s', 'woothemes' ), get_bloginfo( 'name' ) ),
	        'amount' => $order->order_total,
	    	'name' => get_bloginfo( 'name' ) .' purchase, Order ' . $order->get_order_number(),
            'oneitem' => true,
	   	);

	   	// Override merchant_id and merchant_key if the gateway is in test mode.
	   	if ( $this->settings['testmode'] == 'yes' ) {
	   		$this->data_to_send['merchant_id'] = '10000100';
	   		$this->data_to_send['merchant_key'] = '46f0cd694581a';
	   	}

		$CPS_args_array = array();

		foreach ($this->data_to_send as $key => $value) {
			$CPS_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
		}

		return '<form action="' . $this->url . '" method="post" id="CPS_payment_form">
				' . implode('', $CPS_args_array) . '
				<input type="submit" class="button-alt" id="submit_CPS_payment_form" value="' . __( 'Pay via Cornerstone', 'woothemes' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woothemes' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to Cornerstone to make payment.', 'woothemes' ) . '",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    }
							});
						jQuery( "#submit_CPS_payment_form" ).click();
					});
				</script>
			</form>';

	} // End generate_CPS_form()

	/**
	 * Process the payment and return the result.
	 *
     * @author Woo
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);

	}

	/**
	 * Receipt page.
	 *
	 * Display text and a button to direct the user to Cornerstone.
	 *
     * @author Woo
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Cornerstone.', 'woothemes' ) . '</p>';

		echo $this->generate_CPS_form( $order );
	} // End receipt_page()
    
	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the CPS gateway.
	 *
     * @author Woo
	 * @since 1.0.0
	 */
	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
		define( 'CPS_SOFTWARE_NAME', 'WooCommerce' );
		define( 'CPS_SOFTWARE_VER', $woocommerce->version );
		define( 'CPS_MODULE_NAME', 'WooCommerce-CPS' );
		define( 'CPS_MODULE_VER', $this->version );

		// Features
		// - PHP
		$CPSFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'CPS_CURL', '' );
		    $CPSVersion = curl_version();
		    $CPSFeatures .= ' curl '. $CPSVersion['version'] .';';
		}
		else
		    $CPSFeatures .= ' nocurl;';

		// Create user agent
		define( 'CPS_USER_AGENT', CPS_SOFTWARE_NAME .'/'. CPS_SOFTWARE_VER .' ('. trim( $CPSFeatures ) .') '. CPS_MODULE_NAME .'/'. CPS_MODULE_VER );

		// General Defines
		define( 'CPS_TIMEOUT', 15 );
		define( 'CPS_EPSILON', 0.01 );

	} // End setup_constants()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
     * @author Woo
	 * @since 1.0.0
	 */
	function log ( $message, $close = false ) {
		if ( ( $this->settings['testmode'] != 'yes' && ! is_admin() ) ) { return; }

		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/cps.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()

	/**
	 * validate_ip()
	 *
	 * Validate the IP address to make sure it's coming from CPS.
	 *
	 * @param array $data
     * @author Woo
	 * @since 1.0.0
	 */
	function validate_ip( $sourceIP ) {
	    // Variable initialization
	    $validHosts = array(
	        'give.cornerstone.cc/', //sandbox environment
	        'checkout.cornerstone.cc', //live environment
	        );

	    $validIps = array();

	    foreach( $validHosts as $CPSHostname ) {
	        $ips = gethostbynamel( $CPSHostname );

	        if( $ips !== false )
	            $validIps = array_merge( $validIps, $ips );
	    }

	    // Remove duplicates
	    $validIps = array_unique( $validIps );

	    $this->log( "Valid IPs:\n". print_r( $validIps, true ) );

	    if( in_array( $sourceIP, $validIps ) ) {
	        return( true );
	    } else {
	        return( false );
	    }
	} // End validate_ip()

    /**
	 * CPS_callback_handler()
	 *
	 * Checks the callback URL from the CPS payment system and
     * marks the order accordingly. It also notifies the user accordingly.
	 *
	 * @author Timothy Snowden (modified from Woo Paypal code?)
     * @since 1.0.0
	 */
    function CPS_callback_handler() {
        
        $order_id = preg_replace("/[^0-9]/", "", $_GET['orderid']);
        $order = new WC_Order($order_id);
        $order_status = preg_replace("/[^a-zA-Z0-9\s]/", "", $_GET['status']);
        $status_msg = preg_replace("/[^a-zA-Z0-9\s]/", "", $_GET['message']);

        if ( isset($order_status) ) {
            switch ( strtolower( $order_status ) ) {
                case 'approved' :
                    // Payment completed
                    $order->add_order_note( __( 'Payment completed via CPS.', 'woothemes' ) );
                    $order->payment_complete();

                    //Redirect to WC page
                    wp_redirect( $this->get_return_url( $order ), 303 );
                break;
                
                case 'declined' :
                    // Failed order
                    $order->update_status( 'failed', sprintf(__('Payment %s via CPS. Message: %s.', 'woothemes' ), $order_status, $status_msg ) );
                    
                    //Redirect to WC page
                    wp_redirect( $this->get_return_url( $order ), 303 );
                    break;
                
                default:
                    // Hold order
                    $order->update_status( 'on-hold', sprintf(__('Payment %s via CPS.', 'woothemes' ), $order_status ) );
                    
                    // Redirect to WC page
                    wp_redirect( $this->get_return_url( $order ), 303 );
                    break;
            } // End SWITCH Statement

        } // End IF Statement

        exit;
    }
// End CPS_callback_handler()    

} // End Class