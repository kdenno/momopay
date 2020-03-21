<?php
/*
 * Plugin Name: WooCommerce Arnold Payment Gateway
 * Plugin URI: https://namisiarnold.com/woocommerce/payment-gateway-plugin.html
 * Description: Take MTN Mobile Money payments on your store.
 * Author: NAMISI ARNOLD PAUL
 * Author URI: http://namisiarnold.com
 * Version: 1.0.2
 *
 */
 
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */


add_filter( 'woocommerce_payment_gateways', 'arnold_add_gateway_class' );
function arnold_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Arnold_Gateway'; // your class name is here
	return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'arnold_init_gateway_class' );
function arnold_init_gateway_class() {
 
	class WC_Arnold_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
          public function __construct() {
 
            $this->id = 'arnold'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Namisi Arnold Gateway';
            $this->method_description = 'Powered By Namisi Arnold Paul, Check out using MTN MOMO PAY, supports MTN MObile Money'; // will be displayed on the options page
         
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
         
            // Method with all the options fields
            $this->init_form_fields();
         
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
         
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
         }
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
          public function init_form_fields(){
 
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Namisi Arnold Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Namisi Arnold Paul',
                    'type'        => 'text',
                    'description' => 'Powered by Namisi Arnold Paul, Supports MTN Mobile Money',
                    'default'     => 'MTN Mobile Money Number',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'Powered by Namisi Arnold Paul, Supports MTN Mobile Money.',
                    'default'     => 'MTN Mobile Money Number.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
        }
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' Powered by NAMISI ARNOLD PAUL, checkout using MTN Mobile Money';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
         
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
         
            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_credit_card_form_start', $this->id );
         
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>MTN Mobile Money Number <span class="required">*</span></label>
                <input id="misha_ccNo" name = "mobile_number" type="text" autocomplete="off">
                </div>
                <div class="clear"></div>
                
                ';
         
            do_action( 'woocommerce_credit_card_form_end', $this->id );
         
            echo '<div class="clear"></div></fieldset>';
         
        }
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
 
		/*
 		 * Fields validation, more in Step 5
		 */
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */

        public function process_payment( $order_id ) {
 
            global $woocommerce;
         
            // we need it to get any order detailes
            $order = wc_get_order( $order_id );
         

            // This sample uses the Apache HTTP client from HTTP Components (http://hc.apache.org/httpcomponents-client-ga/)
           // require_once 'HTTP/Request2.php';

            $request = new Http_Request2('https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay');
            $url = $request->getUrl();

            function gen_uuid() {
                return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    // 32 bits for "time_low"
                    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            
                    // 16 bits for "time_mid"
                    mt_rand( 0, 0xffff ),
            
                    // 16 bits for "time_hi_and_version",
                    // four most significant bits holds version number 4
                    mt_rand( 0, 0x0fff ) | 0x4000,
            
                    // 16 bits, 8 bits for "clk_seq_hi_res",
                    // 8 bits for "clk_seq_low",
                    // two most significant bits holds zero and one for variant DCE1.1
                    mt_rand( 0, 0x3fff ) | 0x8000,
            
                    // 48 bits for "node"
                    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
                );
            }

            $headers = array(
                // Request headers
                'Authorization' => '',
                'X-Callback-Url' => 'https://subulaug.com',
                'X-Reference-Id' => gen_uuid(),
                'X-Target-Environment' => '',
                'Content-Type' => 'application/json',
                'Ocp-Apim-Subscription-Key' => 'b4d4c22a538147068153346505f66ada',
                
            );

            $request->setHeader($headers);

            $parameters = array(
                // Request parameters
            );

            $url->setQueryVariables($parameters);

            $request->setMethod(HTTP_Request2::METHOD_POST);

            // Request body
            $request->setBody('
            {
                "amount": "string",
                "currency": "string",
                "externalId": "string",
                "payer": {
                "partyIdType": "MSISDN",
                "partyId": "0775307641"
                },
                "payerMessage": "Blob",
                "payeeNote": "string"
                
            }');

            try
            {
                $response = $request->send();
                echo $response->getBody();
                
                $body = json_decode( $response->getBody(), true );
                    
                // it could be different depending on your payment processor
                if ( $body['response']['status'] == 'SUCCESSFUL' ) {

                // we received the payment
                $order->payment_complete();
                $order->reduce_order_stock();

                // some notes to customer (replace true with false to make it private)
                $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

                // Empty cart
                $woocommerce->cart->empty_cart();

                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $order )
                );

                }
                else {
                    wc_add_notice(  'Please try again.', 'error' );
                    
                    
                    return;
                }
            }
            catch (HttpException $ex)
            {
                echo $ex;
            }
         
        }
		
    }
}