<?php
/**
 * WC_Gateway_PaymentSensebank class
 */
if (!defined('ABSPATH')) {
    exit;
}
/**
 * PaymentSensebank Gateway.
 * @class  WC_Gateway_PaymentSensebank
 */
class WC_Gateway_PaymentSensebank extends WC_Payment_Gateway
{
    /**
     * Payment gateway instructions.
     * @var string
     *
     */
    protected $instructions;
    /**
     * Whether the gateway is visible for non-admin users.
     * @var boolean
     *
     */
    public $id = 'sensebank';
    public $has_fields;
    public $supports;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $merchant;
    public $password;
    public $test_mode;
    public $stage_mode;
    public $order_status_paid;
    public $send_order;
    public $tax_system;
    public $tax_type;
    public $success_url;
    public $fail_url;
    public $backToShopUrl;
    public $backToShopUrlName;
    public $FFDVersion;
    public $paymentMethodType;
    public $paymentObjectType;
    public $paymentObjectType_delivery;
    public $pData;
    public $logging;
    public $orderNumberById;
    public $allowCallbacks;
    public $enable_for_methods;
    public $test_url;
    public $prod_url;
    public $cacert_path;
    public function __construct()
    {
        $this->has_fields = false;
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
        );
        if (defined('PAYMENT_SENSEBANK_ENABLE_REFUNDS') && PAYMENT_SENSEBANK_ENABLE_REFUNDS == true) {
            $this->supports[] = 'refunds';
        }
        $this->method_title = PAYMENT_SENSEBANK_PAYMENT_NAME;
        $this->method_description = __('Allows sensebank payments.', 'woocommerce-gateway-sensebank');
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions', $this->description);
        $this->merchant = $this->get_option('merchant');
        $this->password = $this->get_option('password');
        $this->test_mode = $this->get_option('test_mode');
        $this->stage_mode = $this->get_option('stage_mode');
        $this->description = $this->get_option('description');
        $this->order_status_paid = $this->get_option('order_status_paid');
        $this->send_order = $this->get_option('send_order');
        $this->tax_system = $this->get_option('tax_system');
        $this->tax_type = $this->get_option('tax_type');
        $this->success_url = $this->get_option('success_url');
        $this->fail_url = $this->get_option('fail_url');
        $this->backToShopUrl = $this->get_option('backToShopUrl');
        $this->backToShopUrlName = $this->get_option('backToShopUrlName');
        $this->FFDVersion = $this->get_option('FFDVersion');
        $this->paymentMethodType = $this->get_option('paymentMethodType');
        $this->paymentObjectType = $this->get_option('paymentObjectType');
        $this->paymentObjectType_delivery = $this->get_option('paymentMethodType_delivery');
        $this->pData = get_plugin_data(__FILE__);
        $this->logging = PAYMENT_SENSEBANK_ENABLE_LOGGING;
        $this->orderNumberById = true; //false - must be installed WooCommerce Sequential Order Numbers
        $this->allowCallbacks = defined('PAYMENT_SENSEBANK_ENABLE_CALLBACK') ? PAYMENT_SENSEBANK_ENABLE_CALLBACK : false;
        $this->enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->test_url = PAYMENT_SENSEBANK_TEST_URL;
        $this->prod_url = PAYMENT_SENSEBANK_PROD_URL;
        $this->cacert_path = null;
        if (defined('PAYMENT_SENSEBANK_PROD_URL_ALTERNATIVE_DOMAIN') && defined('PAYMENT_SENSEBANK_PROD_URL_ALT_PREFIX')) {
            if (substr($this->merchant, 0, strlen(PAYMENT_SENSEBANK_PROD_URL_ALT_PREFIX)) == PAYMENT_SENSEBANK_PROD_URL_ALT_PREFIX) {
                $pattern = '/^https:\/\/[^\/]+/';
                $this->prod_url = preg_replace($pattern, rtrim(PAYMENT_SENSEBANK_PROD_URL_ALTERNATIVE_DOMAIN, '/'), $this->prod_url);
            } else {
                $this->allowCallbacks = false;
            }
        }
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_scheduled_subscription_payment_sensebank', array($this, 'process_subscription_payment'), 10, 2);
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_sensebank', array($this, 'webhook_result'));
    }
    public function init_form_fields()
    {
        $shipping_methods = array();
        if (is_admin()) {
            foreach (WC()->shipping()->load_shipping_methods() as $method) {
                $shipping_methods[$method->id] = $method->get_method_title();
            }
        }

        $form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-' . $this->id . '-text-domain'),
                'type' => 'checkbox',
                'label' => __('Enable', 'woocommerce') . " " . PAYMENT_SENSEBANK_PAYMENT_NAME,
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'wc-' . $this->id . '-text-domain'),
                'type' => 'text',
                'description' => __('Title displayed to your customer when they make their order.', 'wc-' . $this->id . '-text-domain'),
            ),
            'merchant' => array(
                'title' => __('Login-API', 'wc-' . $this->id . '-text-domain'),
                'type' => 'text',
                'default' => '',
            ),
            'password' => array(
                'title' => __('Password', 'wc-' . $this->id . '-text-domain'),
                'type' => 'password',
                'default' => '',
            ),
            'test_mode' => array(
                'title' => __('Test mode', 'wc-' . $this->id . '-text-domain'),
                'type' => 'checkbox',
                'label' => __('Enable', 'woocommerce'),
                'description' => __('In this mode no actual payments are processed.', 'wc-' . $this->id . '-text-domain'),
                'default' => 'no',
            ),
            'stage_mode' => array(
                'title' => __('Payments type', 'wc-' . $this->id . '-text-domain'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'one-stage',
                'options' => array(
                    'one-stage' => __('One-phase payments', 'wc-' . $this->id . '-text-domain'),
                    'two-stage' => __('Two-phase payments', 'wc-' . $this->id . '-text-domain'),
                ),
            ),
        );
        $form_fields_ext1 = array(
            'description' => array(
                'title' => __('Description', 'wc-' . $this->id . '-text-domain'),
                'type' => 'textarea',
                'description' => __('Payment description displayed to your customer.', 'wc-' . $this->id . '-text-domain'),
            ),
            'order_status_paid' => array(
                'title' => __('Paid order status', 'wc-' . $this->id . '-text-domain'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'wc-completed',
                'options' => array(
                    'wc-processing' => _x('Processing', 'Order status', 'woocommerce'),
                    'wc-completed' => _x('Completed', 'Order status', 'woocommerce'),
                ),
            ),
            'success_url' => array(
                'title' => __('successUrl', 'wc-' . $this->id . '-text-domain'),
                'type' => 'text',
                'description' => __('Page your customer will be redirected to after a <b>successful payment</b>.<br/>Leave this field blank, if you want to use default settings.', 'wc-' . $this->id . '-text-domain'),
            ),
            'fail_url' => array(
                'title' => __('failUrl', 'wc-' . $this->id . '-text-domain'),
                'type' => 'text',
                'description' => __('Page your customer will be redirected to after an <b>unsuccessful payment</b>.<br/>Leave this field blank, if you want to use default settings.', 'wc-' . $this->id . '-text-domain'),
            ),
        );
        $form_fields = array_merge($form_fields, $form_fields_ext1);
        if (defined('PAYMENT_SENSEBANK_ENABLE_BACK_URL_SETTINGS') && PAYMENT_SENSEBANK_ENABLE_BACK_URL_SETTINGS === true) {
            $form_fields_backToShopUrlSettings = array(
                'backToShopUrl' => array(
                    'title' => __('Back to shop URL', 'wc-' . $this->id . '-text-domain'),
                    'type' => 'text',
                    'default' => '',
                    'description' => __('Adds URL for checkout page button that will take a cardholder back to the assigned merchant web-site URL.', 'wc-' . $this->id . '-text-domain'),
                ),
            );
            $form_fields = array_merge($form_fields, $form_fields_backToShopUrlSettings);
        }
        if (defined('PAYMENT_SENSEBANK_ENABLE_CART_OPTIONS') && PAYMENT_SENSEBANK_ENABLE_CART_OPTIONS == true) {
            $form_fields_cartOptions = array(
                'paymentMethodType' => array(
                    'title' => __('Payment type', 'wc-' . $this->id . '-text-domain'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '1',
                    'options' => array(
                        '1' => __('Full prepayment', 'wc-' . $this->id . '-text-domain'),
                        '2' => __('Partial prepayment', 'wc-' . $this->id . '-text-domain'),
                        '3' => __('Advance payment', 'wc-' . $this->id . '-text-domain'),
                        '4' => __('Full payment', 'wc-' . $this->id . '-text-domain'),
                    ),
                ),
                'paymentMethodType_delivery' => array(
                    'title' => __('Payment type for delivery', 'wc-' . $this->id . '-text-domain'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '1',
                    'options' => array(
                        '1' => __('Full prepayment', 'wc-' . $this->id . '-text-domain'),
                        '2' => __('Partial prepayment', 'wc-' . $this->id . '-text-domain'),
                        '3' => __('Advance payment', 'wc-' . $this->id . '-text-domain'),
                        '4' => __('Full payment', 'wc-' . $this->id . '-text-domain'),
                    ),
                ),
                'paymentObjectType' => array(
                    'title' => __('Type of goods and services', 'wc-' . $this->id . '-text-domain'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '1',
                    'options' => array(
                        '1' => __('Goods', 'wc-' . $this->id . '-text-domain'),
                        '2' => __('Excised goods', 'wc-' . $this->id . '-text-domain'),
                        '3' => __('Job', 'wc-' . $this->id . '-text-domain'),
                        '4' => __('Service', 'wc-' . $this->id . '-text-domain'),
                        '5' => __('Stake in gambling', 'wc-' . $this->id . '-text-domain'),
                        '7' => __('Lottery ticket', 'wc-' . $this->id . '-text-domain'),
                        '9' => __('Intellectual property provision', 'wc-' . $this->id . '-text-domain'),
                        '10' => __('Payment', 'wc-' . $this->id . '-text-domain'),
                        '11' => __("Agent's commission", 'wc-' . $this->id . '-text-domain'),
                        '12' => __('Combined', 'wc-' . $this->id . '-text-domain'),
                        '13' => __('Other', 'wc-' . $this->id . '-text-domain'),
                    ),
                ),
            );
            $form_fields = array_merge($form_fields, $form_fields_cartOptions);
        }
        $this->form_fields = $form_fields;
    }
    public function is_available()
    {
        return parent::is_available();
    }
    public function process_admin_options()
    {
        if ($this->allowCallbacks == false) {
            $this->writeLog("Nothing to update: " . __LINE__);
            return parent::process_admin_options();
        }
        if (isset($_POST['woocommerce_sensebank_test_mode'])) {
            $action_adr = $this->test_url;
            $gate_url = str_replace("payment/rest", "mportal/mvc/public/merchant/update", $action_adr);
            if (defined('PAYMENT_SENSEBANK_TEST_URL_ALTERNATIVE_DOMAIN')) {
                $pattern = '/^https:\/\/[^\/]+/';
                $gate_url = preg_replace($pattern, rtrim(PAYMENT_SENSEBANK_TEST_URL_ALTERNATIVE_DOMAIN, '/'), $gate_url);
            }
        } else {
            $action_adr = $this->prod_url;
            $gate_url = str_replace("payment/rest", "mportal/mvc/public/merchant/update", $action_adr);
            if (defined('PAYMENT_SENSEBANK_PROD_URL_ALTERNATIVE_DOMAIN')) {
                $pattern = '/^https:\/\/[^\/]+/';
                $gate_url = preg_replace($pattern, rtrim(PAYMENT_SENSEBANK_PROD_URL_ALTERNATIVE_DOMAIN, '/'), $gate_url);
            }
        }
        $gate_url .= substr($this->merchant, 0, -4);
        $callback_addresses_string = get_option('siteurl') . '/wc-api/sensebank' . '?action=callback';
        if ($this->allowCallbacks !== false) {
            $response = $this->_updateGatewayCallback($this->merchant, $this->password, $gate_url, $callback_addresses_string, null);
            if (PAYMENT_SENSEBANK_ENABLE_LOGGING === true) {
                $this->writeLog("REQUEST:\n" . $gate_url . "\n[callback_addresses_string]: " . $callback_addresses_string . "\nRESPONSE:\n" . $response);
            }
        }
        parent::process_admin_options();
    }
    public function _updateGatewayCallback($login, $password, $action_address, $callback_addresses_string, $ca_info = null)
    {
        $headers = array(
            'Content-Type:application/json',
            'Authorization: Basic ' . base64_encode($login . ":" . $password),
        );
        $data['callbacks_enabled'] = true;
        $data['callback_type'] = "STATIC";
        $data['callback_addresses'] = $callback_addresses_string;
        $data['callback_http_method'] = "GET";
        $data['callback_operations'] = "deposited,approved,declinedByTimeout";
        $response = $this->_sendGatewayData(json_encode($data), $action_address, $headers, $ca_info);
        return $response;
    }
    public function _sendGatewayData($data, $action_address, $headers = array(), $ca_info = null)
    {
        $logData = $data;
        if (isset($logData['password'])) {
            $logData['password'] = '**removed from log**';
        }
        $this->writeLog("[REQUEST: _sendGatewayData]: " . $action_address . "\n body: " . json_encode($logData) . "\n headers: " . json_encode($headers));

        $curl_opt = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_VERBOSE => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_URL => $action_address,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HEADER => true,
        );
        $ssl_verify_peer = false;
        if ($ca_info != null) {
            $ssl_verify_peer = true;
            $curl_opt[CURLOPT_CAINFO] = $ca_info;
        }
        $curl_opt[CURLOPT_SSL_VERIFYPEER] = $ssl_verify_peer;
        $ch = curl_init();
        curl_setopt_array($ch, $curl_opt);
        $response = curl_exec($ch);
        if ($response === false) {
            $this->writeLog("The payment gateway is returning an empty response.");
        }
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return substr($response, $header_size);
    }
    public function writeLog($var, $info = true)
    {
        if ($this->test_mode != "yes") {
        }
        $information = "";
        if ($var) {
            if ($info) {
                $information = "\n\n";
                $information .= str_repeat("-=", 64);
                $information .= "\nDate: " . date('Y-m-d H:i:s');
                $information .= "\nWordpress version " . get_bloginfo('version') . "; Woocommerce version: " . wpbo_get_woo_version_number() . "\n";
            }
            $result = $var;
            if (is_array($var) || is_object($var)) {
                $result = "\n" . print_r($var, true);
            }
            $result .= "\n\n";
            $path = dirname(__FILE__) . '/../logs/wc_sensebank_' . date('Y-m') . '.log';
            error_log($information . $result, 3, $path);
            return true;
        }
        return false;
    }
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        if (!empty($_GET['pay_for_order']) && $_GET['pay_for_order'] == 'true') {
            $this->generate_form($order_id);
            die;
        }
        $pay_now_url = $order->get_checkout_payment_url(true);
        return array(
            'result' => 'success',
            'redirect' => $pay_now_url,
        );
    }
    public function generate_form($order_id)
    {
        $order = new WC_Order($order_id);
        $amount = $order->get_total() * 100;
        $coupons = array();
        global $woocommerce;
        if (!empty($woocommerce->cart->applied_coupons)) {
            foreach ($woocommerce->cart->applied_coupons as $code) {
                $coupons[] = new WC_Coupon($code);
            }
        }
        if ($this->test_mode == 'yes') {
            $action_adr = $this->test_url;
        } else {
            $action_adr = $this->prod_url;
        }
        if ($this->stage_mode == 'two-stage') {
            $action_adr .= 'registerPreAuth.do';
        } else if ($this->stage_mode == 'one-stage') {
            $action_adr .= 'register.do';
        }
        $order_data = $order->get_data();
        $language = substr(get_bloginfo("language"), 0, 2);
        switch ($language) {
            case ('uk'):
                $language = 'ua';
                break;
            default:
                $language = 'ua';
                break;
        }
        $jsonParams_array = array(
            'CMS' => 'Wordpress ' . get_bloginfo('version') . " + woocommerce version: " . wpbo_get_woo_version_number(),
            'Module-Version' => $this->pData['Version'],
        );
        if (PAYMENT_SENSEBANK_CUSTOMER_EMAIL_SEND && !empty($order_data['billing']['email'])) {
            $jsonParams_array['email'] = $order_data['billing']['email'];
        }
        if (!empty($order_data['billing']['phone'])) {
            $jsonParams_array['phone'] = preg_replace("/(\W*)/", "", $order_data['billing']['phone']);
        }
        if (!empty($order_data['billing']['first_name'])) {
            $jsonParams_array['payerFirstName'] = $order_data['billing']['first_name'];
        }
        if (!empty($order_data['billing']['last_name'])) {
            $jsonParams_array['payerLastName'] = $order_data['billing']['last_name'];
        }
        if (!empty($order_data['billing']['address_1'])) {
            $jsonParams_array['postAddress'] = $order_data['billing']['address_1'];
        }
        if (!empty($order_data['billing']['city'])) {
            $jsonParams_array['payerCity'] = $order_data['billing']['city'];
        }
        if (!empty($order_data['billing']['state'])) {
            $jsonParams_array['payerState'] = $order_data['billing']['state'];
        }
        if (!empty($order_data['billing']['postcode'])) {
            $jsonParams_array['payerPostalCode'] = $order_data['billing']['postcode'];
        }
        if (!empty($order_data['billing']['country'])) {
            $jsonParams_array['payerCountry'] = $order_data['billing']['country'];
        }
        if (
            defined('PAYMENT_SENSEBANK_ENABLE_BACK_URL_SETTINGS')
            && PAYMENT_SENSEBANK_ENABLE_BACK_URL_SETTINGS === true
            && !empty($this->backToShopUrl)
        ) {
            $jsonParams_array['backToShopUrl'] = $this->backToShopUrl;
        }
        $args = array(
            'userName' => $this->merchant,
            'password' => $this->password,
            'amount' => $amount,
            'jsonParams' => json_encode($jsonParams_array),
        );

        if ($this->orderNumberById) {
            $args['orderNumber'] = $order_id . '_' . time();
        } else {
            $args['orderNumber'] = trim(str_replace('#', '', $order->get_order_number())) . "_" . time(); // PLUG-3966, PLUG-4300
        }
        $args['returnUrl'] = get_option('siteurl') . '/wc-api/sensebank?' . http_build_query(
            array(
                'action' => 'result',
                'order_id' => $order_id,
                'orderNumber' => $args['orderNumber'],
            )
        );

        if (!empty($order_data['customer_id'] && $order_data['customer_id'] > 0)) {
            $client_email = !empty($order_data['billing']['email']) ? $order_data['billing']['email'] : "";
            $args['clientId'] = md5($order_data['customer_id'] . $client_email . get_option('siteurl'));
        }
        if (
            defined('PAYMENT_SENSEBANK_ENABLE_CART_OPTIONS')
            && PAYMENT_SENSEBANK_ENABLE_CART_OPTIONS == true
            && $this->send_order == 'yes'
        ) {
            $args['taxSystem'] = $this->tax_system;
            $order_items = $order->get_items();
            $order_timestamp_created = $order_data['date_created']->getTimestamp();
            $items = array();
            $itemsCnt = 1;
            foreach ($order_items as $value) {
                $item = array();
                $product_variation_id = $value['variation_id'];
                if ($product_variation_id) {
                    $product = new WC_Product_Variation($value['variation_id']);
                    $item_code = $itemsCnt . "-" . $value['variation_id'];
                } else {
                    $product = new WC_Product($value['product_id']);
                    $item_code = $itemsCnt . "-" . $value['product_id'];
                }
                $product_sku = get_post_meta($value['product_id'], '_sku', true);
                $item_code = !empty($product_sku) ? $product_sku : $item_code;
                $tax_type = $this->getTaxType($product);
                $product_price = round((($value['total'] + $value['total_tax']) / $value['quantity']) * 100);
                if ($product->get_type() == 'variation') {
                }
                $item['positionId'] = $itemsCnt++;
                $item['name'] = $value['name'];
                if ($this->FFDVersion == 'v1_05') {
                    $item['quantity'] = array(
                        'value' => $value['quantity'],
                        'measure' => defined('PAYMENT_SENSEBANK_MEASUREMENT_NAME') ? PAYMENT_SENSEBANK_MEASUREMENT_NAME : 'pcs',
                    );
                } else {
                    $item['quantity'] = array(
                        'value' => $value['quantity'],
                        'measure' => defined('PAYMENT_SENSEBANK_MEASUREMENT_CODE') ? PAYMENT_SENSEBANK_MEASUREMENT_CODE : '0',
                    );
                }
                $item['itemAmount'] = $product_price * $value['quantity'];
                $item['itemCode'] = $item_code;
                $item['tax'] = array(
                    'taxType' => $tax_type,
                );
                $item['itemPrice'] = $product_price;
                $attributes = array();
                $attributes[] = array(
                    "name" => "paymentMethod",
                    "value" => $this->paymentMethodType,
                );
                $attributes[] = array(
                    "name" => "paymentObject",
                    "value" => $this->paymentObjectType,
                );
                $item['itemAttributes']['attributes'] = $attributes;
                $items[] = $item;
            }
            $shipping_total = $order->get_shipping_total();
            $shipping_tax = $order->get_shipping_tax();
            if ($shipping_total > 0) {
                $WC_Order_Item_Shipping = new WC_Order_Item_Shipping();
                $itemShipment['positionId'] = $itemsCnt;
                $itemShipment['name'] = __('Delivery', 'wc-' . $this->id . '-text-domain');
                if ($this->FFDVersion == 'v1_05') {
                    $itemShipment['quantity'] = array(
                        'value' => 1,
                        'measure' => defined('PAYMENT_SENSEBANK_MEASUREMENT_NAME') ? PAYMENT_SENSEBANK_MEASUREMENT_NAME : 'pcs',
                    );
                } else {
                    $itemShipment['quantity'] = array(
                        'value' => 1,
                        'measure' => defined('PAYMENT_SENSEBANK_MEASUREMENT_CODE') ? PAYMENT_SENSEBANK_MEASUREMENT_CODE : '0',
                    );
                }
                $itemShipment['itemAmount'] = $itemShipment['itemPrice'] = $shipping_total * 100;
                $itemShipment['itemCode'] = 'delivery';
                $itemShipment['tax'] = array(
                    'taxType' => $tax_type = $this->getTaxType($WC_Order_Item_Shipping), //$this->tax_type
                );
                $attributes = array();
                $attributes[] = array(
                    "name" => "paymentMethod",
                    "value" => $this->paymentObjectType_delivery,
                );
                $attributes[] = array(
                    "name" => "paymentObject",
                    "value" => 4,
                );
                $itemShipment['itemAttributes']['attributes'] = $attributes;
                $items[] = $itemShipment;
            }
            $order_bundle = array(
                'orderCreationDate' => $order_timestamp_created,
                'cartItems' => array('items' => $items),
            );
            if (PAYMENT_SENSEBANK_CUSTOMER_EMAIL_SEND && !empty($order_data['billing']['email'])) {
                $order_bundle['customerDetails']['email'] = $order_data['billing']['email'];
            }
            if (!empty($order_data['billing']['phone'])) {
                $order_bundle['customerDetails']['phone'] = preg_replace("/(\W*)/", "", $order_data['billing']['phone']);
            }
            if (class_exists('RBSDiscount')) {
                $discountHelper = new RBSDiscount();
                $discount = $discountHelper->discoverDiscount($args['amount'], $order_bundle['cartItems']['items']);
                if ($discount != 0) {
                    $discountHelper->setOrderDiscount($discount);
                    $recalculatedPositions = $discountHelper->normalizeItems($order_bundle['cartItems']['items']);
                    $recalculatedAmount = $discountHelper->getResultAmount();
                    $order_bundle['cartItems']['items'] = $recalculatedPositions;
                }
            }
            $args['orderBundle'] = json_encode($order_bundle);
        }
        $headers = array(
            'CMS: Wordpress ' . get_bloginfo('version') . " + woocommerce version: " . wpbo_get_woo_version_number(),
            'Module-Version: ' . $this->pData['Version'],
        );
        $response = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr, $headers, $this->cacert_path);
        if (PAYMENT_SENSEBANK_ENABLE_LOGGING === true) {
            $logData = $args;
            $logData['password'] = '**removed from log**';
            $this->writeLog("[REQUEST]: " . $action_adr . ": \nDATA: " . print_r($logData, true) . "\n[RESPONSE]: " . $response);
        }
        $response = json_decode($response, true);
        if (empty($response['errorCode'])) {
            if (PAYMENT_SENSEBANK_SKIP_CONFIRMATION_STEP == true) {
                wp_redirect($response['formUrl']); //PLUG-4104 Comment this line for redirect via pressing button (step) // TODO: error 1
            }
            echo '<p><a class="button cancel" href="' . $response['formUrl'] . '">' . __('Proceed with payment', 'wc-' . $this->id . '-text-domain') . '</a></p>'; // TODO: error 2
            exit;
        } else {
            return '<p>' . __('Error code #' . $response['errorCode'] . ': ' . $response['errorMessage'], 'wc-' . $this->id . '-text-domain') . '</p>' .
            '<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel payment and return to cart', 'wc-' . $this->id . '-text-domain') . '</a>';
        }
    }
    public function getTaxType($product)
    {
        $tax = new WC_Tax();
        if (get_option("woocommerce_calc_taxes") == "no") { // PLUG-4056
            $item_rate = -1;
        } else {
            $base_tax_rates = $tax->get_base_tax_rates($product->get_tax_class(true));
            if (!empty($base_tax_rates)) {
                $temp = $tax->get_rates($product->get_tax_class());
                $rates = array_shift($temp);
                $item_rate = round(array_shift($rates));
            } else {
                $item_rate = -1;
            }
        }
        if ($item_rate == 20) {
            $tax_type = 6;
        } else if ($item_rate == 18) {
            $tax_type = 3;
        } else if ($item_rate == 10) {
            $tax_type = 2;
        } else if ($item_rate == 0) {
            $tax_type = 1;
        } else {
            $tax_type = $this->tax_type;
        }
        return $tax_type;
    }
    public function correctBundleItem(&$item, $discount)
    {
        $item['itemAmount'] -= $discount;
        $diff_price = fmod($item['itemAmount'], $item['quantity']['value']); //0.5 quantity
        if ($diff_price != 0) {
            $item['itemAmount'] += $item['quantity']['value'] - $diff_price;
        }
        $item['itemPrice'] = $item['itemAmount'] / $item['quantity']['value'];
    }
    public function receipt_page($order)
    {
        echo $this->generate_form($order);
    }
    public function webhook_result()
    {
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            if ($this->test_mode == 'yes') {
                $action_adr = $this->test_url;
            } else {
                $action_adr = $this->prod_url;
            }
            $action_adr .= 'getOrderStatusExtended.do';
            $args = array(
                'userName' => $this->merchant,
                'password' => $this->password,
            );
            switch ($action) {
                case "result":
                    $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : $_GET['amp;order_id'];
                    $orderNumber = isset($_GET['orderNumber']) ? $_GET['orderNumber'] : $_GET['amp;orderNumber'];
                    $args['orderNumber'] = $orderNumber;
                    $order = new WC_Order($order_id);
                    $response = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr, array(), $this->cacert_path);
                    if (PAYMENT_SENSEBANK_ENABLE_LOGGING === true) {
                        $logData = $args;
                        $logData['password'] = '**removed from log**';
                        $this->writeLog("[REQUEST gOSE]: " . $action_adr . ": " . print_r($logData, true) . "\n[RESPONSE]: " . print_r($response, true));
                    }
                    $response = json_decode($response, true);
                    $orderStatus = $response['orderStatus'];
                    if ($orderStatus == '1' || $orderStatus == '2') {
                        if ($this->allowCallbacks === false) {
                            $order->update_status($this->order_status_paid, "Sensebank: " . __('Payment successful', 'wc-' . $this->id . '-text-domain'));
                            try {
                                wc_reduce_stock_levels($order_id);
                            } catch (Exception $e) {
                            }
                            update_post_meta($order_id, 'orderId', $args['orderId']);
                            $order->payment_complete();
                        }
                        if (!empty($this->success_url)) {
                            WC()->cart->empty_cart();
                            wp_redirect($this->success_url . "?order_id=" . $order_id);
                            exit;
                        }
                        wp_redirect($this->get_return_url($order));
                        exit;
                    } else {
                        $order->update_status('failed', "Sensebank: " . __('Payment failed', 'wc-' . $this->id . '-text-domain'));
                        if (!empty($this->fail_url)) {
                            wp_redirect($this->fail_url . "?order_id=" . $order_id);
                            exit;
                        }
                        wc_add_notice(__('There was an error while processing payment', 'wc-' . $this->id . '-text-domain') . "<br/>" . $response['actionCodeDescription'], 'error');
                        $shop_url = get_permalink(wc_get_page_id('shop'));
                        wp_redirect($shop_url);
                        exit;
                    }
                    $order->save();
                    break;
                case "callback":
                    $this->writeLog("[DEBUG]: webhook_result type: callback");
                    $args['orderId'] = isset($_GET['mdOrder']) ? $_GET['mdOrder'] : null;
                    $response = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr);
                    $response = json_decode($response, true);
                    $p = explode("_", $response['orderNumber']);
                    $order_id = $p[0];
                    $order = new WC_Order($order_id);
                    $orderStatus = $response['orderStatus'];
                    $this->writeLog("[Incoming cb (" . $order_id . ")]: OrderStatus= " . $orderStatus);
                    if ($orderStatus == '1' || $orderStatus == '2') {
                        update_post_meta($order_id, 'orderId', $args['orderId']);
                        if (strpos($order->get_status(), "pending") !== false || strpos($order->get_status(), "failed") !== false) { //PLUG-4415, 4495
                            $order->update_status($this->order_status_paid, "Sensebank: " . __('Payment successful', 'wc-' . $this->id . '-text-domain'));
                            $this->writeLog("[VALUE TO SET ORDER_STATUS]: " . $this->order_status_paid); //PLUG-7155
                            try {
                                wc_reduce_stock_levels($order_id);
                            } catch (Exception $e) {
                            }
                            $order->payment_complete();
                        }
                    } else if ($orderStatus == '4') {
                        exit();
                    } elseif (
                        empty(get_post_meta($order_id, 'orderId', true))
                        && $this->id == $order->get_payment_method()
                    ) {
                        $this->writeLog(">>" . $order->get_meta('orderId') . "<<");
                        $order->update_status('failed', "Sensebank: " . __('Payment failed', 'wc-' . $this->id . '-text-domain'));
                    }
                    $order->save();
                    break;
            }
            exit;
        }
    }
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $this->writeLog("[DEBUG]: process_refund: start");
        $order = wc_get_order($order_id);
        if ($amount == "0.00") {
            $amount = 0;
        } else {
            $amount = $amount * 100;
        }
        $order_key = $order->get_order_key();
        $args = array(
            'userName' => $this->merchant,
            'password' => $this->password,
            'orderId' => get_post_meta($order_id, 'orderId', true),
            'amount' => $amount,
        );
        if ($this->test_mode == 'yes') {
            $action_adr = $this->test_url;
        } else {
            $action_adr = $this->prod_url;
        }
        $this->writeLog("[DEBUG]: process_refund: " . json_encode($args));
        $gose = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'getOrderStatusExtended.do', array(), $this->cacert_path);
        $res = json_decode($gose, true);
        if ($res["orderStatus"] == "2" || $res["orderStatus"] == "4") { //DEPOSITED||REFUNDED
            $result = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'refund.do', array(), $this->cacert_path);
            if (PAYMENT_SENSEBANK_ENABLE_LOGGING === true) {
                $logData = $args;
                $logData['password'] = '**removed from log**';
                $this->writeLog("[DEPOSITED REFUND RESPONSE]: " . print_r($logData, true) . " \n" . $result);
            }
        } elseif ($res["orderStatus"] == "1") { //APPROVED 2x
            if ($amount == 0) {
                unset($args['amount']);
            }
            $result = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'reverse.do', array(), $this->cacert_path);
            if (PAYMENT_SENSEBANK_ENABLE_LOGGING === true) {
                $logData = $args;
                $logData['password'] = '**removed from log**';
                $this->writeLog("[APPROVED REVERSE RESPONSE]: " . print_r($logData, true) . " \n" . $result);
            }
        } else {
            return new WP_Error('wc_' . $this->id . '_refund_failed', sprintf(__('Order ID (%s) failed to be refunded. Please contact administrator for more help.', 'wc-' . $this->id . '-text-domain'), $order_id));
        }
        $response = json_decode($result, true);
        if ($response["errorCode"] != "0") {
            if ($response["errorCode"] == "7") {
                return new WP_Error('wc_' . $this->id . '_refund_failed', "For partial refunds Order state should be in DEPOSITED in Gateway");
            }
            return new WP_Error('wc_' . $this->id . '_refund_failed', $response["errorMessage"]);
        } else {
            $result = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'getOrderStatusExtended.do', array(), $this->cacert_path);
            if (PAYMENT_SENSEBANK_ENABLE_LOGGING === true) {
                $this->writeLog("[FINALE STATE]: " . $result);
            }
            $response = json_decode($result, true);
            $orderStatus = $response['orderStatus'];
            if ($orderStatus == '4' || $orderStatus == '3') {
                return true;
            } elseif ($orderStatus == '1') {
                return true;
            }
        }
        return false;
    }
    /**
     * Process subscription payment.
     *
     * @param  float     $amount
     * @param  WC_Order  $order
     * @return void
     */
    public function process_subscription_payment($amount, $order)
    {
        $payment_result = $this->get_option('result');
        if ('success' === $payment_result) {
            $order->payment_complete();
        } else {
            $message = __('Order payment failed. To make a successful payment using PaymentSensebank Payments, please review the gateway settings.', 'woocommerce-gateway-sensebank');
            throw new Exception($message);
        }
    }
}
if (!function_exists('wpbo_get_woo_version_number')) {
    function wpbo_get_woo_version_number()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_folder = get_plugins('/' . 'woocommerce');
        $plugin_file = 'woocommerce.php';
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            return $plugin_folder[$plugin_file]['Version'];
        } else {
            return "Unknown";
        }
    }
}
