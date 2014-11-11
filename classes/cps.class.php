<?php
/**
 * Cornerstone Payment Systems Payment Gateway
 *
 * Provides a Cornerstone Payment Systems (Solidstone) Payment Gateway.
 *
 * @class 		woocommerce_CPS
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Timothy Snowden (modified WooThemes Payfast plugin)
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
 * payment_fields()
 * generate_CPS_form()
 * process_payment()
 * receipt_page()
 * setup_constants()
 * log()
 * validate_ip()
 * amounts_equal()
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
		$this->merchant_id = $this->settings['merchant_id'];
		$this->merchant_key = $this->settings['merchant_key'];
		$this->url = 'https://checkout.cornerstone.cc/';
		$this->title = $this->settings['title'];

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			$this->add_testmode_admin_settings_notice();
			$this->url = 'https://give.cornerstone.cc/The+Page+of+Infinite+Testing/checkout';
		}

		$this->response_url	= add_query_arg( 'wc-api', 'WC_Gateway_CPS', home_url( '/' ) );

		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_CPS', array( $this, 'receipt_page' ) );

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
							'testmode' => array(
											'title' => __( 'CPS Solidstone Sandbox', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'Place the payment gateway in development mode.', 'woothemes' ),
											'default' => 'yes'
										),
							'merchant_id' => array(
											'title' => __( 'Merchant ID', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This is the merchant ID assigned to you by Cornerstone.', 'woothemes' ),
											'default' => ''
										),
							'merchant_key' => array(
											'title' => __( 'Merchant Key', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This is the merchant key assigned to you by Cornerstone.', 'woothemes' ),
											'default' => ''
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
     * @since 1.0.0
     */
    function add_testmode_admin_settings_notice () {
    	$this->form_fields['merchant_id']['description'] .= ' <strong>' . __( 'CPS Sandbox Merchant ID currently in use.', 'woothemes' ) . ' ( 10000100 )</strong>';
    	$this->form_fields['merchant_key']['description'] .= ' <strong>' . __( 'CPS Sandbox Merchant Key currently in use.', 'woothemes' ) . ' ( 46f0cd694581a )</strong>';
    } // End add_testmode_admin_settings_notice()

    /**
	 * Get the plugin URL
	 *
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
	 * There are no payment fields for PayFast, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } // End payment_fields()

	/**
	 * Generate the CPS button link.
	 *
	 * @since 1.0.0
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
	        'callback' => $this->get_return_url( $order ),

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
	 * amounts_equal()
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @author Jonathan Smit
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
	 */
	function amounts_equal ( $amount1, $amount2 ) {
		if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > CPS_EPSILON ) {
			return( false );
		} else {
			return( true );
		}
	} // End amounts_equal()

} // End Class