<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class ScanForPay_API{

	private static $sandbox = false;
	private static $storeNo= '';
	private static $partnerNo = '';
	private static $signKey = '';
	private static $paymentType = '1';
	private static $wallet = '';


	public static function set_wallet( $wallet ){
		self::$wallet = $wallet;
	}	
	public static function get_wallet(){
		return self::$wallet;
	}

	public static function set_paymentType( $paymentType) {
		self::$paymentType = $paymentType;
	}

	public static function set_partnerNo( $partnerNo ) {
		self::$partnerNo = $partnerNo;
	}

	public static function get_partnerNo() {
		return self::$partnerNo;
    }

    public static function set_sandbox( $sandbox ) {
		self::$sandbox = $sandbox;
	}

	public static function get_sandbox() {
		return self::$sandbox;
    }

    public static function set_storeNo( $storeNo) {
		self::$storeNo= $storeNo;
	}

	public static function get_storeNo() {
		return self::$storeNo;
    }

    public static function set_signKey( $signKey ) {
		self::$signKey = $signKey;
	}

	public static function get_signKey() {
		return self::$signKey;
    }

	public static function construct_request($body){
		$header = array(
			'version' => '1.0', 
			'partnerNo' => self::get_partnerNo(),
			'reqMsgId' => '',
			'reqTime' => date(DATE_ATOM),
			'signType' => 'SHA256'
		);
		$request = array(
			'header' => $header,
			'body' => $body
		);
		$sign_str = json_encode($request);
		$sign = hash('sha256', $sign_str.self::get_signKey());
		$final_req = array('request' => $request, 'signature' => $sign);
		return json_encode($final_req);
	}

	public static function get_host(){
		if(self::get_sandbox() == 'yes'){
			//return "http://192.168.31.200:8002";
			return "https://dev-pay.scan4pay.com";
		}else{
			return "https://pay.scanforpay.com";
		} 
	}

	public static function get_create_request($order, $wallet) {
		$partnerOrderNo = date_i18n("ymdHis").$order->get_id();
		update_post_meta($order->get_id(), 'scanforpay_order_id', $partnerOrderNo);

		$currency =method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;

		$body = array(
			'storeNo' => self::get_storeNo(),
			'partnerOrderNo' => $partnerOrderNo,
			'wallet' => $wallet,
			'orderAmount' => round(floatval($order->get_total() * 100), 2),  // 最小單位為0.01元的貨幣需乘以100, 但這裡如何判斷最小單位呢?
			'orderTitle' => self::get_order_title($order),
			'operatorId' => '',
			'terminalNo' => '',
			'currency' => $currency,
			'notifyUrl' => get_site_url().'/?wc-api=payment_notify',
			'returnUrl' => get_site_url().'/?wc-api=payment_callback&partnerOrderNo='.$partnerOrderNo
		);
		return self::construct_request($body);
	}
	public static function valide_sign($result_str){
		$arr = explode("response", $result_str);
		$brr = explode("signature", $arr[1]);
		$response = substr($brr[0],2,-2);
		$signature = substr($brr[1], 3, -2);


	    $sign = hash('sha256', $response.self::get_signKey());

	    if($sign == $signature) {
	    	return true;
	    }else{
	    	return false;
	    }
	}


    public static function get_order_title($order,$limit=32){
	    $title ="";
		$order_items = $order->get_items();
		if($order_items){
		    $qty = 0;
		    foreach ($order_items as $item_id =>$item){
		    	if($qty == 0){
			        $title.="{$item['name']}";
		    	}
		    	$qty += $item['quantity'];
		    }
		    if($qty>1){
		        $title.='等共'.$qty.'件商品';
		    }
		}
		return $title;
	}

	public static function create_order($order, $wallet)
	{

		// $currency =method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;
		// if($currency != 'HKD'){
		// 	throw new Exception('只接受港幣定價~');
		// 	return;
		// }
		# code...
		$body = self::get_create_request( $order, $wallet );
	    $options = array(
        	'http' => array(
	            'method' => 'POST',
	            'header' => 'Content-type:application/json',
	            'content' => $body,
	            'timeout' => 15 * 60 // 超时时间（单位:s）
        	)
    	);
	    $context = stream_context_create($options);
	    
	    $req_path = "";
	    if(self::$paymentType == "1"){
	    	$req_path = self::get_host().'/api/offline/qrcode';
	    } else if(self::$paymentType == "2"){
	    	$req_path = self::get_host().'/api/online/create';
	    }
    	$result = file_get_contents($req_path, false, $context);
    	if(!$result){
			$error = error_get_last();
			// echo"HTTP request failed. Error was:". $error['message'];
	   		wc_add_notice( __('Payment error:', 'woothemes') . $error['message'], 'error' );
			return;
    	}else{
    		$json_res = json_decode($result, true);

		   	$isValid = self::valide_sign($result);
		   	if(!$isValid){
		   		wc_add_notice( __('Payment error:', 'woothemes') . "签名校验失败", 'error' );
		   		return;
		   	}
		   	$body = $json_res["response"]["body"];
		   	if($body["code"] != 1){
		   		wc_add_notice( __('Payment error:', 'woothemes') . $body["msg"], 'error' );
				return;
		   	}
		   	return $body["payUrl"];
    	}
	}
	public static function get_query_order_request($partner_order_id){
		$body = array(
			'storeNo' => self::get_storeNo(),
			'partnerOrderNo' => $partner_order_id
		);
		return self::construct_request($body); 
	}
	public static function query_order($partner_order_id){
		# code...
		$body = self::get_query_order_request( $partner_order_id );
		
	    $options = array(
        	'http' => array(
	            'method' => 'POST',
	            'header' => 'Content-type:application/json',
	            'content' => $body,
	            'timeout' => 15 * 60 // 超时时间（单位:s）
        	)
    	);
	    $context = stream_context_create($options);
	    $result = file_get_contents(self::get_host().'/api/common/payQuery', false, $context);
	    if(!$result){
			$error = error_get_last();
	   		wc_add_notice( __('Payment error:', 'woothemes') . $error['message'], 'error' );
			return;
    	}else{
		  	$json_res = json_decode($result, true);

		   	$isValid = self::valide_sign($result);
		   	if(!$isValid){
		   		wc_add_notice( __('Payment error:', 'woothemes') . "签名校验失败", 'error' );
		   		return;
		   	}
		   	$body = $json_res["response"]["body"];
		   	if($body["code"] != 1){
		   		wc_add_notice( __('Payment error:', 'woothemes') . $body["msg"], 'error' );
				return;
		   	}
		   	return $body;
	   }
	}
	private static function get_refund_request($order_id,$amount, $reason, $refundNo){
		$partnerOrderNo = get_post_meta($order_id, 'scanforpay_order_id',true);

		$body = array(
			'refundNo' => $refundNo,
			'partnerOrderNo' => $partnerOrderNo,
			'orderAmount' => $amount,
			'tradeSummary' => $reason || '',
			'operatorId' => ''
		);
		return self::construct_request($body); 
	}
	public static function refund($order_id, $amount, $reason){
		# code...
		$refundNo = 'r_'.date_i18n("ymdHis").$order_id;
		$body = self::get_refund_request( $order_id, $amount, $reason, $refundNo);
		
	    $options = array(
        	'http' => array(
	            'method' => 'POST',
	            'header' => 'Content-type:application/json',
	            'content' => $body,
	            'timeout' => 15 * 60 // 超时时间（单位:s）
        	)
    	);
	    $context = stream_context_create($options);
	    $result = file_get_contents(self::get_host().'/api/common/refund', false, $context);
		if(!$result){
			$error = error_get_last();
			// echo"HTTP request failed. Error was:". $error['message'];
	   		wc_add_notice( __('Payment error:', 'woothemes') . $error['message'], 'error' );
			return;
    	}else{
		  	$json_res = json_decode($result, true);

		   	$isValid = self::valide_sign($result);
		   	if(!$isValid){
		   		WC_ScanForPay::log("签名校验失败", "error");
		   		wc_add_notice( __('Payment error:', 'woothemes') . "Signature Verify Failed.", 'error' );
		   		return;
		   	}
		   	$body = $json_res["response"]["body"];
		   	if($body["code"] != 1){
		   		WC_ScanForPay::log("Refund fail: ".$body["msg"], "error");
		   		wc_add_notice( __('Payment error:', 'woothemes') . $body["msg"], 'error' );
				return;
	   	}
		
		if(!isset($body["refundNo"])){
			$body["refundNo"] = $refundNo;
		}
	   	return $body;
	   }
	}
}
?>
