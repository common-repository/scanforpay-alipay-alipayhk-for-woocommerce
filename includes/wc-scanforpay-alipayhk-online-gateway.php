<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_ScanForPay_AlipayHK_Online extends WC_Payment_Gateway{	

	/**
	 * Whether or not logging is enabled
	 *
	 * @var bool
	 */
	public static $log_enabled = false;

	/**
	 * Logger instance
	 *
	 * @var WC_Logger
	 */
	public static $log = false;


	public function __construct(){
		$this->id = "scanforpay-alipayhk-online"; // 你的网关的唯一ID，例如，“your_gateway”
		$this->icon = "https://pay.scanforpay.com/static/plugin/alipayhk.svg";
		//如果你想要付款字段在结算页面显示，就设置为true（如果做的直接集成） 
		$this->has_fields = false;
		//显示在后台的付款方式的标题
		$this->method_title = 'ScanForPay-AlipayHK';
		//显示在后台的付款方式的描述。
		$this->method_description = 'Alipay & AlipayHK收款由<a href="https://hk.scanforpay.com" target="_blank">ScanForPay</a>提供';
		$this->supports[]='refunds';
		$main_settings  = get_option( 'woocommerce_scanforpay_settings' );

		$this->init_form_fields();

		$this->init_settings();
 
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			if($main_settings['paymentType'] == 1 && $setting_key == 'enabled'){
				$this->$setting_key = false;
			}else {
				$this->$setting_key = $value;				
			}
		}
		ScanForPay_API::set_partnerNo($main_settings['partnerNo']);
		ScanForPay_API::set_sandbox($main_settings['sandbox']);
		ScanForPay_API::set_storeNo($main_settings['merchantId']);
		ScanForPay_API::set_signKey($main_settings['signKey']);
		// ScanForPay_API::set_paymentType(2); // AlipayHK線上支付,只支持網頁跳轉。
		// ScanForPay_API::set_wallet("AlipayHK");

		self::$log_enabled    = $main_settings['sandbox'];


		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		// 通知的action
		add_action( 'woocommerce_api_payment_notify', array( $this, 'wc_payment_notify' ) );
		add_action( 'woocommerce_api_payment_callback', array( $this, 'wc_payment_callback' ) );
			
	}
	
	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		// Maybe clear logs.
		if ( 'yes' !== $this->get_option( 'sandbox', 'no' ) ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->clear( 'scanforpay' );
		}

		return $saved;
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'scanforpay' ) );
		}
	}


	

	// public function get_icon() {
	// 	$icons_str = '<img src="' . SCANFORPAY_URL . '/images/white_logo.png" class="right-float" alt="Alipay" />';

	// 	return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	// }

	function init_form_fields(){
		$this->form_fields = array(
		    'enabled' => array(
		        'title' => '開啟/關閉',
		        'type' => 'checkbox',
		        'label' => '開啟ScanForPay',
		        'default' => 'yes'
		    ),
		    'title' => array(
		        'title' => '標題',
		        'type' => 'text',
		        'default' => 'AlipayHK',
		    ),
		    'description' => array(
		        'title' => '描述',
		        'type' => 'text',
		        'default' => '使用AlipayHK掃描二維碼支付',
                'desc_tip'    => true,
		    ),
		    // 'sandbox' => array(
		    // 	'title' => '測試',
		    // 	'type' => 'checkbox',
		    //     'label' => '使用測試環境',
		    //     'default' => 'no'
		    // ),
		    // 'merchantId' => array(
		    // 	'title' => '门店编号',
		    // 	'type' => 'text',
		    //     'default' => '',
		    //     'description' => '* Get your merchant account from <a href="https://hk.scanforpay.com" target="_blank">ScanForPay</a> or contact us by (852)-81207808. <br/>您需要先從<a  href="https://hk.scanforpay.com" target="_blank">ScanForPay</a>獲取商戶帳戶, 或者您可以直接撥打(852)-81207808聯繫我們',
		    // ),
		    // 'partnerNo' => array(
		    // 	'title' => 'PartnerNo',
		    // 	'type' => 'text',
		    //     'default' => ''
		    // ),
		    // 'signKey' => array(
		    // 	'title' => 'Sign Key',
		    // 	'type' => 'text',
		    //     'default' => ''
		    // ),
		);
	}

	function process_payment( $order_id ) {
	    $order = wc_get_order( $order_id );

	    if(!$order||!$order->needs_payment()){
			return array(
	             'result'   => 'success',
	             'redirect' => $this->get_return_url($order)
	         );
		}

		$result = ScanForPay_API::create_order($order, 'AlipayHK');

		if(!$result){
			return array('result'   => 'fail');
		}
		return array(
				'result'   => 'success',
				'redirect' => $result
		);	
	}
	public function wc_payment_notify(){
		// 打印日志记录
		$json = $GLOBALS['HTTP_RAW_POST_DATA'];
		if(empty($json)){
			$json = file_get_contents("php://input");
		}
		if(empty($json)){	   	
			exit("fail");
		}
		// print_r($json);
		$isValid = ScanForPay_API::valide_sign($json);
		if(!$isValid){
			print_r("签名校验失败");
	   		exit;
	   	}
		$result = json_decode($json, true);
	   	$body = $result["response"]["body"];

		$partnerOrderNo = $body["partnerOrderNo"];

		if(empty($partnerOrderNo)){
			print "partnerOrderNo为空";
			exit;
		}
		$order = new WC_Order(substr($partnerOrderNo, 12));
		if( !$order || !$order->needs_payment() ){
			print_r("success");
			exit;
		}
		if ($order && $order->needs_payment() ){
			$order->payment_complete($order->order_id);
			print_r("success");
			exit;
		}
		exit('fail');
	}

	public function wc_payment_callback(){
		$partnerOrderNo = $_POST["partnerOrderNo"];
		if(empty($partnerOrderNo)){
			$post_data = $GLOBALS['HTTP_RAW_POST_DATA'];
			if(empty($post_data)){
				$post_data = file_get_contents("php://input");
			}
			if(empty($post_data)){
				print_r("回调参数错误");
				exit;
			}
			print_r($post_data);
		}

		$order_id = substr($partnerOrderNo, 12);
		$order = new WC_Order($order_id);
		$result = ScanForPay_API::query_order($partnerOrderNo);

		if($result && $result["status"] == 1){
			// 支付成功
			if( $order && $order->needs_payment() ){
				$order->payment_complete($order->order_id);
			    header('Location: ' . $this->get_return_url($order), true, 301);
				exit;
			} else {
				header('Location: ' . $this->get_return_url($order), true, 301);
			}
		}
		exit;
	}

	public function process_refund( $order_id, $amount = null, $reason = ''){
		$this->log( 'Refund: ' . $order_id.','.$amount.','.$reason, 'info' );

		$order = new WC_Order ($order_id );
		if(!$order){
			return new WP_Error( 'invalid_order', 'Wrong Order' );
		}
		
		$total = (int)($order->get_total () * 100);
		$payAmount = (int) ($amount * 100);
		if( $payAmount <= 0 || $payAmount > $total ){
			return new WP_Error( 'invalid_order','Invalid Amount ');
		}

		$result = ScanForPay_API::refund($order_id, $payAmount, $reason);

		if(!$result){
			return new WP_Error( 'refund_error', 'Refund Failed');
		}

		if($result["status"] != 1){
			return new WP_Error( 'refund_error', 'Refund Failed');	
		}

		$order->add_order_note(
			sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce' ), $amount, $result["refundNo"], $reason )
		);
		return true;
	}
}
?>
