<?php
/**
 * Plugin Name: SenseBank for online stores on WooCommerce
 * Plugin URI:
 * Description: SenseBank plugin for accepting online payments by Visa/Mastercard or ApplePay/GooglePay cards.
 * Version: 1.0.0
 * Author: SenseBank
 * Text Domain: wc-sensebank-text-domain
 * Domain Path: /lang
 */
if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook( __FILE__, 'sensebank_create_db' );
function sensebank_create_db() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . PAYMENT_SENSEBANK_DB_TRANSACTIONS;

	$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		date_created_gmt datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		date_updated_gmt datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		order_id bigint(20) NOT NULL,		
		errorCode varchar(100),
		orderNumber varchar(100) NOT NULL,
		mdOrder varchar(100),
		orderStatus varchar(100),
		actionCode varchar(100),
		repeated int DEFAULT 0,
		PRIMARY KEY id (id),
        INDEX date_updated_gmt (date_updated_gmt),
        INDEX order_id (order_id),
        INDEX errorCode (errorCode),
        UNIQUE INDEX orderNumber (orderNumber),
        UNIQUE INDEX mdOrder (mdOrder),
        INDEX orderStatus (orderStatus),
        INDEX repeated (repeated)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

require_once __DIR__ . '/includes/include.php';
// if (file_exists(__DIR__ . '/includes/Discount.php')) {
//     include __DIR__ . '/includes/Discount.php';
// }

// Create scheduler for checking transactions.
function sensebank_cron_schedules($schedules){
	if (!isset($schedules["1min"])) {
		$schedules["1min"] = array(
			'interval' => 60,
			'display' => __('Once per minute'));
	}
	return $schedules;
}
add_filter('cron_schedules','sensebank_cron_schedules');
if(!wp_next_scheduled('sensebank_cron_hook')) {
	wp_schedule_event(time(), '1min', 'sensebank_cron_hook');
}
add_action('sensebank_cron_hook', 'sensebank_scheduler');
function sensebank_scheduler() {
	$sense = new WC_Gateway_PaymentSensebank();
	$sense->check_transactions();
}

add_action('woocommerce_order_refunded', 'sensebank_refund', 10, 2);
function sensebank_refund($order_id, $refund_id) {
	$sense = new WC_Gateway_PaymentSensebank();
	$sense->writeLog("[REFUND]: order_id: " . $order_id . ' refund_id: ' . $refund_id);
	$refund = new WC_Order_Refund($refund_id);
	$sense->writeLog("[REFUND]: amount: " . $refund->get_amount() . ' reason: ' . $refund->get_reason());
	$sense->process_refund($order_id, $refund->get_amount(), $refund->get_reason());
}

add_filter('plugin_row_meta', 'sensebank_register_plugin_links', 10, 2);
function sensebank_register_plugin_links($links, $file)
{
    $base = plugin_basename(__FILE__);
    if ($file == $base) {
        $links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=sensebank">' . __('Settings', 'woocommerce') . '</a>';
    }
    return $links;
}

add_action('plugins_loaded', 'load_sensebank_textdomain');
function load_sensebank_textdomain()
{
    $res = load_plugin_textdomain('wc-sensebank-text-domain', false, dirname(plugin_basename(__FILE__)) . '/lang');
}

/**
 * WC PaymentSensebank Payments gateway plugin class.
 *
 * @class WC_PaymentSensebank_Payments
 */
class WC_PaymentSensebank_Payments
{
	/**
	 * Plugin bootstrapping.
	 */
    public static function init()
    {
        add_action('plugins_loaded', array(__CLASS__, 'includes'), 0);
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));
        add_action('woocommerce_blocks_loaded', array(__CLASS__, 'woocommerce_gateway_sensebank_woocommerce_block_support'));
    }

	/**
	 * Add the PaymentSensebank Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
    public static function add_gateway($gateways)
    {
        $options = get_option('woocommerce_sensebank_settings', array());
        $gateways[] = 'WC_Gateway_PaymentSensebank';
        return $gateways;
    }

	/**
	 * Plugin includes.
	 */
    public static function includes()
    {
        if (class_exists('WC_Payment_Gateway')) {
            require_once 'includes/class-wc-gateway-sensebank.php';
        }
    }

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
    public static function plugin_url()
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
    public static function plugin_abspath()
    {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
    public static function woocommerce_gateway_sensebank_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once 'includes/blocks/class-wc-sensebank-payments-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_Gateway_PaymentSensebank_Blocks_Support());
                }
            );
        }
    }
}
WC_PaymentSensebank_Payments::init();
