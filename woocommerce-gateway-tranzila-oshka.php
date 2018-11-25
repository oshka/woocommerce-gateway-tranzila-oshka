<?php
/*
Plugin Name: WooCommerce Tranzila Iframe Gateway
Plugin URI: https://oshka.000webhostapp.com/
Description: WooCommerce Plugin for accepting payment through Tranzila Payment Gateway USong Iframe.
Version: 1.0.3
Author: Olha Babchenko
Author URI: https://oshka.000webhostapp.com/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_gateway_tranzila_iframe_init', 0);

function register_session_tranzila() {
    if (!session_id())
        session_start();
}

add_action('init', 'register_session_tranzila');

function woocommerce_gateway_tranzila_iframe_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Localisation
	 */
	load_plugin_textdomain('wc-gateway-tranzila-iframe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
	/**
 	 * Gateway class
 	 */
	class WC_tranzila_iframe_Gateway extends WC_Payment_Gateway {
		
		public function __construct(){
			
			
			$this -> id = 'tranzila-iframe';
			$this -> title = "Tranzila Payment Gateway";
			$this -> method_title = "Tranzila Payment Gateway";
			$this -> method_description = "Pay securely with an Israeli credit card using Tranzila";
			$this -> has_fields = false;
			$this -> icon = "/wp-content/plugins/woocommerce-gateway-tranzila-oshka/logo.png";
			$this -> init_form_fields();
			$this -> init_settings();
			
			$this -> title = $this -> get_option('title');
			$this -> description = $this -> get_option('description');
			$this -> merchant_id = $this -> get_option('merchant_id');
			$_SESSION['tranzila_terminal'] = $this->get_option('terminal_name');
			
			if(isset($_POST['post_data'])) {
				$order_id_alg=strstr($_POST['post_data'], '_wp_http_referer');
				$order_id_alg=strstr($order_id_alg, 'order_id%3D');
				$order_id_post = str_replace('order_id%3D', "", $order_id_alg );
				
				if($order_id_post!="" )  {
					global $woocommerce;
					$order_id = $order_id_post;
					$order = new WC_Order( $order_id );
					//$this->order_button_text = $order_id_post;
					$this->order_button_text = " ";
					
					// Get the product list
					global $woocommerce; 	
					$items = $woocommerce->cart->get_cart();
					$i = 0;
					$len = count($items);	 	
					foreach($items as $item => $values) { 
						$_product = $values['data']->post;
						$description = $values['quantity']. " x ". $_product->post_title;
						if ($i != $len - 1) $description .= ", ";
						$i++;
					}
					$order_key = get_post_meta( $order_id, '_order_key', true);
			
					$tranzila_iframe_params = array(
					'sum' => $order -> get_total(),
					'pdesc' => urlencode($description),
					'contact' => urlencode($order -> billing_first_name." ".$order -> billing_last_name),
//					'company' => "Personal",
					'email' => urlencode($order -> billing_email),
					'phone' => urlencode($order -> billing_phone),
					'fax' => "",
					'address' =>  urlencode($order -> billing_address_1." ".$order -> billing_address_2),
					'city' =>  urlencode($order -> billing_city),
					'remarks'=>  urlencode($order-> customer_orer_notes),
					'lang' => 'il',
					'currency' => '1',
//					'myid' => '',
//					'TranzilaToken' => $_SESSION['tranzila_token'],
					'order_id' => $order_id,
					'orderid' => $order_id,
					'fail_url_address' => site_url().'/payment-failed/',
					'success_url_address' => site_url().'/checkout/order-received/'.$order_id.'/?key='.$order_key,
					'cred_type' => '1',
					'nologo' => '1',
					'trBgColor' => 'dfdcde',
					'trTextColor' => '000'
					);
					$iframe_http_query = http_build_query($tranzila_iframe_params,'', '&amp;');

					$tranzila_iframe_html_code='<br><iframe width="350" height="260" frameborder="0" border="0" scrolling="no" target="_top" src="https://direct.tranzila.com/' . $this->get_option('terminal_name') . '/iframe.php?'.$iframe_http_query.'"></iframe>';

					$this -> description = $this -> get_option('description').$tranzila_iframe_html_code;
				} else {
					$this->order_button_text = __( 'המשך לדף הסליקה המאובטח', 'woocommerce' );
				}
			
			} else {
				$this->order_button_text = __( 'המשך לדף הסליקה המאובטח', 'woocommerce' );
			}
			
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		   
		}
		   
		public function init_form_fields(){
		 
		       $this->form_fields  = array(
		                'enabled' => array(
		                    'title' => __('Enable/Disable', 'oshka'),
		                    'type' => 'checkbox',
		                    'label' => __('Enable Tranzila Payment Module In Iframe', 'oshka'),
		                    'default' => 'no'),
		                'title' => array(
		                    'title' => __('Title:', 'oshka'),
		                    'type'=> 'text',
		                    'description' => __('This controls the title which the user sees during checkout.', 'oshka'),
		                    'default' => __('Tranzila', 'oshka')),
		                'description' => array(
		                    'title' => __('Description:', 'oshka'),
		                    'type' => 'textarea',
		                    'description' => __('This controls the description which the user sees during checkout.', 'oshka'),
		                    'default' => __('Pay securely with an Israeli credit card using Tranzila.', 'oshka')),
                        'terminal_name' => array(
                            'title' => __('Terminal Name', 'oshka'),
                            'type' => 'text',
                            'description' =>  __('The Tranzila Terminal Name', 'oshka'),
                        )
		               
		            );
		    }
		 
		public function admin_options(){
		        echo '<h3>'.__('Tranzila Payment Gateway', 'oshka').'</h3>';
		        echo '<p>'.__('This custom payment gateway was made by Oshka Web Development').'</p>';
		        echo '<table class="form-table">';
		        // Generate the HTML For the settings form.
		        $this -> generate_settings_html();
		        echo '</table>';
		 
		    }
		   
		function payment_fields(){
				// Check if tranzila has been used
			echo $this->description;
		}
		
		
		
				 
		 /**
		 	 * Process the payment and return the result
		 	 *
		 	 * @access public
		 	 * @param int $order_id
		 	 * @return array
		 	 */
	 	function process_payment( $order_id ) {
	 		global $woocommerce;
	 		$order = new WC_Order( $order_id );
	 		$_SESSION['tranzila_token'] = $this->get_return_url( $order );
	 		$_SESSION['encryption-key'] = mt_rand();
	 		// Get the product list
	 		global $woocommerce; 	
	 		$items = $woocommerce->cart->get_cart();
	 		$i = 0;
	 		$len = count($items);	 	
	 		foreach($items as $item => $values) { 
	 			$_product = $values['data']->post;
	 			$description = $values['quantity']. " x ". $_product->post_title;
	 			if ($i != $len - 1) $description .= ", ";
	 			$i++;
	 		}
			$order_key = get_post_meta( $order_id, '_order_key', true);
			$tranzila_data = array(
				'sum' => $order -> get_total(),
				'pdesc' => $description,
				'contact' => $order -> billing_first_name." ".$order -> billing_last_name,
				'company' => "Personal",
				'email' => $order -> billing_email,
				'phone' => $order -> billing_phone,
				'fax' => "",
				'address' => $order -> billing_address_1." ".$order -> billing_address_2,
				'city' => $order -> billing_city,
				'remarks'=> $order-> customer_orer_notes,
				'currency' => '1',
				'lang' => 'il',
				'myid' => '',
				'TranzilaToken' => $_SESSION['tranzila_token'],
				'orderid' => $order_id,
				'fail_url_address' => site_url().'/payment-failed/',
				'success_url_address' => site_url().'/checkout/order-received/'.$order_id.'/'.$order_key,
				'cred_type' => '1',
				'trBgColor' => 'dfdcde',
				'trTextColor' => '000'
				);
				 		
	 			
	 			return array(
	 				'result' 	=> 'success',
	 				'redirect'	=> "https://ctrlprint.shop/checkout/?tranzila=sendpayment&order_id=".$order_id);

	 
	 	}
	 	
		 
	}
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_tranzila_iframe($methods) {
		$methods[] = 'WC_tranzila_iframe_Gateway';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_tranzila_iframe' );
} 





/*add js to header*/
function top_location_js() {?>
	<script type="text/javascript">
		if (top.location!= self.location) {
			top.location = self.location.href;
			self.window.stop();
		}
	</script>
<?php }
// Add hook for front-end <head></head>
add_action( 'wp_head', 'top_location_js' );
