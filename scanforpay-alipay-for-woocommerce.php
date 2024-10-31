<?php
/*
 * Plugin Name: ScanForPay - Alipay & AlipayHK & WechatPay for WooCommerce (香港支付寶微信收款)
 * Description: ScanForPay - Accept cross border Alipay & AlipayHK & WechatPay in WooCommerce, settle with HKD, pay by RMB & HKD. 支持內地支付寶、AlipayHK、WechatPay收款, 付人民幣和港幣, 結算港幣.
 * Version: 1.1.3
 * Author: ScanForPay
 * Author URI: https://hk.scanforpay.com
 * License: GPL
 */
if (! defined ( 'ABSPATH' )) exit ();

add_action( 'plugins_loaded', 'init_scanforpay_gateway' );
function init_scanforpay_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	define('SCANFORPAY_FILE',__FILE__);
	define('SCANFORPAY_URL',rtrim(plugin_dir_url(SCANFORPAY_FILE),'/'));

	include_once( 'includes/wc-scanforpay-api.php' );
	include_once( 'includes/wc-scanforpay-gateway.php' );
	include_once( 'includes/wc-scanforpay-alipayhk-online-gateway.php' );
	include_once( 'includes/wc-scanforpay-wechat-gateway.php' );

	function add_your_gateway_class( $methods ) {
	    $methods[] = 'WC_ScanForPay'; 
	    $methods[] = 'WC_ScanForPay_AlipayHK_Online';
	    $methods[] = 'WC_ScanForPay_Wechat';
	    return $methods;
	}
	

	add_filter( 'woocommerce_payment_gateways', 'add_your_gateway_class' );

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'scanforpay_settings' );
	function scanforpay_settings( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=scanforpay' ) . '">' .  'Settings' . '</a>',
		);

		// Merge our new link with the default ones
		return array_merge( $plugin_links, $links );	
	}
}
	
?>