<?php
/*
Plugin Name: Payment Gateway for BA via PayPal
Plugin URI: https://wordpress.org/plugins/payment-gateway-for-ba-paypal/
Description: Integrates PayPal payments into BA Book Everything plugin
Author: Quynh Shyn
Version: 1.0.2
*/

define( 'BAPAYPAL__FILE__', __FILE__ );
define( 'BAPAYPAL_PATH', plugin_dir_path( BAPAYPAL__FILE__ ) );


require_once BAPAYPAL_PATH . 'paypal-sdk/autoload.php';

Class BAPAYPAL_PAYMENT_GATEWAY {


    public function __construct() {

        add_action( 'babe_payment_methods_init', array( $this, 'init_payment_method' ) );

        add_action( 'babe_settings_payment_method_paypal', array( $this, 'add_settings_paypal' ), 10, 3);

        if (class_exists('BABE_Settings')){
            add_filter( 'babe_sanitize_'.BABE_Settings::$option_name, array( $this, 'sanitize_settings' ), 10, 2);
        }

        add_filter( 'babe_checkout_payment_title_paypal', array( $this, 'payment_method_title'), 10, 3);

        add_filter( 'babe_checkout_payment_fields_paypal', array( $this, 'payment_method_fields_html'), 10, 3);

        add_action( 'babe_order_to_pay_by_paypal', array( $this, 'order_to_pay_paypal'), 10, 4);

        add_action( 'babe_payment_server_paypal_response', array( $this, 'paypal_server_response'), 10);

        add_filter( 'babe_refund_paypal', array( $this, 'refund_paypal'), 10, 5);


    }

    public function init_payment_method() {
        if ( ! isset( $payment_methods['paypal'] ) ) {
            BABE_Payments::add_payment_method( 'paypal', esc_html__( 'PayPal', 'payment-gateway-for-ba-paypal' ) );
        }
    }



    public static function add_settings_paypal($section_id, $option_menu_slug, $option_name) {

        add_settings_field(
            'sandbox_mode', // ID
            esc_html__('Use PayPal sandbox mode?', 'payment-gateway-for-ba-paypal'), // Title
            array( __CLASS__, 'setting_activate_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id, // Section
            array('option' => 'sandbox_mode', 'settings_name' => $option_name) // Args array
        );


        add_settings_field(
            'paypal_email', // ID
            esc_html__('E-mail for PayPal account', 'payment-gateway-for-ba-paypal'), // Title
            array( __CLASS__, 'email_paypal_field_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id,  // Section
            array('option' => 'paypal_email', 'settings_name' => $option_name) // Args array
        );

        add_settings_field(
            'paypal_api_keys_desc', // ID
            esc_html__('REST API Credentials', 'payment-gateway-for-ba-paypal'), // Title
            array( __CLASS__, 'text_api_keys_desc' ), // Callback
            $option_menu_slug, // Page
            $section_id
        );

        add_settings_field(
            'paypal_live_client_id', // ID
            esc_html__('Live Client ID', 'payment-gateway-for-ba-paypal'), // Title
            array( 'BABE_Settings_admin', 'text_field_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id,  // Section
            array('option' => 'paypal_live_client_id', 'settings_name' => $option_name) // Args array
        );

        add_settings_field(
            'paypal_live_secret', // ID
            esc_html__('Live Secret', 'payment-gateway-for-ba-paypal'), // Title
            array( 'BABE_Settings_admin', 'text_field_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id,  // Section
            array('option' => 'paypal_live_secret', 'settings_name' => $option_name) // Args array
        );

        add_settings_field(
            'paypal_test_client_id', // ID
            esc_html__('Test Client ID', 'payment-gateway-for-ba-paypal'), // Title
            array( __CLASS__ , 'client_ID_paypal_field_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id,  // Section
            array('option' => 'paypal_test_client_id', 'settings_name' => $option_name) // Args array
        );

        add_settings_field(
            'paypal_test_secret', // ID
            esc_html__('Test Secret', 'payment-gateway-for-ba-paypal'), // Title
            array( __CLASS__ , 'client_secret_paypal_field_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id,  // Section
            array('option' => 'paypal_test_secret', 'settings_name' => $option_name) // Args array
        );

        add_settings_field(
            'paypal_url_webhook', // ID
            sprintf(__('Webhook endpoint. Add it in your %s', 'payment-gateway-for-ba-paypal'), '<a href="https://developer.paypal.com/developer/applications" target="_blank">'. esc_html__('REST API settings', 'payment-gateway-for-ba-paypal').'</a>'), // Title
            array( __CLASS__, 'url_readonly_callback' ), // Callback
            $option_menu_slug, // Page
            $section_id,  // Section
            array('option' => 'paypal_url_webhook', 'settings_name' => $option_name) // Args array
        );

    }


    public static function setting_activate_callback($args){

        $check = isset(BABE_Settings::$settings[$args['option']]) ?  BABE_Settings::$settings[$args['option']] : 0;

        $checked1 = $check ? 'checked' : '';
        $checked2 = !$check ? 'checked' : '';

        echo '<p><input id="'.$args['option'].'1" name="'.$args['settings_name'].'['.$args['option'].']" type="radio" value="1" '.$checked1.'/><label for="'.$args['option'].'1">'. esc_html__('Yes', 'payment-gateway-for-ba-paypal').'</label></p>';
        echo '<p><input id="'.$args['option'].'2" name="'.$args['settings_name'].'['.$args['option'].']" type="radio" value="0" '.$checked2.'/><label for="'.$args['option'].'2">'. esc_html__('No', 'payment-gateway-for-ba-paypal').'</label></p>';

    }

    public static function email_paypal_field_callback($args){
        $add_class = isset($args['translate']) ? ' class="q_translatable"' : '';

        printf(
            '<input type="text"'.$add_class.' id="'.$args['option'].'" name="'.$args['settings_name'].'['.$args['option'].']" value="%s" />',
            isset( BABE_Settings::$settings[$args['option']] ) ? esc_attr( BABE_Settings::$settings[$args['option']]) : 'sb-gpf9p3601734@business.example.com'
        );
    }

    public static function client_ID_paypal_field_callback($args){
        $add_class = isset($args['translate']) ? ' class="q_translatable"' : '';

        printf(
            '<input type="text"'.$add_class.' id="'.$args['option'].'" name="'.$args['settings_name'].'['.$args['option'].']" value="%s" />',
            isset( BABE_Settings::$settings[$args['option']] ) ? esc_attr( BABE_Settings::$settings[$args['option']]) : 'ARIGtnaC3DsPrxza4WTWXEcaNcJRhwGaHOVaN3S-vFV-lZuT_8ze_x_fCLioclMRviCwBaxuxiVK52xP'
        );
    }

    public static function client_secret_paypal_field_callback($args){
        $add_class = isset($args['translate']) ? ' class="q_translatable"' : '';

        printf(
            '<input type="text"'.$add_class.' id="'.$args['option'].'" name="'.$args['settings_name'].'['.$args['option'].']" value="%s" />',
            isset( BABE_Settings::$settings[$args['option']] ) ? esc_attr( BABE_Settings::$settings[$args['option']]) : 'EJrYReDGzvxxr0U5RVP4BHbFcIGph-F5XfPsqY1Zz4lzoco4ZvHs89GWkY6-ZzOrTPrq6t-YyIqlshpE'
        );
    }



    public static function text_api_keys_desc(){

        printf(__( 'REST API credentials are necessary to process PayPal refunds from inside WordPress. You should <a href="%s" target="_blank">create an app</a> to receive REST API credentials for testing and live transactions.', 'payment-gateway-for-ba-paypal' ), 'https://developer.paypal.com/developer/applications');

    }

    public static function url_readonly_callback($args){

        echo '<textarea name="'.$args['settings_name'].'['.$args['option'].']" type="text">'.BABE_Payments::get_payment_server_response_page_url('paypal').'</textarea>';

    }

    public static function payment_method_title($method_title, $args, $input_fields_name){

        return $method_title;

    }

    public static function payment_method_fields_html($fields, $args, $input_fields_name){

        $fields = '
        <div class="paypal-payment-description">
           <img class="booking_payment_img" src="'. plugin_dir_url( __FILE__ ).'images/paypal-logo.png" border="0" alt="'. esc_html__('Paypal payment gateway', 'payment-gateway-for-ba-paypal').'">
           <h4>'. esc_html__( 'Continue with PayPal', 'payment-gateway-for-ba-paypal' ).'</h4>
        </div>';

        return $fields;

    }



    public static function init_settings() {
        $setting_data = [];

        $setting_data['paypal_email'] = 'sb-gpf9p3601734@business.example.com';
        $setting_data['sandbox_mode'] = 1;

        $setting_data['paypal_api'] = array(
            'live' => array(
                'client_id' => '',
                'secret' => '',
            ),
            'test' => array(
                'client_id' => 'ARIGtnaC3DsPrxza4WTWXEcaNcJRhwGaHOVaN3S-vFV-lZuT_8ze_x_fCLioclMRviCwBaxuxiVK52xP',
                'secret' => 'EJrYReDGzvxxr0U5RVP4BHbFcIGph-F5XfPsqY1Zz4lzoco4ZvHs89GWkY6-ZzOrTPrq6t-YyIqlshpE',
            ),
        );

        if (class_exists('BABE_Settings')){

            $setting_data['paypal_email'] = isset(BABE_Settings::$settings['paypal_email']) ? BABE_Settings::$settings['paypal_email'] : '';

            $setting_data['sandbox_mode'] = isset(BABE_Settings::$settings['sandbox_mode']) ? BABE_Settings::$settings['sandbox_mode'] : 0;

            $setting_data['paypal_api']['live']['client_id'] = isset(BABE_Settings::$settings['paypal_live_client_id']) ? BABE_Settings::$settings['paypal_live_client_id'] : '';

            $setting_data['paypal_api']['live']['secret'] = isset(BABE_Settings::$settings['paypal_live_secret']) ? BABE_Settings::$settings['paypal_live_secret'] : '';

            $setting_data['paypal_api']['test']['client_id'] = isset(BABE_Settings::$settings['paypal_test_client_id']) ? BABE_Settings::$settings['paypal_test_client_id'] : '';

            $setting_data['paypal_api']['test']['secret'] = isset(BABE_Settings::$settings['paypal_test_secret']) ? BABE_Settings::$settings['paypal_test_secret'] : '';

        }

        return $setting_data;

    }


    public static function sanitize_settings($new_input, $input) {

        $new_input['paypal_email'] = isset($input['paypal_email']) ? sanitize_email($input['paypal_email']) : '';

        $new_input['sandbox_mode'] = isset($input['sandbox_mode']) ? intval($input['sandbox_mode']) : 0;

        $new_input['paypal_live_client_id'] = isset($input['paypal_live_client_id']) ? sanitize_text_field($input['paypal_live_client_id']) : '';

        $new_input['paypal_live_secret'] = isset($input['paypal_live_secret']) ? sanitize_text_field($input['paypal_live_secret']) : '';

        $new_input['paypal_test_client_id'] = isset($input['paypal_test_client_id']) ? sanitize_text_field($input['paypal_test_client_id']) : '';

        $new_input['paypal_test_secret'] = isset($input['paypal_test_secret']) ? sanitize_text_field($input['paypal_test_secret']) : '';

        return $new_input;
    }



    public static function paypal_server_response(){

        $raw_post_data = file_get_contents('php://input');
        $posted = explode('&', $raw_post_data);

        $validate_ipn = array( 'cmd' => '_notify-validate' );
        $validate_ipn += wp_unslash( $posted );

        // Send back post vars to paypal
        $params = array(
            'body'        => $validate_ipn,
            'timeout'     => 60,
            'httpversion' => '1.1',
            'compress'    => false,
            'decompress'  => false,
            'user-agent'  => 'BA-Book-Everything'
        );

        // Post back to get a response.

        $data_settings = self::init_settings();

        $link = $data_settings['sandbox_mode'] ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

        $response = wp_safe_remote_post( $link, $params );

        // Check to see if the request was valid.
        if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && strstr( $response['body'], 'VERIFIED' ) ) {
            $validate = true;
        } else {
            $validate = false;
        }

        if ( $validate && ! empty( $posted['custom'] ) ) {

            $order_num = $posted['custom'];
            $page = get_page_by_title( $order_num, OBJECT, BABE_Post_types::$order_post_type );

            if (!empty($page)){

                // Lowercase returned variables.
                $posted['payment_status'] = strtolower( $posted['payment_status'] );
                //   Sandbox fix.
                if ( isset( $posted['test_ipn'] ) && 1 == $posted['test_ipn'] && 'pending' == $posted['payment_status'] ) {
                    $posted['payment_status'] = 'completed';
                }

                if ( 'completed' === $posted['payment_status'] && $posted['mc_gross'] > 0){

                    BABE_Payments::do_complete_order($page->ID, 'paypal', $posted['txn_id'], $posted['mc_gross']);
                    return;
                }

                // cancel order - set status to draft
                // order will be deleted in cron job or updated by customer with new payment process
                BABE_Order::update_order_status($page->ID, 'draft');
            }  /// end if (!empty($page))

        } /// end if $validate


        return;

    }


    public static function order_to_pay_paypal($order_id, $args, $current_url, $success_url){

        $amount = isset($args['payment']['amount_to_pay']) && $args['payment']['amount_to_pay'] == 'deposit' ? BABE_Order::get_order_prepaid_amount($order_id) : BABE_Order::get_order_total_amount($order_id);

        $phone_args = array(
            'night_phone_b' => $args['phone'],
            'day_phone_b'   => $args['phone']
        );

        $data_settings = self::init_settings();

        $paypal_args = array_merge( array(
            'cmd'           => '_xclick',
            'business'      => $data_settings['paypal_email'],
            'no_note'       => 1,
            'currency_code' => BABE_Currency::get_currency(),
            'charset'       => 'utf-8',
            'rm'            => 2,
            'return'        => $success_url,
            'cancel_return' => $current_url,
            'bn'            => 'BA_BuyNow_WPS',
            'no_shipping'   => 1,
            'custom'        => BABE_Order::get_order_number($order_id),
            'notify_url'    => BABE_Payments::get_payment_server_response_page_url('paypal'),
            'first_name'    => $args['first_name'],
            'last_name'     => $args['last_name'],
            'address1'      => '',
            'address2'      => '',
            'city'          => '',
            'state'         => '',
            'country'       => '',
            'email'         => $args['email'],
            'amount'        => $amount,
            'item_name'     => 'Order: '.BABE_Order::get_order_number($order_id),
        ),
            $phone_args);

        $paypal_query = http_build_query( $paypal_args, '', '&' );

        $link = $data_settings['sandbox_mode'] ? 'https://www.sandbox.paypal.com/cgi-bin/webscr?test_ipn=1&' . $paypal_query : 'https://www.paypal.com/cgi-bin/webscr?' . $paypal_query;

        BABE_Order::update_order_status($order_id, 'payment_processing');

        wp_redirect($link);

        return;

    }

    public static function refund_paypal($refunded, $order_id, $amount = 0, $token_arr = array()){

        $refunded = 0;

        $data_settings = self::init_settings();

        if ($amount){

            $api = $data_settings['sandbox_mode'] ? $data_settings['paypal_api']['test'] : $data_settings['paypal_api']['live'];

            $currency = BABE_Order::get_order_currency($order_id);

            $transaction_id = isset($token_arr['token']) ? $token_arr['token'] : ''; //// get saved payment transaction id

            $apiContext = new \PayPal\Rest\ApiContext(
                new \PayPal\Auth\OAuthTokenCredential(
                    $api['client_id'],
                    $api['secret']
                )
            );

            $amt = new Paypal_Amount();
            $amt->setTotal($amount)
                ->setCurrency($currency);

            $refund = new Paypal_Refund();
            $refund->setAmount($amt);

            $sale = new Paypal_Sale();
            $sale->setId($transaction_id);

            $refundedSale = '';

            try {

                $refundedSale = $sale->refund($refund, $apiContext);

            } catch (PayPal\Exception\PayPalConnectionException $ex) {

                return $refunded;

            } catch (Exception $ex) {
                return $refunded;
            }

            $refunded = $amount;

            BABE_Payments::do_after_refund_order($order_id, 'paypal', $transaction_id, $refunded, $token_arr);

        }

        return $refunded;

    }


}

add_action( 'plugin_loaded', function () {
    new BAPAYPAL_PAYMENT_GATEWAY();
} );
