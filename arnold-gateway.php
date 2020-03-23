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
defined('ABSPATH') or exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// define plugin url
if (!defined('ARNOLDGATEWAY_URL')) {
    define('ARNOLDGATEWAY_URL', plugins_url('', __FILE__) . '/');
}

function wc_arnold_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Arnold_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_arnold_add_to_gateways');

// add plugin page links

function wc_arnold_gateway_plugin_links($links)
{

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=arnold_gateway') . '">' . __('Configure', 'wc-arnold-gateway') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_arnold_gateway_plugin_links');

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'arnold_init_gateway_class', 11);
function arnold_init_gateway_class()
{

    class WC_Arnold_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        private $publishable_key, $private_key, $encodedstr;

        public function __construct()
        {

            $this->id = 'arnold_gateway'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = __('Namisi Arnold Gateway', 'wc-arnold-gateway');
            $this->method_description = __('Powered By Namisi Arnold Paul, Check out using MTN MOMO PAY, supports MTN MObile Money', 'wc-arnold-gateway'); // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');
            $this->encodedstr = $this->get_option('encodedstr')

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

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
                ),
                'encodedstr' => array(
                    'title'       => 'Your Base 64 Encoded String',
                    'type'        => 'password'
                )
            );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {

            // commenting out default credit card form
            /*
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
            */
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

        public function gen_uuid()
        {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),

                // 16 bits for "time_mid"
                mt_rand(0, 0xffff),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000,

                // 48 bits for "node"
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );
        }

        public function process_payment($order_id)
        {

            global $woocommerce;

            // we need it to get any order detailes
            $order = wc_get_order($order_id);

            // commenting out Http_Request2, gonna harness wp_get/remote_post
            // $request = new Http_Request2('https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay');
            // $url = $request->getUrl();

            $randomID = $this->gen_uuid();

            $url = 'https://sandbox.momodeveloper.mtn.com/collection/v1_0/token';
            $url2 = 'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay';
            $encodedstr = $this->encodedstr;// enter b64 string;
            $subkey = $this->publishable_key;
            //$order = wc_get_order( $order_id );
            $order = new WC_Order($order_id);
            $order_data = $order->get_data();
            $total_amount = $order_data['total'];
            $items = $order->get_items();
            $itemName;
            $phoneno = $order_data['billing']['phone'];
            $phone_number;
            $firstdig = substr($phoneno, 0, 1);
            $firstdigits = substr($phoneno, 0, 3);

            if ($firstdig === '0') {
                $phone_number = preg_replace('/^0/', '256', $phoneno);
            } elseif ($firstdigits === '256') {
                $phone_number = $phoneno;
            } else {
                $error_message = 'Please provide a valid MTN number (should start with 07 or 256)';
                throw new Exception(__('Number invalid. ' . $error_message, 'wc-arnold-gateway'));
            }
            // get order name
            foreach ($items as $item) {
                $itemName = $item['name'];
            };

            // get tokens
            $response = wp_remote_post(
                $url,
                array(

                    "method" => "POST",
                    "timeout" => 45,
                    "redirection" => 5,
                    "httpversion" => '1.0',
                    "blocking" => true,
                    "headers" => array(
                        "Ocp-Apim-Subscription-Key" => $subkey,
                        "Authorization" => "Basic " . $encodedstr,
                        "Content-Type" => "application/json"
                    )

                )
            );

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                throw new Exception(__('There is a problem creating token. ' . $error_message, 'wc-arnold-gateway'));
            }

            $tknobj = json_decode($response['body']);
            $thetoken = $tknobj->{'access_token'};
            $auth = "Bearer " . $thetoken;
            // process payment
            $payresponse = wp_remote_post($url2, array(
                "method" => "POST",
                "timeout" => 45,
                "redirection" => 5,
                "httpversion" => '1.0',
                "blocking" => true,
                "headers" => array(
                    "Ocp-Apim-Subscription-Key" => $subkey,
                    "Authorization" => $auth,
                    "X-Reference-Id" => $randomID,
                    "X-Target-Environment" => "sandbox",
                    "X-Callback-Url" => "call back url for your website",
                    "cache-control" => "no-cache",
                    "Content-Type" => "application/json"
                ),
                "body" => json_encode(array(
                    "amount" => $total_amount,
                    "currency" => "EUR",
                    "externalId" => 'XXX',
                    "payer" => array(
                        "partyIdType" => "MSISDN",
                        "partyId" => $phone_number
                    ),
                    "payerMessage" => "message",
                    "payeeNote" => "string"
                ), JSON_FORCE_OBJECT)
            ));
            if (is_wp_error($payresponse)) {
                $error_message = $payresponse->get_error_message();
                throw new Exception(__('There is a problem processing payment. ' . $error_message, 'wc-arnold-gateway'));
            }

            // check on payment
            $getresponse = wp_remote_get("https://ericssonbasicapi1.azure-api.net/collection/v1_0/requesttopay/" . $randomID, array(
                "headers" => array(
                    "Ocp-Apim-Subscription-Key" => $subkey,
                    "Authorization" => $auth,
                    "X-Reference-Id" => $randomID,
                    "X-Target-Environment" => "sandbox",
                    "Content-Type" => "application/json"
                )
            ));
            if (is_wp_error($payresponse)) {
                $error_message = $payresponse->get_error_message();
                throw new Exception(__('There is a problem processing payment. ' . $error_message, 'wc-arnold-gateway'));
            }

            $getrespobj = json_decode($getresponse['body']);
            $getrespstat = $getrespobj->{'status'};
            if ($getrespstat === 'FAILED') {
                // Get status message
                $error_message = $getrespobj->{'reason'};;
                throw new Exception(__($error_message . ' Make sure phone number is registered with MTN Mobile Money and you have enough credit ', 'wc-arnold-gateway'));
            }

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaits payment, refID: ' . $randomID, 'wc-arnold-gateway'));

            //$order->payment_complete();

            // Reduce stock levels
            // $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
    }
}
