<?php
/*
e-SCOTT Smart Settlement module
Version: 1.1.0
Author: Collne Inc.

*/

class ESCOTT_SETTLEMENT
{
	private $error_mes, $pay_method;
	public static $opts;
	
	public function __construct(){
	
		$this->pay_method = array(
			'acting_escott_card',
			'acting_escott_conv'
		);
		
		self::initialize_data();
		
		if( is_admin() ){
			add_action( 'usces_action_settlement_tab_title',	 array( $this, 'tab_title') );
			add_action( 'usces_action_settlement_tab_body',		 array( $this, 'tab_body') );
			add_action( 'usces_action_admin_settlement_update',	 array( $this, 'data_update') );
		}
		
		if( $this->is_activate_card() || $this->is_activate_conv() ){

			add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment'), 10, 5 );
			add_filter( 'usces_filter_is_complete_settlement', array( $this, 'is_complete_settlement'), 10, 3 );
			add_action( 'usces_action_revival_order_data', array( $this, 'revival_order_data' ), 10, 3 );

			if( is_admin() ){
			
				add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settle_info_field_keys') );
				add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settle_info_field_value'), 10, 3 );
			//	add_action( 'usces_action_post_update_memberdata', array( $this, 'admin_update_memberdata'), 10, 2 );
				
			}else{
			
				add_filter( 'usces_filter_delivery_secure_form_loop', array( $this, 'delivery_secure_form'), 10, 2 );
				add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ), 9 );
				add_action( 'wp_footer', array( $this, 'delivery_secure_js'), 9 );
				add_filter( 'usces_filter_payments_str', array( $this, 'payments_str'), 10, 2 );
				add_filter( 'usces_filter_payments_arr', array( $this, 'payments_arr'), 10, 2 );
				add_filter( 'usces_filter_delivery_check', array( $this, 'delivery_check'), 15 );
				add_filter( 'usces_filter_confirm_inform', array( $this, 'confirm_inform'), 10, 5 );
				add_action( 'usces_action_confirm_page_point_inform', array( $this, 'point_inform'), 10, 5 );
				add_filter( 'usces_filter_confirm_point_inform', array( $this, 'point_inform_filter'), 10, 5 );
				add_filter( 'wccp_filter_coupon_inform', array( $this, 'point_inform_filter'), 10, 5 );
				//add_filter( 'usces_purchase_check', array( $this, 'purchase'), 5 );
				add_action( 'usces_action_acting_processing', array( $this, 'acting_processing' ), 10, 2 );
				add_filter( 'usces_filter_check_acting_return_results', array( $this, 'acting_return') );
				add_filter( 'usces_filter_check_acting_return_duplicate', array( $this, 'check_acting_return_duplicate'), 10, 2 );
				add_action( 'usces_action_reg_orderdata', array( $this, 'reg_order_metadata') );
				add_filter( 'usces_filter_get_error_settlement', array( $this, 'error_page_mesage') );
				add_action( 'usces_post_reg_orderdata', array( $this, 'order_status_change'), 10, 2 );
				add_filter( 'usces_filter_send_order_mail_payment', array( $this, 'order_mail_payment'), 10, 6 );
			//	add_action( 'plugins_loaded', array( $this, 'conv_result_notification') );
			//	add_action( 'usces_action_edit_memberdata', array( $this, 'edit_memberdata'), 10, 2 );
				$this->conv_result_notification();
			}
		}

		if( $this->is_activate_card() ) {
			add_filter( 'usces_filter_delete_member_check', array( $this, 'delete_member_check' ), 10, 2 );
		}
	}
	/**********************************************
	* usces_filter_is_complete_settlement
	* ポイント即時付与
	* @param  $complete, $payment_name, $status
	* @return boorean $mes
	***********************************************/
	public function is_complete_settlement( $complete, $payment_name, $status ){
		$payments = usces_get_system_option( 'usces_payment_method', 'name' );
		if( isset($payments[$payment_name]) && 'acting_escott_card' == $payments[$payment_name]['settlement'] ){
			$complete = true;
		}
		return $complete;
	}
	
	/**********************************************
	* usces_filter_delivery_check
	* カード情報入力チェック
	* @param  $mes
	* @return str $mes
	***********************************************/
	public function delivery_check( $mes ){
		global $usces;
		
		if( !isset($_POST['offer']['payment_name']) )
			return $mes;

		$payments = $usces->getPayments($_POST['offer']['payment_name']);
		if( 'acting_escott_card' == $payments['settlement'] ){
			$total_items_price = $usces->get_total_price();
			if( 1 > $total_items_price )
				$mes .= sprintf(__('A total products amount of money surpasses the upper limit(%s) that I can purchase in C.O.D.', 'usces'), usces_crform($this->options['cod_limit_amount'], true, false, 'return')) . "<br />";

			if( ( isset($_POST['acting']) && 'escott' == $_POST['acting'] ) && 
				( isset($_POST['cnum1']) && empty($_POST['cnum1']) ) || 
				( isset($_POST['securecode']) && empty($_POST['securecode']) ) || 
				( isset($_POST['expyy']) && empty($_POST['expyy']) ) || 
				( isset($_POST['expmm']) && empty($_POST['expmm']) ) || 
				( isset($_POST['username_card']) && empty($_POST['username_card']) )
			){
				$mes .= __('Please enter the card information correctly.', 'usces') . "<br />";
			}

		}
		return $mes;
	}
	
	/**********************************************
	* init
	* コンビニ及びペイジー入金通知処理
	* @param  -
	* @return -
	***********************************************/
	public function conv_result_notification(){
		global $wpdb, $usces;

		if(isset($_REQUEST['TransactionId'])){
			usces_log('Conv test : '.print_r($_REQUEST, true), 'acting_transaction.log');
		}
		if( isset($_REQUEST['TransactionId']) && isset($_REQUEST['MerchantFree2']) && 'acting_escott_conv' == $_REQUEST['MerchantFree2'] && isset($_REQUEST['RecvNum']) && isset($_REQUEST['NyukinDate']) ){
			
			global $wpdb;
			$order_meta_table_name = $wpdb->prefix . "usces_order_meta";
			$query = $wpdb->prepare("SELECT order_id FROM $order_meta_table_name WHERE meta_key = %s", 
									$_REQUEST['MerchantFree1']);
			$order_id = $wpdb->get_var($query);

			//オーダーステータス変更
			usces_change_order_receipt( $order_id, 'receipted' );
			//ポイント付与
			usces_action_acting_getpoint( $order_id );
	
			$acting_flag = $_REQUEST['MerchantFree2'];
			$value = $usces->get_order_meta_value( $acting_flag, $order_id );
			$results = unserialize($value);
			$meta_value = serialize(array_merge( $results, $_REQUEST ));
			$usces->set_order_meta_value( $acting_flag, $meta_value, $order_id );
			usces_log('e-SCOTT Conv return : '.print_r($_REQUEST, true), 'acting_transaction.log');
			header("HTTP/1.0 200 OK");
			die();
			
		}
	}
 
	/**********************************************
	* usces_post_reg_orderdata
	* 初期入金状況を「未入金」に指定
	* コンビニURLを保存
	* @param  $order_id, $results
	* @return -
	***********************************************/
	public function order_status_change( $order_id, $results ){
		global $usces;
		if( isset($results['acting']) && 'escott_conv' == $results['acting'] ){
			usces_change_order_receipt( $order_id, 'noreceipt' );
			$acting_opts = $usces->options['acting_settings']['escott'];
			$FreeArea = trim($results['FreeArea']);
			$url = add_query_arg( array('code'=>$FreeArea, 'rkbn'=>2), $acting_opts['redirect_url_conv']);
			$usces->set_order_meta_value( 'escott_conv_url', $url, $order_id );
		}
	}
 
	/**********************************************
	* usces_filter_order_confirm_mail_payment
	* オンライン収納代行決済用管理メール
	* @param  $msg_payment, $order_id, $payment, $cart, $data
	* @return $msg_payment
	***********************************************/
	public function order_confirm_mail_payment( $msg_payment, $order_id, $payment, $cart, $data ){
		global $usces;
		if( 'acting_escott_conv' != $payment['settlement'] || ('orderConfirmMail' != $_POST['mode'] && 'changeConfirmMail' != $_POST['mode']) )
			return $msg_payment;
			
		
		$acting_opts = $usces->options['acting_settings']['escott'];
		$url = $usces->get_order_meta_value( 'escott_conv_url', $order_id );
		$msg_payment .= "お支払いの有効期限は" . $acting_opts['conv_limit'] . "日間となっております。\r\n";
		$msg_payment .= "まだお支払いが完了していない場合は、下記のURLからお支払いのお手続きをお願いいたします。\r\n\r\n";
		$msg_payment .= "[お支払いURL]\r\n";
		$msg_payment .= $url . "\r\n";
		return $msg_payment;
	}
 
	/**********************************************
	* usces_filter_send_order_mail_payment
	* オンライン収納代行決済用サンキューメール
	* @param  $msg_payment, $order_id, $payment, $cart, $entry, $data
	* @return $msg_payment
	***********************************************/
	public function order_mail_payment( $msg_payment, $order_id, $payment, $cart, $entry, $data ){
		global $usces;
		if( 'acting_escott_conv' != $payment['settlement'] )
			return $msg_payment;
			
		$acting_opts = $usces->options['acting_settings']['escott'];
		$url = $usces->get_order_meta_value( 'escott_conv_url', $order_id );
		$msg_payment .= "お支払いの有効期限は" . $acting_opts['conv_limit'] . "日間となっております。\r\n";
		$msg_payment .= "まだお支払いが完了していない場合は、下記のURLからお支払いのお手続きをお願いいたします。\r\n\r\n";
		$msg_payment .= "[お支払いURL]\r\n";
		$msg_payment .= $url . "\r\n";
		return $msg_payment;
	}
 
	/**********************************************
	* usces_filter_get_error_settlement
	* カード決済用エラーメッセージ
	* @param  $keys
	* @return str $keys
	***********************************************/
	public function error_page_mesage( $res ){
		$html = '';
		$acting = ( isset($_REQUEST['MerchantFree2']) ) ? $_REQUEST['MerchantFree2'] : '';
		switch( $acting ) {
		case 'acting_escott_card':
			if( isset($_REQUEST['MerchantFree1']) && usces_get_order_id_by_trans_id( (int)$_REQUEST['MerchantFree1'] ) ) {
				$html .= '<div class="error_page_mesage">
				<p>'.__('Your order has already we complete.','usces').'</p>
				<p>'.__('Please do not re-display this page.','usces').'</p>
				</div>';
			} else {
				$error_message = array();
				$responsecd = explode( '|', $_REQUEST['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$error_message[] = $this->error_message( $cd );
				}
				$error_message = array_unique( $error_message );
				if( 0 < count($error_message) ) {
					$html .= '<div class="error_page_mesage">
					<p>'.__('Error code','usces').'：'.$_REQUEST['ResponseCd'].'</p>';
					foreach( $error_message as $message ) {
						$html .= '<p>'.$message.'</p>';
					}
					$html .= '
					<p class="return_settlement"><a href="'.add_query_arg( array( 'backDelivery'=>'escott_card', 're-enter'=>1 ), USCES_CART_URL ).'">'.__('Card number re-enter','usces').'</a></p>
					</div>';
				}
			}
			break;

		case 'acting_escott_conv':
			$error_message = array();
			$responsecd = explode( '|', $_REQUEST['ResponseCd'] );
			foreach( (array)$responsecd as $cd ) {
				$error_message[] = $this->error_message( $cd );
			}
			$error_message = array_unique( $error_message );
			if( 0 < count($error_message) ) {
				$html .= '<div class="error_page_mesage">
				<p>'.__('Error code','usces').'：'.$_REQUEST['ResponseCd'].'</p>';
				foreach( $error_message as $message ) {
					$html .= '<p>'.$message.'</p>';
				}
			}
			$html .= '</div>';
			break;

		}
		return $html;
	}

	/**********************************************
	* usces_filter_settle_info_field_keys
	* 受注編集画面に表示する決済情報のキー
	* @param  $keys
	* @return array $keys
	***********************************************/
	public function settle_info_field_keys( $keys ){
		$array = array_merge( $keys, array('MerchantFree1','ResponseCd', 'PayType','CardNo','CardExp','KessaiNumber', 'NyukinDate', 'CvsCd') );
		return $array;
	}
	
	/**********************************************
	* usces_filter_settle_info_field_value
	* 受注編集画面に表示する決済情報の値整形
	* @param  $value, $key, $acting
	* @return str $value
	***********************************************/
	public function settle_info_field_value( $value, $key, $acting ){
		if( 'escott_card' != $acting && 'escott_conv' != $acting )
			return $value;

		switch($key){
			case 'acting':
				switch($value){
					case 'escott_card':
						$value = 'e-SCOTT カード決済';
						break;
					case 'escott_conv':
						$value = 'e-SCOTT オンライン収納';
						break;
				
				}
				break;
			case 'CvsCd':
				switch($value){
					case 'LSN':
						$value = 'ローソン';
						break;
					case 'FAM':
						$value = 'ファミリーマート';
						break;
					case 'SAK':
						$value = 'サンクス';
						break;
					case 'CCK':
						$value = 'サークルK';
						break;
					case 'ATM':
						$value = 'Pay-easy（ATM）';
						break;
					case 'ONL':
						$value = 'Pay-easy（オンライン）';
						break;
					case 'LNK':
						$value = 'Pay-easy（情報リンク）';
						break;
					case 'SEV':
						$value = 'セブンイレブン';
						break;
					case 'MNS':
						$value = 'ミニストップ';
						break;
					case 'DAY':
						$value = 'デイリーヤマザキ';
						break;
					case 'EBK':
						$value = '楽天銀行';
						break;
					case 'JNB':
						$value = 'ジャパンネット銀行';
						break;
					case 'EDY':
						$value = 'Edy';
						break;
					case 'SUI':
						$value = 'Suica';
						break;
					case 'FFF':
						$value = 'スリーエフ';
						break;
					case 'JIB':
						$value = 'じぶん銀行';
						break;
					case 'SNB':
						$value = '住信SBIネット銀行';
						break;
					case 'SCM':
						$value = 'セイコーマート';
						break;
				}
				break;

		}
		return $value;
	}
	
	/**********************************************
	* usces_action_reg_orderdata
	* オーダーメタ保存
	* @param  $args = array(
				'cart'=>$cart, 'entry'=>$entry, 'order_id'=>$order_id, 
				'member_id'=>$member['ID'], 'payments'=>$set, 'charging_type'=>$charging_type, 
				'results'=>$results
				);
	* @return -
	***********************************************/
	public function reg_order_metadata( $args ){
		global $usces;
		extract($args);

		$acting_flag = ( 'acting' == $payments['settlement'] ) ? $payments['module'] : $payments['settlement'];
		if( 'acting_escott_card' != $acting_flag && 'acting_escott_conv' != $acting_flag )
			return;
			
		if( !$entry['order']['total_full_price'] )
			return;
		
		$usces->set_order_meta_value('trans_id', $results['MerchantFree1'], $order_id);
		$usces->set_order_meta_value('wc_trans_id', $results['TransactionId'], $order_id);

		$meta_value = serialize($results);
		$usces->set_order_meta_value( $acting_flag, $meta_value, $order_id );
		if( 'acting_escott_conv' == $acting_flag ){
			$usces->set_order_meta_value( $results['MerchantFree1'], $acting_flag, $order_id );
		}
	}
	
	/**********************************************
	* Revival order data
	* @param  -
	* @return -
	* @echo   -
	***********************************************/
	public function revival_order_data( $order_id, $log_key, $acting ) {
		global $usces;
		
		if( in_array($acting, $this->pay_method) ) {
			$results['MerchantFree1'] = $log_key;
			$usces->set_order_meta_value( $acting, serialize($results), $order_id );
			$usces->set_order_meta_value( 'trans_id', $log_key, $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', '', $order_id);
		}
		if( 'acting_escott_conv' == $acting ){
			usces_change_order_receipt( $order_id, 'noreceipt' );
			$usces->set_order_meta_value( $log_key, 'acting_escott_conv', $order_id );
		}
	}

	/**********************************************
	* usces_filter_check_acting_return_duplicate
	* 重複オーダー禁止処理
	* @param  $tid, $results
	* @return str RandId
	***********************************************/
	public function check_acting_return_duplicate( $tid, $results ){
		global $usces;
		$entry = $usces->cart->get_entry();
		if( !$entry['order']['total_full_price'] ){	
			return 'not_credit';
		}elseif( !isset($results['acting']) || ('escott_card' != $results['acting'] && 'escott_conv' != $results['acting']) ){
			return $tid;
		}else{	
			return $results['MerchantFree1'];
		}
	}

	/**********************************************
	* カード決済有効判定
	* @param  $paymod_base
	* @return boolean $res
	***********************************************/
	public function is_activate_card(){
		global $usces;
		
		if( 'on' == self::$opts['card_activate'] && 'on' == self::$opts['activate'] ){
			$res = true;
		}else{
			$res = false;
		}
		
		return $res;
	}
	public function is_activate_conv(){
		global $usces;
		
		if( 'on' == self::$opts['conv_activate'] && 'on' == self::$opts['activate'] ){
			$res = true;
		}else{
			$res = false;
		}
		
		return $res;
	}

	/**********************************************
	* usces_filter_payments_str
	* 支払方法JavaScript用決済名追加
	* @param  $payments_str $payment
	* @return str $payments_str
	***********************************************/
	public function payments_str( $payments_str, $payment ){
		global $usces;
		
		switch( $payment['settlement'] ){
		case 'acting_escott_card':
			$paymod_base = 'escott';
			if( 'on' == $usces->options['acting_settings'][$paymod_base]['card_activate'] 
				&& 'on' == $usces->options['acting_settings'][$paymod_base]['activate'] ){
				$payments_str .= "'" . $payment['name'] . "': '" . $paymod_base . "', ";
			}
			break;
		}
		return $payments_str;
	}
	
	/**********************************************
	* usces_filter_payments_arr
	* 支払方法JavaScript用決済追加
	* @param  $payments_arr $payment
	* @return str $payments_arr
	***********************************************/
	public function payments_arr( $payments_arr, $payment ){
		global $usces;
		
		switch( $payment['settlement'] ){
		case 'acting_escott_card':
			$paymod_base = 'escott';
			if( 'on' == $usces->options['acting_settings'][$paymod_base]['card_activate'] 
				&& 'on' == $usces->options['acting_settings'][$paymod_base]['activate'] ){
				$payments_arr[] = $paymod_base;
			}
			break;
		}
		return $payments_arr;
	}
	
	/**********************************************
	* wp_print_footer_scripts
	* JavaScript
	* @param  -
	* @return -
	***********************************************/
	function footer_scripts() {
		global $usces;

		//マイページ
		if( $usces->is_member_page($_SERVER['REQUEST_URI']) ):
			$member = $usces->get_member();
			$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
			if( !empty($KaiinId) ):
?>
<script type="text/javascript">
jQuery(document).ready(function($) {
	$("input[name='deletemember']").css("display","none");
});
</script>
<?php
			endif;
		endif;
	}

	/**********************************************
	* wp_footer
	* 支払方法ページ用入力フォーム
	* @param  $nouse $payment
	* @return str $html
	***********************************************/
	function delivery_secure_js(){
		global $usces;
		if( 'delivery' != $usces->page )
			return;
		
		if( !$this->is_validity_escot('card') )
			return;

	?>
	<script type='text/javascript'>
	(function($) {
		$("#escott_cnum").change(function(e){
			console.log($(this).val());
			var first_c = $(this).val().substr( 0, 1 );
			var second_c = $(this).val().substr( 1, 1 );
			if( '4' == first_c || '5' == first_c || ('3' == first_c && '5' == second_c) ){
				$("#escott_paytype_default").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype4535").removeAttr("disabled").css("display", "inline");
				$("#escott_paytype37").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype36").attr("disabled", "disabled").css("display", "none");
			}else if( '3' == first_c && '6' == second_c ){
				$("#escott_paytype_default").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype4535").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype37").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype36").removeAttr("disabled").css("display", "inline");
			}else if( '3' == first_c && '7' == second_c ){
				$("#escott_paytype_default").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype4535").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype37").removeAttr("disabled").css("display", "inline");
				$("#escott_paytype36").attr("disabled", "disabled").css("display", "none");
			}else{
				$("#escott_paytype_default").removeAttr("disabled").css("display", "inline");
				$("#escott_paytype4535").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype37").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype36").attr("disabled", "disabled").css("display", "none");
			}
		});
		if( $("#escott_cnum").length && $("#escott_cnum").val() ){
			var first_c = $("#escott_cnum").val().substr( 0, 1 );
			var second_c = $("#escott_cnum").val().substr( 1, 1 );
			if( '4' == first_c || '5' == first_c || ('3' == first_c && '5' == second_c) ){
				$("#escott_paytype_default").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype4535").removeAttr("disabled").css("display", "inline");
				$("#escott_paytype37").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype36").attr("disabled", "disabled").css("display", "none");
			}else if( '3' == first_c && '6' == second_c ){
				$("#escott_paytype_default").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype4535").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype37").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype36").removeAttr("disabled").css("display", "inline");
			}else if( '3' == first_c && '7' == second_c ){
				$("#escott_paytype_default").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype4535").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype37").removeAttr("disabled").css("display", "inline");
				$("#escott_paytype36").attr("disabled", "disabled").css("display", "none");
			}else{
				$("#escott_paytype_default").removeAttr("disabled").css("display", "inline");
				$("#escott_paytype4535").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype37").attr("disabled", "disabled").css("display", "none");
				$("#escott_paytype36").attr("disabled", "disabled").css("display", "none");
			}
		}
	})(jQuery);
	</script>
	<?php
	}
	/**********************************************
	* usces_filter_delivery_secure_form_loop
	* 支払方法ページ用入力フォーム
	* @param  $nouse $payment
	* @return str $html
	***********************************************/
	public function delivery_secure_form( $nouse, $payment ){
		global $usces;
		
		$paymod_id = 'escott';
		$html = '';
		switch( $payment['settlement'] ){
		case 'acting_escott_card':
		
			$acting_opts = $usces->options['acting_settings'][$paymod_id];

			if( 'on' != $acting_opts['card_activate'] 
				|| 'on' != $acting_opts['activate'] )
				continue;

			$cnum1 = isset( $_POST['cnum1'] ) ? esc_html($_POST['cnum1']) : '';
			$securecode = isset( $_POST['securecode'] ) ? esc_html($_POST['securecode']) : '';
			$expyy = isset( $_POST['expyy'] ) ? esc_html($_POST['expyy']) : '';
			$expmm = isset( $_POST['expmm'] ) ? esc_html($_POST['expmm']) : '';
			$username = isset( $_POST['username_card'] ) ? esc_html($_POST['username_card']) : '';
			$paytype = isset( $_POST['paytype'] ) ? esc_html($_POST['paytype']) : '1';

			$html .= '<input type="hidden" name="acting" value="escott">'."\n";
			$html .= '<table class="customer_form" id="' . $paymod_id . '">'."\n";

			if( $usces->is_member_logged_in() ){
				$member = $usces->get_member();
				$KaiinPass = $this->get_quick_pass( $member['ID'] );
				$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
				if( isset($_REQUEST['backDelivery']) && 'escott_card' == substr($_REQUEST['backDelivery'], 0, 11)){
					$this->edit_memberdata( NULL, $member['ID'] );
				}
			}
			$res['ResponseCd'] = '';
			if( 'on' == $acting_opts['quickpay'] && !empty($KaiinPass) ){
			
				$param_list = array(); 
				$params = array();
				$TransactionDate = date('Ymd', current_time('timestamp'));
				// 共通部 
				$param_list['MerchantId'] = urlencode( $acting_opts['merchant_id'] ); 
				$param_list['MerchantPass'] = urlencode( $acting_opts['merchant_pass'] ); 
				$param_list['TransactionDate'] = urlencode( $TransactionDate ); 
				$param_list['MerchantFree3'] = urlencode( "wc1collne" ); 
				$params['send_url'] = $acting_opts['send_url_member'];
				$params['param_list'] = array_merge($param_list, 
					array(
						'OperateId' => '4MemRefM',
						'KaiinPass' => $KaiinPass,
						'KaiinId' => $KaiinId
					)
				);//escott会員照会
				$res = $this->connection( $params );
			}
			if( 'OK' == $res['ResponseCd'] && !( isset($_REQUEST['backDelivery']) && 'escott_card' == substr($_REQUEST['backDelivery'], 0, 11)) ){
				$expyy = substr(date('Y', current_time('timestamp')), 0, 2) . substr($res['CardExp'], 0, 2);
				$expmm = substr($res['CardExp'], 2, 2);
				$html .= '<input name="cnum1" type="hidden" value="8888888888888888" />
				<input name="expyy" type="hidden" value="' . $expyy . '" />
				<input name="expmm" type="hidden" value="' . $expmm . '" />
				<input name="username_card" type="hidden" value="QUICKPAY" />';
				$html .= '<tr>
				<th scope="row">'.__('The last four digits of your card number', 'usces').'</th>
				<td colspan="2"><p>' . substr($res['CardNo'], -4) . ' (<a href="' . add_query_arg( array('backDelivery'=>'escott_card'), USCES_CART_URL ) . '#cardchange">'.__('Change of card information, click here', 'usces').'</a>)</p></td>
				</tr>';
//				$html .= '<tr>
//				<th scope="row">'.__('security code', 'usces').'</th>
//				<td colspan="2"><input name="securecode" type="text" size="6" value="' . esc_attr($securecode) . '" />'.__('(Single-byte numbers only)', 'usces').'</td>
//				</tr>';
				$html .= '
				<tr>
				<th scope="row">'.__('Number of payments', 'usces').'</th>
				<td colspan="2"><p>'.__('One time payment', 'usces').'<input type="hidden" name="paytype" value="01" />';
				if( 2 <= $acting_opts['howtopay'] ){
					$html .= ' (<a href="' . add_query_arg( array('backDelivery'=>'escott_card'), USCES_CART_URL ) . '#cardchange">'.__('Change of number of payments, click here', 'usces').'</a>)';
				}
				$html .= '</p></td>
				</tr>
				';

			}else{
			
				$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __('(Single-byte numbers only)','usces').'<div class="attention">'.__('* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.','usces').'</div>' );
				$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __('(Single-byte numbers only)','usces') );
				$label = __('card number', 'usces');
				$html .= '<tr>
				<th scope="row">'.$label.'<input name="acting" type="hidden" value="escott" /></th>
				<td colspan="2"><input name="cnum1" type="text" id="escott_cnum" size="16" value="' . esc_attr($cnum1) . '" />'.$cardno_attention.'</td>
				</tr>';
				$html .= '<tr>
				<th scope="row">'.__('security code', 'usces').'</th>
				<td colspan="2"><input name="securecode" type="text" size="6" value="' . esc_attr($securecode) . '" />'.$seccd_attention.'</td>
				</tr>';
				$html .= '<tr>
				<th scope="row">'.__('Card expiration', 'usces').'</th>
				<td colspan="2">
				<select name="expmm">
					<option value=""' . (empty($expmm) ? ' selected="selected"' : '') . '>----</option>
					<option value="01"' . (('01' === $expmm) ? ' selected="selected"' : '') . '> 1</option>
					<option value="02"' . (('02' === $expmm) ? ' selected="selected"' : '') . '> 2</option>
					<option value="03"' . (('03' === $expmm) ? ' selected="selected"' : '') . '> 3</option>
					<option value="04"' . (('04' === $expmm) ? ' selected="selected"' : '') . '> 4</option>
					<option value="05"' . (('05' === $expmm) ? ' selected="selected"' : '') . '> 5</option>
					<option value="06"' . (('06' === $expmm) ? ' selected="selected"' : '') . '> 6</option>
					<option value="07"' . (('07' === $expmm) ? ' selected="selected"' : '') . '> 7</option>
					<option value="08"' . (('08' === $expmm) ? ' selected="selected"' : '') . '> 8</option>
					<option value="09"' . (('09' === $expmm) ? ' selected="selected"' : '') . '> 9</option>
					<option value="10"' . (('10' === $expmm) ? ' selected="selected"' : '') . '>10</option>
					<option value="11"' . (('11' === $expmm) ? ' selected="selected"' : '') . '>11</option>
					<option value="12"' . (('12' === $expmm) ? ' selected="selected"' : '') . '>12</option>
				</select>'.__('month', 'usces').'&nbsp;<select name="expyy">
					<option value=""' . (empty($expyy) ? ' selected="selected"' : '') . '>------</option>
				';
				for($i=0; $i<15; $i++){
					$year = date('Y') - 1 + $i;
					$html .= '
					<option value="' . $year . '"' . (($year == $expyy) ? ' selected="selected"' : '') . '>' . $year . '</option>';
				}
				$html .= '
				</select>'.__('year', 'usces').'</td>
				</tr>
				<tr>
				<th scope="row">'.__('Card name', 'usces').'</th>
				<td colspan="2"><input name="username_card" id="username_card_escott" type="text" size="30" value="' . esc_attr($username) . '" />'.__('(Alphabetic characters)', 'usces').'</td>
				</tr>';

				if( 1 === (int)$acting_opts['howtopay'] ){
	//				$html_paytype .= '
	//				<tr>
	//				<th scope="row">'.__('payment method', 'usces').'</th>
	//				<td colspan="2">'.__('One time payment', 'usces').'<input type="hidden" name="paytype" value="01" /></td>
	//				</tr>
	//				';
					$html .= '<input type="hidden" name="paytype" value="01" />';
				}elseif( 2 <= $acting_opts['howtopay'] ){
					$html_paytype = '';
					$html_paytype .= '
					<tr>
					<th scope="row">'.__('Number of payments', 'usces').'</th>
					<td colspan="2">';
					
					$html_paytype .= '
					<select name="paytype" id="escott_paytype_default" >
						<option value="01"' . (('01' == $paytype) ? ' selected="selected"' : '') . '>'.__('One time payment', 'usces').'</option>
					</select>';
					
					$html_paytype .= '
					<select name="paytype" id="escott_paytype4535" style="display:none;" disabled="disabled" >
						<option value="01"' . (('01' == $paytype) ? ' selected="selected"' : '') . '>1'.__('-time payment', 'usces').'</option>
						<option value="02"' . (('02' == $paytype) ? ' selected="selected"' : '') . '>2'.__('-time payment', 'usces').'</option>
						<option value="03"' . (('03' == $paytype) ? ' selected="selected"' : '') . '>3'.__('-time payment', 'usces').'</option>
						<option value="05"' . (('05' == $paytype) ? ' selected="selected"' : '') . '>5'.__('-time payment', 'usces').'</option>
						<option value="06"' . (('06' == $paytype) ? ' selected="selected"' : '') . '>6'.__('-time payment', 'usces').'</option>
						<option value="10"' . (('10' == $paytype) ? ' selected="selected"' : '') . '>10'.__('-time payment', 'usces').'</option>
						<option value="12"' . (('12' == $paytype) ? ' selected="selected"' : '') . '>12'.__('-time payment', 'usces').'</option>
						<option value="15"' . (('15' == $paytype) ? ' selected="selected"' : '') . '>15'.__('-time payment', 'usces').'</option>
						<option value="18"' . (('18' == $paytype) ? ' selected="selected"' : '') . '>18'.__('-time payment', 'usces').'</option>
						<option value="20"' . (('20' == $paytype) ? ' selected="selected"' : '') . '>20'.__('-time payment', 'usces').'</option>
						<option value="24"' . (('24' == $paytype) ? ' selected="selected"' : '') . '>24'.__('-time payment', 'usces').'</option>
						<option value="88"' . (('88' == $paytype) ? ' selected="selected"' : '') . '>'.__('Libor Funding pay', 'usces').'</option>';
					if( 3 == $acting_opts['howtopay'] ){
						$html_paytype .= '
						<option value="80"' . (('80' == $paytype) ? ' selected="selected"' : '') . '>'.__('Bonus lump-sum payment', 'usces').'</option>';
					}
					$html_paytype .= '</select>';
					
					$html_paytype .= '
					<select name="paytype" id="escott_paytype37" style="display:none;" disabled="disabled" >
						<option value="01"' . (('01' == $paytype) ? ' selected="selected"' : '') . '>1'.__('-time payment', 'usces').'</option>
						<option value="03"' . (('03' == $paytype) ? ' selected="selected"' : '') . '>3'.__('-time payment', 'usces').'</option>
						<option value="05"' . (('05' == $paytype) ? ' selected="selected"' : '') . '>5'.__('-time payment', 'usces').'</option>
						<option value="06"' . (('06' == $paytype) ? ' selected="selected"' : '') . '>6'.__('-time payment', 'usces').'</option>
						<option value="10"' . (('10' == $paytype) ? ' selected="selected"' : '') . '>10'.__('-time payment', 'usces').'</option>
						<option value="12"' . (('12' == $paytype) ? ' selected="selected"' : '') . '>12'.__('-time payment', 'usces').'</option>
						<option value="15"' . (('15' == $paytype) ? ' selected="selected"' : '') . '>15'.__('-time payment', 'usces').'</option>
						<option value="18"' . (('18' == $paytype) ? ' selected="selected"' : '') . '>18'.__('-time payment', 'usces').'</option>
						<option value="20"' . (('20' == $paytype) ? ' selected="selected"' : '') . '>20'.__('-time payment', 'usces').'</option>
						<option value="24"' . (('24' == $paytype) ? ' selected="selected"' : '') . '>24'.__('-time payment', 'usces').'</option>';
					if( 3 == $acting_opts['howtopay'] ){
						$html_paytype .= '
						<option value="80"' . (('80' == $paytype) ? ' selected="selected"' : '') . '>'.__('Bonus lump-sum payment', 'usces').'</option>';
					}
					$html_paytype .= '</select>';
					
					$html_paytype .= '
					<select name="paytype" id="escott_paytype36" style="display:none;" disabled="disabled" >
						<option value="01"' . (('01' == $paytype) ? ' selected="selected"' : '') . '>'.__('One time payment', 'usces').'</option>
						<option value="88"' . (('88' == $paytype) ? ' selected="selected"' : '') . '>'.__('Libor Funding pay', 'usces').'</option>';
					if( 3 == $acting_opts['howtopay'] ){
						$html_paytype .= '
						<option value="80"' . (('80' == $paytype) ? ' selected="selected"' : '') . '>'.__('Bonus lump-sum payment', 'usces').'</option>';
					}
					$html_paytype .= '</select>';
					
					$html_paytype .= '
					</td>
					</tr>
					';
					$html .= apply_filters( 'usces_filter_escott_secure_form_paytype', $html_paytype );
				}
			}


			$html .= '
			</table><table>';
			break;
		}
		return $html;
	}

	/**********************************************
	* usces_filter_check_acting_return_results
	* 決済完了ページ制御
	* @param  $results
	* @return array $results
	***********************************************/
	public function acting_return( $results ){
		if( !in_array( 'acting_'.$results['acting'], $this->pay_method) )
			return $results;

		if( isset($results['acting_return']) && $results['acting_return'] != 1 )
			return $results;

		$results['reg_order'] = false;
		
		usces_log('e-SCOTT Payment results : '.print_r($results, true), 'acting_transaction.log');
		if( !isset( $_REQUEST['nonce'] ) || !wp_verify_nonce($_REQUEST['nonce'], 'escott_transaction') ) {
			wp_redirect( home_url() );
			exit;
		}
		
		return $results;
	}

	/**********************************************
	* e-SCOTT 会員パスワード取得
	* @param  $params
	* @return $response
	***********************************************/
	public function get_quick_pass( $member_id ){
		global $usces;
		
		if( empty($member_id) )
			return false;
			
		$escott_member_passwd = $usces->get_member_meta_value( 'escott_member_passwd', $member_id );
		return $escott_member_passwd;
		
	}
	
	/**********************************************
	* e-SCOTT 会員ID取得
	* @param  $params
	* @return $response
	***********************************************/
	public function get_quick_kaiin_id( $member_id ){
		global $usces;
		
		if( empty($member_id) )
			return false;
			
		$escott_member_passwd = $usces->get_member_meta_value( 'escott_member_id', $member_id );
		return $escott_member_passwd;
		
	}
	
	/**********************************************
	* e-SCOTT 会員パスワード生成
	* @param  $params
	* @return $response
	***********************************************/
	public function make_kaiin_pass(){
		$passwd = sprintf( '%012d', mt_rand() );
		return $passwd;
		
	}
	
	/**********************************************
	* e-SCOTT 会員ID生成
	* @param  $params
	* @return $response
	***********************************************/
	public function make_kaiin_id(){
		$id = sprintf( '%012d', mt_rand() );
		return 'i'.$id;
		
	}
	
	/**********************************************
	* e-SCOTT 管理画面会員情報更新
	* @param  $mem_id, $res
	* @return -
	***********************************************/
	public function admin_update_memberdata( $mem_id, $res ){
		global $usces;

		if( !$this->is_activate_card( 'escott' ) || false === $res )
			return;

		$acting_opts = $usces->options['acting_settings']['escott'];
		$params['send_url'] = $acting_opts['send_url_member'];
		$KaiinPass = $this->get_quick_pass( $mem_id );
		$KaiinId = $this->get_quick_kaiin_id( $mem_id );
		
		if( $KaiinPass ){
			// 共通部 
			$TransactionDate = date('Ymd', current_time('timestamp'));
			$params['param_list']['MerchantId'] = urlencode( $acting_opts['merchant_id'] ); 
			$params['param_list']['MerchantPass'] = urlencode( $acting_opts['merchant_pass'] ); 
			$params['param_list']['TransactionDate'] = urlencode( $TransactionDate ); 
			$params['param_list']['MerchantFree3'] = urlencode( "wc1collne" ); 
			if( !empty($acting_opts['tenant_id']) )
				$params['param_list']['TenantId'] = urlencode( $acting_opts['tenant_id'] ); 

			$params['param_list']['OperateId'] = '4MemInval';
			$params['param_list']['KaiinPass'] = $KaiinPass;
			$params['param_list']['KaiinId'] = $KaiinId;
			$ires = $this->connection( $params );
			if( 'OK' == $ires['ResponseCd'] ){
				$params['param_list']['OperateId'] = '4MemDel';
				$dres = $this->connection( $params );
				if( 'OK' == $dres['ResponseCd'] ){
					$usces->del_member_meta( 'escott_member_passwd', $mem_id );
					$usces->del_member_meta( 'escott_member_id', $mem_id );
				}else{
					usces_log('dres_connection : NG : '.print_r($dres,true), 'acting_transaction.log');
				}
			}else{
				usces_log('ires_connection : NG : '.print_r($ires,true), 'acting_transaction.log');
			}
		}
	}
	/**********************************************
	* e-SCOTT 会員情報更新
	* @param  $post_member, $mem_id
	* @return -
	***********************************************/
	public function edit_memberdata( $post_member, $mem_id ){
		global $usces;

		if( !$this->is_activate_card( 'escott' ) )
			return;

		$acting_opts = $usces->options['acting_settings']['escott'];
		$params['send_url'] = $acting_opts['send_url_member'];
		$KaiinPass = $this->get_quick_pass( $mem_id );
		$KaiinId = $this->get_quick_kaiin_id( $mem_id );
		
		if( $KaiinPass ){
			// 共通部 
			$TransactionDate = date('Ymd', current_time('timestamp'));
			$params['param_list']['MerchantId'] = urlencode( $acting_opts['merchant_id'] ); 
			$params['param_list']['MerchantPass'] = urlencode( $acting_opts['merchant_pass'] ); 
			$params['param_list']['TransactionDate'] = urlencode( $TransactionDate ); 
			$params['param_list']['MerchantFree3'] = urlencode( "wc1collne" ); 
			if( !empty($acting_opts['tenant_id']) )
				$params['param_list']['TenantId'] = urlencode( $acting_opts['tenant_id'] ); 

			$params['param_list']['OperateId'] = '4MemInval';
			$params['param_list']['KaiinPass'] = $KaiinPass;
			$params['param_list']['KaiinId'] = $KaiinId;
			$ires = $this->connection( $params );
			if( 'OK' == $ires['ResponseCd'] ){
				$params['param_list']['OperateId'] = '4MemDel';
				$dres = $this->connection( $params );
				if( 'OK' == $dres['ResponseCd'] ){
					$usces->del_member_meta( 'escott_member_passwd', $mem_id );
					$usces->del_member_meta( 'escott_member_id', $mem_id );
				}else{
					usces_log('dres_connection : NG : '.print_r($dres,true), 'acting_transaction.log');
				}
			}else{
				usces_log('ires_connection : NG : '.print_r($ires,true), 'acting_transaction.log');
			}
		}
	}
	
	/**********************************************
	* e-SCOTT 会員情報登録・更新
	* @param  $params
	* @return $response
	***********************************************/
	public function escott_member_process( $param_list = array() ){
		global $usces;

		$member = $usces->get_member();
		$acting_opts = $usces->options['acting_settings']['escott'];
		$params['send_url'] = $acting_opts['send_url_member'];
		$KaiinPass = $this->get_quick_pass( $member['ID'] );//member_ID
		$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );//member_ID
		if( empty( $KaiinPass ) ){
			$KaiinPass = $this->make_kaiin_pass();
			$KaiinId = $this->make_kaiin_id();
			$params['param_list'] = array_merge($param_list, 
				array(
					'OperateId' => '4MemAdd',
					'KaiinPass' => $KaiinPass,
					'KaiinId' => $KaiinId,
					'CardNo' => trim($_POST['cardnumber']),
					'CardExp' => substr($_POST['expyy'],2) . $_POST['expmm']
				)
			);//escott新規会員登録
			$res = $this->connection( $params );

			if( 'OK' == $res['ResponseCd'] ){
				$usces->set_member_meta_value( 'escott_member_passwd', $KaiinPass, $member['ID'] );
				$usces->set_member_meta_value( 'escott_member_id', $KaiinId, $member['ID'] );
				$res['KaiinPass'] = $KaiinPass;
				$res['KaiinId'] = $KaiinId;
			}
		}else{
			if( isset($_POST['cardnumber']) && '8888888888888888' != $_POST['cardnumber'] ){
				$params['param_list'] = array_merge($param_list, 
					array(
						'OperateId' => '4MemChg',
						'KaiinPass' => $KaiinPass,
						'KaiinId' => $KaiinId,
						'CardNo' => trim($_POST['cardnumber']),
						'CardExp' => substr($_POST['expyy'],2) . $_POST['expmm']
					)
				);//escott会員更新
				$res = $this->connection( $params );

				if( 'OK' == $res['ResponseCd'] ){
					$res['KaiinPass'] = $KaiinPass;
					$res['KaiinId'] = $KaiinId;
				}else{
//					$params['param_list']['OperateId'] = '4MemInval';
//					$params['param_list']['KaiinPass'] = $KaiinPass;
//					$params['param_list']['KaiinId'] = $KaiinId;
//					$ires = $this->connection( $params );
//					if( 'OK' == $ires['ResponseCd'] ){
//						$params['param_list']['OperateId'] = '4MemDel';
//						$dres = $this->connection( $params );
//						if( 'OK' == $dres['ResponseCd'] ){
//							$usces->del_member_meta( 'escott_member_passwd', $member['ID'] );
//							$usces->del_member_meta( 'escott_member_id', $member['ID'] );
//						}
//					}
				}
			}else{
				$res['ResponseCd'] = 'OK';
				$res['KaiinPass'] = $KaiinPass;
				$res['KaiinId'] = $KaiinId;
			}
		}
		return $res;
	}
	
	/**********************************************
	* ソケット通信接続
	* @param  $params
	* @return array $response_data
	***********************************************/
	public function connection( $params ){

		$gc = new SLNConnection(); 
		$gc->set_connection_url( $params['send_url'] ); 
		$gc->set_connection_timeout( 60 ); 
		$response_list = $gc->send_request( $params['param_list'] ); 

		if( !empty($response_list) ){
		
			$resdata = explode("\r\n\r\n", $response_list);
			parse_str($resdata[1], $response_data );

		}else{
			$response_data['ResponseCd'] = 'error';
		}
		return $response_data;
	}
	
	/**********************************************
	* usces_action_acting_processing
	* 決済処理
	* @param  no use
	* @return allways false
	***********************************************/
	//public function purchase( $nouse ){
	public function acting_processing( $acting_flag, $post_query ) {
		global $usces;
		
		if( 'acting_escott_card' != $acting_flag && 'acting_escott_conv' != $acting_flag )
			return;
		
		$usces_entries = $usces->cart->get_entry();
		$cart = $usces->cart->get_cart();

		if( !$usces_entries || !$cart )
			wp_redirect(USCES_CART_URL);
			
		//$payments = usces_get_payments_by_name($usces_entries['order']['payment_name']);
		//$acting_flag = ( 'acting' == $payments['settlement'] ) ? $payments['module'] : $payments['settlement'];

		//if( !$usces_entries['order']['total_full_price'] )
		//	return true;

		if( !wp_verify_nonce( $_REQUEST['_nonce'], $acting_flag ) )
			wp_redirect(USCES_CART_URL);
		

		$TransactionDate = date('Ymd', current_time('timestamp'));
		$rand = $_REQUEST['rand'];
		$member = $usces->get_member();

		//Duplication control
		$this->duplication_control( $acting_flg, $rand );

		//$charging_type = $usces->getItemChargingType($cart[0]['post_id']);
		//$frequency = $usces->getItemFrequency($cart[0]['post_id']);
		//$chargingday = $usces->getItemChargingDay($cart[0]['post_id']);
		
		$item_id = $cart[0]['post_id'];
		$item_name = mb_convert_kana($usces->getItemName($cart[0]['post_id']), 'ASK', 'UTF-8');
		if(1 < count($cart)){
			if(11 < mb_strlen($item_name . __(' etc.', 'usces'), 'UTF-8')){
				$item_name = mb_substr($item_name, 0, 10, 'UTF-8') . __(' etc.', 'usces');
			}
		}else{
			if(11 < mb_strlen($item_name, 'UTF-8')){
				$item_name = mb_substr($item_name, 0, 10, 'UTF-8') . __('...', 'usces');
			}
		}
		
		$acting_opts = $usces->options['acting_settings']['escott'];
		$send_url = $acting_opts['send_url'];

		$param_list = array(); 
		$response_list = array(); 
		// 共通部 
		$param_list['MerchantId'] = urlencode( $acting_opts['merchant_id'] ); 
		$param_list['MerchantPass'] = urlencode( $acting_opts['merchant_pass'] ); 
		$param_list['TransactionDate'] = urlencode( $TransactionDate ); 
		$param_list['MerchantFree1'] = urlencode( $rand ); 
		$param_list['MerchantFree2'] = urlencode( $acting_flag ); 
		$param_list['MerchantFree3'] = urlencode( "wc1collne" ); 
		if( !empty($acting_opts['tenant_id']) )
			$param_list['TenantId'] = urlencode( $acting_opts['tenant_id'] ); 
		$param_list['Amount'] = urlencode( $usces_entries['order']['total_full_price'] ); 

		// 処理部 
		switch( $acting_flag ) {
		
		case 'acting_escott_card':
			$acting = "escott_card";

			if( !empty($member['ID']) && 'on' == $acting_opts['quickpay'] ) {
				
				$mem_response_data = $this->escott_member_process( $param_list );
				if( 'OK' == $mem_response_data['ResponseCd'] ){
					$param_list['KaiinPass'] = urlencode( $mem_response_data['KaiinPass'] );
					$param_list['KaiinId'] = urlencode( $mem_response_data['KaiinId'] );
				}else{
					$response_data['MerchantFree2'] = $mem_response_data['MerchantFree2'];
					$response_data['ResponseCd'] = $mem_response_data['ResponseCd'];
					$response_data['acting'] = $acting;
					$response_data['acting_return'] = 0;
					$response_data['result'] = 0;
					unset( $mem_response_data['CardNo'] );
					$logdata = array_merge($param_list, $mem_response_data);
					$logresult = $mem_response_data['ResponseCd'].':'.$this->response_message( $mem_response_data['ResponseCd'] );
					$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$rand, 'result'=>$logresult, 'data'=>$logdata );
					usces_save_order_acting_error( $log );
					wp_redirect( add_query_arg( $response_data, USCES_CART_URL) );
					exit;
				}
			}else{
				$param_list['CardNo'] = urlencode( trim($_POST['cardnumber']) ); 
				$param_list['CardExp'] = urlencode( substr($_POST['expyy'],2) . $_POST['expmm'] ); 
			}
			$param_list['PayType'] = urlencode( $_POST['paytype'] ); 
			$param_list['SecCd'] = urlencode( trim($_POST['securecode']) ); 
			$param_list['OperateId'] = urlencode( $acting_opts['operateid'] );
	
			$params['send_url'] = $acting_opts['send_url'];
			$params['param_list'] = $param_list;
			$response_data = $this->connection( $params );
			
			$response_data['acting'] = $acting;
			$response_data['PayType'] = $_POST['paytype'];
			$response_data['CardNo'] = substr($_POST['cardnumber'], -4);
			$response_data['CardExp'] = substr($_POST['expyy'],2) . '/' . $_POST['expmm'];

			if( 'OK' == $response_data['ResponseCd'] ){
			
				$res = $usces->order_processing($response_data);
				if( 'ordercompletion' == $res ){
					if( isset($response_data['MerchantFree1']) ){
						usces_ordered_acting_data($response_data['MerchantFree1']);
					}
					$response_data['acting_return'] = 1;
					$response_data['result'] = 1;
					$response_data['nonce'] = wp_create_nonce('escott_transaction');
					wp_redirect( add_query_arg( $response_data, USCES_CART_URL) );
				}else{
					$response_data['acting_return'] = 0;
					$response_data['result'] = 0;
					unset( $response_data['CardNo'] );
					$logdata = array_merge($usces_entries['order'], $response_data);
					$logresult = $response_data['ResponseCd'].':'.$this->response_message( $response_data['ResponseCd'] );
					$log = array( 'acting'=>$acting.'(order_processing)', 'key'=>$rand, 'result'=>$logresult, 'data'=>(array)$logdata );
					usces_save_order_acting_error( $log );
					wp_redirect( add_query_arg( $response_data, USCES_CART_URL) );
				}
			
			}else{
				$response_data['acting_return'] = 0;
				$response_data['result'] = 0;
				unset( $response_data['CardNo'] );
				$logdata = array_merge($params, $response_data);
				$logresult = $response_data['ResponseCd'].':'.$this->response_message( $response_data['ResponseCd'] );
				$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$logresult, 'data'=>(array)$logdata );
				usces_save_order_acting_error( $log );
				wp_redirect( add_query_arg( $response_data, USCES_CART_URL) );
			
			}

			break;
			
		case 'acting_escott_conv':
			$acting = "escott_conv";

			$param_list['OperateId'] = '2Add';
			$param_list['PayLimit'] = urlencode( date( 'Ymd', current_time('timestamp')+(86400*$acting_opts['conv_limit']) ).'2359' ); 
			$param_list['NameKanji'] = urlencode( $usces_entries['customer']['name1'].$usces_entries['customer']['name2'] ); 
			$param_list['NameKana'] = !empty($usces_entries['customer']['name3']) ? urlencode( $usces_entries['customer']['name3'].$usces_entries['customer']['name4'] ) : $param_list['NameKanji']; 
			$param_list['TelNo'] = urlencode( $usces_entries['customer']['tel'] ); 
			$param_list['ShouhinName'] = urlencode( $item_name );
			$param_list['Comment'] = urlencode( __('Thank you for your use.', 'usces') );
			//$return_para = array( 'acting_return'=>1,'result'=>1,'acting'=>'acting_escott_conv','nonce'=>wp_create_nonce('escott_transaction'));
			//$param_list['ReturnURL'] = urlencode(add_query_arg( $return_para, USCES_CART_URL));
			$param_list['ReturnURL'] = urlencode(home_url('/'));

			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = $param_list;
			$response_data = $this->connection( $params );
			$response_data['acting'] = $acting;

			if( 'OK' == $response_data['ResponseCd'] ){
			
				$FreeArea = trim($response_data['FreeArea']);
				$url = add_query_arg( array('code'=>$FreeArea, 'rkbn'=>1), $acting_opts['redirect_url_conv']);
				$res = $usces->order_processing($response_data);
				if( 'ordercompletion' == $res ){
					if( isset($response_data['MerchantFree1']) ){
						usces_ordered_acting_data($response_data['MerchantFree1']);
					}
					$usces->cart->crear_cart();

					header('location: ' . $url);
					exit;
				}else{
					$response_data['acting_return'] = 0;
					$response_data['result'] = 0;
					unset( $response_data['CardNo'] );
					$logdata = array_merge($usces_entries['order'], $response_data);
					$logresult = $response_data['ResponseCd'].':'.$this->response_message( $response_data['ResponseCd'] );
					$log = array( 'acting'=>$acting.'(order_processing)', 'key'=>$rand, 'result'=>$logresult, 'data'=>(array)$logdata );
					usces_save_order_acting_error( $log );
					wp_redirect( add_query_arg( $response_data, USCES_CART_URL) );
				}
				
			}else{
			
				$response_data['acting_return'] = 0;
				$response_data['result'] = 0;
				unset( $response_data['CardNo'] );
				$logdata = array_merge($params, $response_data);
				$logresult = $response_data['ResponseCd'].':'.$this->response_message( $response_data['ResponseCd'] );
				$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$logresult, 'data'=>(array)$logdata );
				usces_save_order_acting_error( $log );
				wp_redirect( add_query_arg( $response_data, USCES_CART_URL) );
			}
			break;
			
		}
		
		exit;
		return false;
	}
	
	/**********************************************
	* usces_filter_confirm_inform
	* 内容確認ページ Purchase Button
	* @param  $html, $payments, $acting_flag, $rand, $purchase_disabled
	* @return form str
	***********************************************/
	public function confirm_inform( $html, $payments, $acting_flag, $rand, $purchase_disabled ){
		global $usces;
		if( 'acting_escott_card' != $acting_flag && 'acting_escott_conv' != $acting_flag )
			return $html;

		$usces_entries = $usces->cart->get_entry();
		
		if( !$usces_entries['order']['total_full_price'] )
			return $html;
		
		
		$acting_opts = $usces->options['acting_settings']['escott'];
		$usces->save_order_acting_data($rand);
		usces_save_order_acting_data( $rand );
		$html = '<form id="purchase_form" action="' . USCES_CART_URL . '" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">';
		$mem_id = '';
		$quick_pass = '';
		if( $usces->is_member_logged_in() ){
			$member = $usces->get_member();
			$mem_id = $member['ID'];
			$quick_pass = $this->get_quick_pass( $mem_id );
		}
		$html .= '<input type="hidden" name="cardnumber" value="' . esc_attr($_POST['cnum1']) . '">';
		$securecode = isset($_POST['securecode']) ? $_POST['securecode'] : '';
		$html .= '<input type="hidden" name="securecode" value="' . esc_attr($securecode) . '">';
		$html .= '<input type="hidden" name="expyy" value="' . esc_attr($_POST['expyy']) . '">
			<input type="hidden" name="expmm" value="' . esc_attr($_POST['expmm']) . '">';
		$html .= '<input type="hidden" name="telno" value="' . esc_attr(str_replace('-', '', $usces_entries['customer']['tel'])) . '">
			<input type="hidden" name="email" value="' . esc_attr($usces_entries['customer']['mailaddress1']) . '">
			<input type="hidden" name="sendid" value="' . $mem_id . '">
			<input type="hidden" name="username" value="' . esc_attr($_POST['username_card']) . '">
			<input type="hidden" name="money" value="' . usces_crform($usces_entries['order']['total_full_price'], false, false, 'return', false) . '">
			<input type="hidden" name="sendpoint" value="' . $rand . '">
			<input type="hidden" name="printord" value="yes">';
		$html .= '<input type="hidden" name="paytype" value="' . esc_attr($_POST['paytype']) . '">';
		$html .= '
			<input type="hidden" name="rand" value="' . $rand . '">
			<input type="hidden" name="cnum1" value="' . esc_attr($_POST['cnum1']) . '">
			<div class="send"><input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__('Back', 'usces').'"' . apply_filters('usces_filter_confirm_prebutton', NULL) . ' />
			<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.__('Checkout', 'usces').'"' . apply_filters('usces_filter_confirm_nextbutton', NULL) . $purchase_disabled . ' /></div>
			<input type="hidden" name="username_card" value="' . esc_attr($_POST['username_card']) . '">
			<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flag ).'">' . "\n";
		return $html;

	}
	
	/**********************************************
	* usces_action_confirm_page_point_inform
	* 内容確認ページ Point form
	***********************************************/
	public function point_inform(){
		global $usces;
		$usces_entries = $usces->cart->get_entry();
		$payments = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		$acting_flag = $payments['settlement'];
		if( 'acting_escott_card' != $acting_flag )
			return;
		
		$securecode = isset($_POST['securecode']) ? $_POST['securecode'] : '';
	?>
		<input type="hidden" name="cardnumber" value="<?php echo $_POST['cnum1']; ?>">
		<input type="hidden" name="securecode" value="<?php echo $securecode; ?>">
		<input type="hidden" name="expyy" value="<?php echo $_POST['expyy']; ?>">
		<input type="hidden" name="expmm" value="<?php echo $_POST['expmm']; ?>">
		<input type="hidden" name="username" value="<?php echo $_POST['username_card']; ?>">
		<input type="hidden" name="cnum1" value="<?php echo $_POST['cnum1']; ?>">
		<input type="hidden" name="username_card" value="<?php echo $_POST['username_card']; ?>">
		<input type="hidden" name="paytype" value="<?php echo $_POST['paytype']; ?>">
	<?php
	}

	/**********************************************
	* usces_filter_confirm_point_inform
	* 内容確認ページ Point form
	* @param  $html
	* @return str
	***********************************************/
	public function point_inform_filter( $html ){
		global $usces;
		$usces_entries = $usces->cart->get_entry();
		$payments = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		$acting_flag = $payments['settlement'];
		if( 'acting_escott_card' != $acting_flag )
			return $html;
		
		$securecode = isset($_POST['securecode']) ? $_POST['securecode'] : '';
		$html .= '<input type="hidden" name="cardnumber" value="' . esc_attr($_POST['cnum1']) . '">
		<input type="hidden" name="securecode" value="' . esc_attr($securecode) . '">
		<input type="hidden" name="expyy" value="' . esc_attr($_POST['expyy']) . '">
		<input type="hidden" name="expmm" value="' . esc_attr($_POST['expmm']) . '">
		<input type="hidden" name="username" value="' . esc_attr($_POST['username_card']) . '">
		<input type="hidden" name="cnum1" value="' . esc_attr($_POST['cnum1']) . '">
		<input type="hidden" name="paytype" value="' . esc_attr($_POST['paytype']) . '">
		<input type="hidden" name="username_card" value="' . esc_attr($_POST['username_card']) . '">';
		return $html;
	}

	/**********************************************
	* Initialize
	* Modified:
	***********************************************/
	public function initialize_data(){
		global $usces;
		
		$options = get_option('usces');
		
		$options['acting_settings']['escott']['merchant_id'] = isset($options['acting_settings']['escott']['merchant_id']) ? $options['acting_settings']['escott']['merchant_id'] : '';
		$options['acting_settings']['escott']['merchant_pass'] = isset($options['acting_settings']['escott']['merchant_pass']) ? $options['acting_settings']['escott']['merchant_pass'] : '';
		$options['acting_settings']['escott']['tenant_id'] = isset($options['acting_settings']['escott']['tenant_id']) ? $options['acting_settings']['escott']['tenant_id'] : '0001';
		$options['acting_settings']['escott']['ope'] = isset($options['acting_settings']['escott']['ope']) ? $options['acting_settings']['escott']['ope'] : 'test';
		$options['acting_settings']['escott']['card_activate'] = isset($options['acting_settings']['escott']['card_activate']) ? $options['acting_settings']['escott']['card_activate'] : 'on';
		$options['acting_settings']['escott']['operateid'] = isset($options['acting_settings']['escott']['operateid']) ? $options['acting_settings']['escott']['operateid'] : '1Auth';
		$options['acting_settings']['escott']['quickpay'] = isset($options['acting_settings']['escott']['quickpay']) ? $options['acting_settings']['escott']['quickpay'] : 'off';
		$options['acting_settings']['escott']['howtopay'] = isset($options['acting_settings']['escott']['howtopay']) ? $options['acting_settings']['escott']['howtopay'] : '1';
		$options['acting_settings']['escott']['conv_activate'] = isset($options['acting_settings']['escott']['conv_activate']) ? $options['acting_settings']['escott']['conv_activate'] : 'off';
		$options['acting_settings']['escott']['conv_limit'] = !empty($options['acting_settings']['escott']['conv_limit']) ? $options['acting_settings']['escott']['conv_limit'] : '7';
		$options['acting_settings']['escott']['payeasy_activate'] = isset($options['acting_settings']['escott']['payeasy_activate']) ? $options['acting_settings']['escott']['payeasy_activate'] : '';
		$options['acting_settings']['escott']['activate'] = isset($options['acting_settings']['escott']['activate']) ? $options['acting_settings']['escott']['activate'] : 'off';
		
		update_option( 'usces', $options );
		self::$opts = $options['acting_settings']['escott'];

		$available_settlement = get_option( 'usces_available_settlement' );
		if( !in_array( 'escott', $available_settlement ) ) {
			$available_settlement['escott'] = 'e-SCOTT Smart';
			update_option( 'usces_available_settlement', $available_settlement );
		}
	}

	/**********************************************
	* usces_action_admin_settlement_update
	* 決済オプション登録・更新
	* @param  -
	* @return -
	***********************************************/
	public function data_update(){
		global $usces;
		
		if( 'escott' != $_POST['acting'])
			return;
	
		$this->error_mes = '';
		$options = get_option('usces');

		unset( $options['acting_settings']['escott'] );
		$options['acting_settings']['escott']['merchant_id'] = isset($_POST['merchant_id']) ? $_POST['merchant_id'] : '';
		$options['acting_settings']['escott']['merchant_pass'] = isset($_POST['merchant_pass']) ? $_POST['merchant_pass'] : '';
		$options['acting_settings']['escott']['tenant_id'] = isset($_POST['tenant_id']) ? $_POST['tenant_id'] : '';
		$options['acting_settings']['escott']['ope'] = isset($_POST['ope']) ? $_POST['ope'] : '';
		$options['acting_settings']['escott']['card_activate'] = isset($_POST['card_activate']) ? $_POST['card_activate'] : '';
		$options['acting_settings']['escott']['operateid'] = isset($_POST['operateid']) ? $_POST['operateid'] : '1Auth';
		$options['acting_settings']['escott']['quickpay'] = isset($_POST['quickpay']) ? $_POST['quickpay'] : '';
		$options['acting_settings']['escott']['howtopay'] = isset($_POST['howtopay']) ? $_POST['howtopay'] : '';
//		$options['acting_settings']['escott']['paytype'] = isset($_POST['paytype']) ? $_POST['paytype'] : '';
//		$options['acting_settings']['escott']['continuation'] = isset($_POST['continuation']) ? $_POST['continuation'] : '';
		$options['acting_settings']['escott']['conv_activate'] = isset($_POST['conv_activate']) ? $_POST['conv_activate'] : '';
		$options['acting_settings']['escott']['conv_limit'] = !empty($_POST['conv_limit']) ? $_POST['conv_limit'] : '7';
		$options['acting_settings']['escott']['payeasy_activate'] = isset($_POST['payeasy_activate']) ? $_POST['payeasy_activate'] : '';


		if( WCUtils::is_blank($_POST['merchant_id']) )
			$this->error_mes .= '※マーチャントIDを入力して下さい<br />';
		if( WCUtils::is_blank($_POST['merchant_pass']) )
			$this->error_mes .= '※マーチャントパスワードを入力して下さい<br />';
		if( WCUtils::is_blank($_POST['tenant_id']) )
			$this->error_mes .= '※店舗コードを入力して下さい<br />';

		if( WCUtils::is_blank($this->error_mes) ){
			$usces->action_status = 'success';
			$usces->action_message = __('options are updated','usces');
			$options['acting_settings']['escott']['activate'] = 'on';
			if( isset($_POST['ope']) && 'public' == $_POST['ope'] ) {
				$options['acting_settings']['escott']['send_url'] = 'https://www.e-scott.jp/online/aut/OAUT002.do';
				$options['acting_settings']['escott']['send_url_member'] = 'https://www.e-scott.jp/online/crp/OCRP005.do';
				$options['acting_settings']['escott']['send_url_conv'] = 'https://www.e-scott.jp/online/cnv/OCNV005.do';
				$options['acting_settings']['escott']['redirect_url_conv'] = 'https://link.kessai.info/JLP/JLPcon';
			}else{
				$options['acting_settings']['escott']['send_url'] = 'https://www.test.e-scott.jp/online/aut/OAUT002.do';
				$options['acting_settings']['escott']['send_url_member'] = 'https://www.test.e-scott.jp/online/crp/OCRP005.do';
				$options['acting_settings']['escott']['send_url_conv'] = 'https://www.test.e-scott.jp/online/cnv/OCNV005.do';
				$options['acting_settings']['escott']['redirect_url_conv'] = 'https://link.kessai.info/JLPCT/JLPcon';
			}
			if( 'on' == $options['acting_settings']['escott']['card_activate'] ){
				$usces->payment_structure['acting_escott_card'] = 'カード決済（e-SCOTT Smart）';
			}else{
				unset($usces->payment_structure['acting_escott_card']);
			}
			if( 'on' == $options['acting_settings']['escott']['conv_activate'] ){
				$usces->payment_structure['acting_escott_conv'] = 'オンライン収納代行（e-SCOTT Smart）';
			}else{
				unset($usces->payment_structure['acting_escott_conv']);
			}
		}else{
			$usces->action_status = 'error';
			$usces->action_message = __('Data have deficiency.','usces');
			$options['acting_settings']['escott']['activate'] = 'off';
			unset($usces->payment_structure['acting_escott_card']);
			unset($usces->payment_structure['acting_escott_conv']);
		}
		ksort($usces->payment_structure);
		update_option('usces', $options);
		update_option('usces_payment_structure', $usces->payment_structure);
		self::$opts = $options['acting_settings']['escott'];
	}

	/**********************************************
	* usces_action_settlement_tab_title
	* クレジット決済設定画面タブ追加
	* @param  -
	* @return -
	* @echo   str
	***********************************************/
	public function tab_title(){
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'escott', (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_escott">e-SCOTT Smart</a></li>';
		}
	}

	/**********************************************
	* usces_action_settlement_tab_body
	* クレジット決済設定画面フォーム追加
	* @param  -
	* @return -
	* @echo   str
	***********************************************/
	public function tab_body(){
		global $usces;
		$opts = $usces->options['acting_settings'];
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'escott', (array)$settlement_selected ) ):
?>
	<div id="uscestabs_escott">
	<div class="settlement_service"><span class="service_title">e-SCOTT Smart　ソニーペイメントサービス</span></div>

	<?php if( isset($_POST['acting']) && 'escott' == $_POST['acting'] ){ ?>
		<?php if( '' != $this->error_mes ){ ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php }else if( isset($opts['escott']['activate']) && 'on' == $opts['escott']['activate'] ){ ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php } ?>
	<?php } ?>
	<form action="" method="post" name="escott_form" id="escott_form">
		<table class="settle_table">
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_merchant_id_escott');">マーチャントID</a></th>
				<td colspan="6"><input name="merchant_id" type="text" id="merchant_id_escott" value="<?php echo esc_html(isset($opts['escott']['merchant_id']) ? $opts['escott']['merchant_id'] : ''); ?>" size="20"  /></td>
				<td><div id="ex_merchant_id_escott" class="explanation"><?php _e('契約時にスマートリンクネットワークから発行されるマーチャントID（半角数字）', 'usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_merchant_pass_escott');">マーチャントパスワード</a></th>
				<td colspan="6"><input name="merchant_pass" type="text" id="merchant_pass_escott" value="<?php echo esc_html(isset($opts['escott']['merchant_pass']) ? $opts['escott']['merchant_pass'] : ''); ?>" size="20"  /></td>
				<td><div id="ex_merchant_pass_escott" class="explanation"><?php _e('契約時にスマートリンクネットワークから発行されるサービスパスワード（半角数字）', 'usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_tenant_id_escott');">店舗コード</a></th>
				<td colspan="6"><input name="tenant_id" type="text" id="tenant_id_escott" value="<?php echo esc_html(isset($opts['escott']['tenant_id']) ? $opts['escott']['tenant_id'] : ''); ?>" size="20"  /></td>
				<td><div id="ex_tenant_id_escott" class="explanation"><?php _e('契約時にスマートリンクネットワークから発行される店舗コード。<br/>契約するショップが1つだけの場合は、0001 と入力してください。', 'usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_ope_escott');"><?php _e('Operation Environment', 'usces'); ?></a></th>
				<td><input name="ope" type="radio" id="ope_escott_2" value="test"<?php if( isset($opts['escott']['ope']) && $opts['escott']['ope'] == 'test' ) echo ' checked="checked"'; ?> /></td><td><label for="ope_escott_2">テスト環境</label></td>
				<td><input name="ope" type="radio" id="ope_escott_3" value="public"<?php if( isset($opts['escott']['ope']) && $opts['escott']['ope'] == 'public' ) echo ' checked="checked"'; ?> /></td><td><label for="ope_escott_3">本番環境</label></td>
				<td><div id="ex_ope_escott" class="explanation"><?php _e('動作環境を切り替えます。', 'usces'); ?></div></td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><input name="card_activate" type="radio" id="card_activate_escott_1" value="on"<?php if( isset($opts['escott']['card_activate']) && $opts['escott']['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="card_activate_escott_1">利用する</label></td>
				<td><input name="card_activate" type="radio" id="card_activate_escott_2" value="off"<?php if( isset($opts['escott']['card_activate']) && $opts['escott']['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="card_activate_escott_2">利用しない</label></td>
				<td></td>
			</tr>
			<tr>
				<th>処理区分</th>
				<td><input name="operateid" type="radio" id="operateid_escott_1" value="1Auth"<?php if( isset($opts['escott']['operateid']) && $opts['escott']['operateid'] == '1Auth' ) echo ' checked="checked"'; ?> /></td><td><label for="operateid_escott_1">与信</label></td>
				<td><input name="operateid" type="radio" id="operateid_escott_2" value="1Gathering"<?php if( isset($opts['escott']['operateid']) && $opts['escott']['operateid'] == '1Gathering' ) echo ' checked="checked"'; ?> /></td><td><label for="operateid_escott_2">与信売上計上</label></td>
				<td></td>
			</tr>
			<tr>
				<th>クイック決済</th>
				<td><input name="quickpay" type="radio" id="quickpay_escott_1" value="on"<?php if( isset($opts['escott']['quickpay']) && $opts['escott']['quickpay'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="quickpay_escott_1">利用する</label></td>
				<td><input name="quickpay" type="radio" id="quickpay_escott_2" value="off"<?php if( isset($opts['escott']['quickpay']) && $opts['escott']['quickpay'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="quickpay_escott_2">利用しない</label></td>
				<td></td>
			</tr>
			<tr>
				<th>支払方法</th>
				<td><input name="howtopay" type="radio" id="howtopay_escott_1" value="1"<?php if( isset($opts['escott']['howtopay']) && $opts['escott']['howtopay'] == '1' ) echo ' checked="checked"'; ?> /></td><td><label for="howtopay_escott_1">一括払いのみ</label></td>
				<td><input name="howtopay" type="radio" id="howtopay_escott_2" value="2"<?php if( isset($opts['escott']['howtopay']) && $opts['escott']['howtopay'] == '2' ) echo ' checked="checked"'; ?> /></td><td><label for="howtopay_escott_2">分割払いを有効にする</label></td>
				<td><input name="howtopay" type="radio" id="howtopay_escott_3" value="3"<?php if( isset($opts['escott']['howtopay']) && $opts['escott']['howtopay'] == '3' ) echo ' checked="checked"'; ?> /></td><td><label for="howtopay_escott_3">分割払いとボーナス払いを有効にする</label></td>
				<td></td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>オンライン収納代行</th>
				<td><input name="conv_activate" type="radio" id="conv_activate_escott_1" value="on"<?php if( isset($opts['escott']['conv_activate']) && $opts['escott']['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="conv_activate_escott_1">利用する</label></td>
				<td><input name="conv_activate" type="radio" id="conv_activate_escott_2" value="off"<?php if( isset($opts['escott']['conv_activate']) && $opts['escott']['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="conv_activate_escott_2">利用しない</label></td>
				<td></td>
			</tr>
			<tr>
				<th>支払制限日数</th>
				<td colspan="3"><input name="conv_limit" type="text" id="conv_limit" value="<?php echo (isset($opts['escott']['conv_limit']) ? $opts['escott']['conv_limit'] : '7'); ?>" />日</td>
				<td></td>
			</tr>
		</table>
		<input name="send_url_test" type="hidden" value="https://www.test.e-scott.jp/online/aut/OAUT002.do" />
		<input name="acting" type="hidden" value="escott" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="e-SCOTT Smartの設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong>e-SCOTT Smart　ソニーペイメントサービス</strong></p>
		<a href="http://www.sonypaymentservices.jp/intro/" target="_blank">e-SCOTT Smartの詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「埋め込み型」の決済システムです。</p>
		<p>「埋め込み型」とは、決済会社のページへは遷移せず、Welcart のページのみで完結する決済システムです。<br />
デザインの統一されたスタイリッシュな決済が可能です。但し、カード番号を扱いますので専用SSLが必須となります。</p>
		<p>カード番号はe-SCOTT Smart のシステムに送信されるだけで、Welcart に記録は残しません。</p>
		<!--<p>「簡易継続課金」を利用するには「DL Seller」拡張プラグインのインストールが必要です。</p>-->
		<p>尚、本番環境では、正規SSL証明書のみでのSSL通信となりますのでご注意ください。</p>
		<p>テスト環境で利用したWelcart会員アカウントは、本番環境で利用できない場合があります。<br/>テスト環境と本番環境で別の会員登録を行うか、テスト環境で利用した会員を一旦削除してから、本番環境で改めて会員登録してください。</p>
	</div>
	</div><!--uscestabs_escott-->
<?php
		endif;
	}

	/**********************************************
	* @param  -
	* @return redirect
	***********************************************/
	public function duplication_control( $acting_flg, $rand ) {
		global $usces, $wpdb;
		$key = 'wc_trans_id';

		$order_meta_table_name = $wpdb->prefix . "usces_order_meta";
		$query = $wpdb->prepare("SELECT order_id FROM $order_meta_table_name 
									WHERE meta_value = %d AND meta_key = %s", $rand, $key);
		$res = $wpdb->get_var($query);
		if( !$res )
			return;

		switch( $acting_flg ) {
		
			case 'acting_escott_card':
				$response_data['acting'] = 'escott_card';
				$response_data['acting_return'] = 1;
				$response_data['result'] = 1;
				$response_data['nonce'] = wp_create_nonce( 'welcart_transaction' );
				wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
				exit;
				break;
				
			case 'acting_escott_conv':
				//wp_redirect( USCES_CART_URL );
				//exit;
				break;
		}
	}

	/**********************************************
	* エラーコード対応メッセージ
	* @param  $code
	* @return str $message
	***********************************************/
	public function response_message( $code ) {

		switch( $code ) {
		case 'K01'://当該 OperateId の設定値を網羅しておりません。（送信項目不足、または項目エラー）設定値をご確認の上、再処理行ってください。
			$message = 'オンライン取引電文精査エラー';
			break;
		case 'K02'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「MerchantId」精査エラー';
			break;
		case 'K03'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「MerchantPass」精査エラー';
			break;
		case 'K04'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「TenantId」精査エラー';
			break;
		case 'K05'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「TransactionDate」精査エラー';
			break;
		case 'K06'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「OperateId」精査エラー';
			break;
		case 'K07'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「MerchantFree1」精査エラー';
			break;
		case 'K08'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「MerchantFree2」精査エラー';
			break;
		case 'K09'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「MerchantFree3」精査エラー';
			break;
		case 'K10'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「ProcessId」精査エラー';
			break;
		case 'K11'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「ProcessPass」精査エラー';
			break;
		case 'K12'://Master 電文で発行された「ProcessId」または「ProcessPass」では無いことを意味します。設定値をご確認の上、再処理行ってください。
			$message = '項目「ProcessId」または「ProcessPass」不整合エラー';
			break;
		case 'K14'://要求された Process 電文の「OperateId」が要求対象外です。例：「1Delete：取消」に対して再度「1Delete：取消」を送信したなど。
			$message = 'OperateId のステータス遷移不整合';
			break;
		case 'K15'://返戻対象となる会員の数が、最大件（30 件）を超えました。
			$message = '会員参照（同一カード番号返戻）時の返戻対象会員数エラー';
			break;
		case 'K20'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「CardNo」精査エラー';
			break;
		case 'K21'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「CardExp」精査エラー';
			break;
		case 'K22'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「PayType」精査エラー';
			break;
		case 'K23'://半角数字ではないことまたは、利用額変更で元取引と金額が同一となっていることを意味します。 8桁以下 (0 以外 )の半角数字であること、利用額変更で元取引と金額が同一でないことをご確認の上、再処理を行ってください。
			$message = '項目「Amount」精査エラー';
			break;
		case 'K24'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「SecCd」精査エラー';
			break;
		case 'K28'://オンライン収納で「半角数字ハイフン≦13桁では無い」設定値を確認の上、再処理を行ってください。
			$message = '項目「TelNo」精査エラー';
			break;
		case 'K39'://YYYMMDD形式では無い、または未来日付あることを意味します。設定値をご確認の上、再処理を行ってください。
			$message = '項目「SalesDate」精査エラー';
			break;
		case 'K45'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「KaiinId」精査エラー';
			break;
		case 'K46'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「KaiinPass」精査エラー';
			break;
		case 'K47'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「NewKaiinPass」精査エラー';
			break;
		case 'K50'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「PayLimit」精査エラー';
			break;
		case 'K51'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「NameKanji」精査エラー';
			break;
		case 'K52'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「NameKana」精査エラー';
			break;
		case 'K53'://形式エラーです。 設定値をご確認の上、再処理を行ってください。
			$message = '項目「ShouhinName」精査エラー';
			break;
		case 'K68'://会員登録機能が未設定となっております。
			$message = '会員の登録機能は利用できません';
			break;
		case 'K69'://この会員ID はすでに使用されています。
			$message = '会員ID の重複エラー';
			break;
		case 'K70'://会員削除電文に対して会員が無効状態ではありません。
			$message = '会員が無効状態ではありません';
			break;
		case 'K71'://会員ID・パスワードが一致しません。
			$message = '会員ID の認証エラー';
			break;
		case 'K73'://会員無効解除電文に対して会員が既に有効となっています。
			$message = '会員が既に有効となっています';
			break;
		case 'K74'://会員認証に連続して失敗し、ロックアウトされました。
			$message = '会員認証に連続して失敗し、ロックアウトされました';
			break;
		case 'K75'://会員は有効でありません。
			$message = '会員は有効でありません';
			break;
		case 'K79'://現在は Login 無効または会員無効状態です。
			$message = '会員判定エラー（Login 無効または会員無効）';
			break;
		case 'K80'://Master 電文は会員ID が設定されています。Process 電文も会員ID を設定してください。
			$message = '会員ID 設定不一致（設定が必要）';
			break;
		case 'K81'://Master 電文は会員 ID が未設定です。Process 電文の会員ID も未設定としてください。
			$message = '会員ID 設定不一致（設定が不要）';
			break;
		case 'K82'://カード番号が適切ではありません。
			$message = 'カード番号の入力内容不正';
			break;
		case 'K83'://カード有効期限が適切ではありません。
			$message = 'カード有効期限の入力内容不正';
			break;
		case 'K84'://会員ID が適切ではありません。
			$message = '会員ID の入力内容不正';
			break;
		case 'K85'://会員パスワードが適切ではありません。
			$message = '会員パスワードの入力内容不正';
			break;
		case 'K88'://取引の対象が複数件存在します。弊社までお問い合わせください。
			$message = '元取引重複エラー';
			break;
		case 'K96'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。
			$message = '本システム通信障害発生（タイムアウト）';
			break;
		case 'K98'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。
			$message = '本システム内部で軽度障害が発生';
			break;
		case 'K99'://弊社までお問い合わせください。
			$message = 'その他例外エラー';
			break;
		case 'KG8'://マーチャントID、マーチャントパスワド認証に連続して失敗し、ロックアウトされました。
			$message = '事業者認証に連続して失敗し、ロックアウトされました';
			break;
		case 'C01'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。
			$message = '弊社設定関連エラー';
			break;
		case 'C02'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。
			$message = 'e-SCOTT システムエラー';
			break;
		case 'C03'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。
			$message = 'e-SCOTT 通信エラー';
			break;
		case 'C10'://ご契約のある支払回数（区分）をセットし再処理行ってください。
			$message = '支払区分エラー';
			break;
		case 'C11'://ボーナス払いご利用対象外期間のため、支払区分を変更して再処理を行ってください。
			$message = 'ボーナス期間外エラー';
			break;
		case 'C12'://ご契約のある分割回数（区分）をセットし再処理行ってください。
			$message = '分割回数エラー';
			break;
		case 'C13'://カード有効期限の年月入力間違え。または、有効期限切れカードです。
			$message = '有効期限切れエラー';
			break;
		case 'C14'://取消処理が既に行われております。管理画面で処理状況をご確認ください。
			$message = '取消済みエラー';
			break;
		case 'C15'://ボーナス払いの下限金額未満によるエラーのため、支払方法を変更して再処理を行ってください。
			$message = 'ボーナス金額下限エラー';
			break;
		case 'C16'://該当のカード会員番号は存在しない。
			$message = 'カード番号エラー';
			break;
		case 'C17'://ご契約範囲外のカード番号。もしくは存在しないカード番号体系。
			$message = 'カード番号体系エラー';
			break;
		case 'C70'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。
			$message = '弊社設定情報エラー';
			break;
		case 'C71'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。
			$message = '弊社設定情報エラー';
			break;
		case 'C80'://カード会社システムの停止を意味します。
			$message = 'カード会社センター閉局';
			break;
		case 'C98'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。
			$message = 'その他例外エラー';
			break;
		case 'G12'://クレジットカードが使用不可能です。
			$message = 'カード使用不可';
			break;
		case 'G22'://支払永久禁止を意味します。
			$message = '"G22" が設定されている';
			break;
		case 'G30'://取引の判断保留を意味します。
			$message = '取引判定保留';
			break;
		case 'G42'://暗証番号が正しくありません。※デビットカードの場合、発生するがあります。
			$message = '暗証番号エラー';
			break;
		case 'G44'://入力されたセキュリティコードが正しくありません。
			$message = 'セキュリティコード誤り';
			break;
		case 'G45'://セキュリティコードが入力されていません。
			$message = 'セキュリティコード入力無';
			break;
		case 'G54'://1日利用回数または金額オーバーです。
			$message = '利用回数エラー';
			break;
		case 'G55'://1日利用限度額オーバーです。
			$message = '限度額オーバー';
			break;
		case 'G56'://クレジットカードが無効です。
			$message = '無効カード';
			break;
		case 'G60'://事故カードが入力されたことを意味します。
			$message = '事故カード';
			break;
		case 'G61'://無効カードが入力されたことを意味します。
			$message = '無効カード';
			break;
		case 'G65'://カード番号の入力が誤っていることを意味します。
			$message = 'カード番号エラー';
			break;
		case 'G68'://金額の入力が誤っていることを意味します。
			$message = '金額エラー';
			break;
		case 'G72'://ボーナス金額の入力が誤っていることを意味します。
			$message = 'ボーナス額エラー';
			break;
		case 'G74'://分割回数の入力が誤っていることを意味します。
			$message = '分割回数エラー';
			break;
		case 'G75'://分割払いの下限金額を回ってること意味します。
			$message = '分割金額エラー';
			break;
		case 'G78'://支払方法の入力が誤っていることを意味します。
			$message = '支払区分エラー';
			break;
		case 'G83'://有効期限の入力が誤っていることを意味します。
			$message = '有効期限エラー';
			break;
		case 'G84'://承認番号の入力が誤っていることを意味します。
			$message = '承認番号エラー';
			break;
		case 'G85'://CAFIS 代行中にエラーが発生したことを意味します。
			$message = 'CAFIS 代行エラー';
			break;
		case 'G92'://カード会社側で任意にエラーとしたい場合に発生します。
			$message = 'カード会社任意エラー';
			break;
		case 'G94'://サイクル通番が規定以上または数字でないことを意味します。
			$message = 'サイクル通番エラー';
			break;
		case 'G95'://カード会社の当該運用業務が終了していることを意味します。
			$message = '当該業務オンライン終了';
			break;
		case 'G96'://取扱不可のクレジットカードが入力されたことを意味します。
			$message = '事故カードデータエラー';
			break;
		case 'G97'://当該要求が拒否され、取扱不能を意味します。
			$message = '当該要求拒否';
			break;
		case 'G98'://接続されたクレジットカード会社の対象業務ではないことを意味します。
			$message = '当該自社対象業務エラー';
			break;
		case 'G99'://接続要求自社受付拒否を意味します。
			$message = '接続要求自社受付拒否';
			break;
		case 'W01'://弊社までお問い合わせください。
			$message = 'オンライン収納代行サービス設定エラー';
			break;
		case 'W02'://弊社までお問い合わせください。
			$message = '設定値エラー';
			break;
		case 'W03'://弊社までお問い合わせください。
			$message = 'オンライン収納代行サービス内部エラー（Web系）';
			break;
		case 'W04'://弊社までお問い合わせください。
			$message = 'システム設定エラー';
			break;
		case 'W05'://送信内容をご確認の上、再処理を行ってください。エラーが解消しない場合は、弊社までお問い合わせください。
			$message = '項目設定エラー';
			break;
		case 'W06'://弊社までお問い合わせください。
			$message = 'オンライン収納代行サービス内部エラー（DB系）';
			break;
		case 'W99'://弊社までお問い合わせください。
			$message = 'その他例外エラー';
			break;
		default:
			$message = $code;
		}
		return $message;
	}

	/**********************************************
	* エラーコード対応メッセージ
	* @param  $code
	* @return str $message
	***********************************************/
	private function error_message( $code ) {

		switch( $code ) {
		case 'K01'://オンライン取引電文精査エラー
		case 'K02'://項目「MerchantId」精査エラー
		case 'K03'://項目「MerchantPass」精査エラー
		case 'K04'://項目「TenantId」精査エラー
		case 'K05'://項目「TransactionDate」精査エラー
		case 'K06'://項目「OperateId」精査エラー
		case 'K07'://項目「MerchantFree1」精査エラー
		case 'K08'://項目「MerchantFree2」精査エラー
		case 'K09'://項目「MerchantFree3」精査エラー
		case 'K10'://項目「ProcessId」精査エラー
		case 'K11'://項目「ProcessPass」精査エラー
		case 'K12'://項目「ProcessId」または「ProcessPass」不整合エラー
		case 'K14'://OperateId のステータス遷移不整合
		case 'K15'://会員参照（同一カード番号返戻）時の返戻対象会員数エラー
		case 'K22'://項目「PayType」精査エラー
		case 'K23'://項目「Amount」精査エラー
		case 'K25':
		case 'K26':
		case 'K27':
		case 'K30':
		case 'K31':
		case 'K32':
		case 'K33':
		case 'K34':
		case 'K35':
		case 'K36':
		case 'K37':
		case 'K39'://項目「SalesDate」精査エラー
		case 'K50'://項目「PayLimit」精査エラー
		case 'K53'://項目「ShouhinName」精査エラー
		case 'K54':
		case 'K55':
		case 'K56':
		case 'K57':
		case 'K58':
		case 'K59':
		case 'K60':
		case 'K61':
		case 'K64':
		case 'K65':
		case 'K66':
		case 'K67':
		case 'K68'://会員の登録機能は利用できません
		case 'K69'://会員ID の重複エラー
		case 'K70'://会員が無効状態ではありません
		case 'K71'://会員ID の認証エラー
		case 'K73'://会員が既に有効となっています
		case 'K74'://会員認証に連続して失敗し、ロックアウトされました
		case 'K75'://会員は有効でありません
		case 'K76':
		case 'K77':
		case 'K78':
		case 'K79'://会員判定エラー（Login 無効または会員無効）
		case 'K80'://会員ID 設定不一致（設定が必要）
		case 'K81'://会員ID 設定不一致（設定が不要）
		case 'K84'://会員ID の入力内容不正
		case 'K85'://会員パスワードの入力内容不正
		case 'K88'://元取引重複エラー
		case 'K95':
		case 'K96'://本システム通信障害発生（タイムアウト）
		case 'K98'://本システム内部で軽度障害が発生
		case 'K99'://その他例外エラー
		case 'KG8'://事業者認証に連続して失敗し、ロックアウトされました
		case 'C01'://弊社設定関連エラー
		case 'C02'://e-SCOTT システムエラー
		case 'C03'://e-SCOTT 通信エラー
		case 'C10'://支払区分エラー
		case 'C11'://ボーナス期間外エラー
		case 'C12'://分割回数エラー
		case 'C14'://取消済みエラー
		case 'C70'://弊社設定情報エラー
		case 'C71'://弊社設定情報エラー
		case 'C80'://カード会社センター閉局
		case 'C98'://その他例外エラー
		case 'G74'://分割回数エラー
		case 'G78'://支払区分エラー
		case 'G85'://CAFIS 代行エラー
		case 'G92'://カード会社任意エラー
		case 'G94'://サイクル通番エラー
		case 'G95'://当該業務オンライン終了
		case 'G98'://当該自社対象業務エラー
		case 'G99'://接続要求自社受付拒否
		case 'W01'://オンライン収納代行サービス設定エラー
		case 'W02'://設定値エラー
		case 'W03'://オンライン収納代行サービス内部エラー（Web系）
		case 'W04'://システム設定エラー
		case 'W05'://項目設定エラー
		case 'W06'://オンライン収納代行サービス内部エラー（DB系）
		case 'W99'://その他例外エラー
			$message = __('Sorry, please contact the administrator from the inquiry form.','usces');//恐れ入りますが、お問い合わせフォームより管理者にお問い合わせください。
			break;
		case 'K20'://項目「CardNo」精査エラー
		case 'K82'://カード番号の入力内容不正
		case 'C16'://カード番号エラー
		case 'C17'://カード番号体系エラー
		case 'G65'://カード番号エラー
			$message = __('Credit card number is not appropriate.','usces');//指定のカード番号が適切ではありません。
			break;
		case 'K21'://項目「CardExp」精査エラー
		case 'K83'://カード有効期限の入力内容不正
		case 'C13'://有効期限切れエラー
		case 'G83'://有効期限エラー
			$message = __('Card expiration date is not appropriate.','usces');//カード有効期限が適切ではありません。
			break;
		case 'K24'://項目「SecCd」精査エラー
		case 'G44'://セキュリティコード誤り
		case 'G45'://セキュリティコード入力無
			$message = __('Security code is not appropriate.','usces');//セキュリティコードが適切ではありません。
			break;
		case 'K40':
		case 'K41':
		case 'K42':
		case 'K43':
		case 'K44':
		case 'K45'://項目「KaiinId」精査エラー
		case 'K46'://項目「KaiinPass」精査エラー
		case 'K47'://項目「NewKaiinPass」精査エラー
		case 'K48':
		case 'KE0':
		case 'KE1':
		case 'KE2':
		case 'KE3':
		case 'KE4':
		case 'KE5':
		case 'KEA':
		case 'KEB':
		case 'KEC':
		case 'KED':
		case 'KEE':
		case 'KEF':
		case 'G42'://暗証番号エラー
		case 'G84'://承認番号エラー
			$message = __('Credit card information is not appropriate.','usces');//カード情報が適切ではありません。
			break;
		case 'C15'://ボーナス金額下限エラー
			$message = __('Please change the payment method and error due to less than the minimum amount of bonus payment.','usces');//ボーナス払いの下限金額未満によるエラーのため、支払方法を変更して再処理を行ってください。
			break;
		case 'G12'://カード使用不可
		case 'G22'://"G22" が設定されている
		case 'G30'://取引判定保留
		case 'G56'://無効カード
		case 'G60'://事故カード
		case 'G61'://無効カード
		case 'G96'://事故カードデータエラー
		case 'G97'://当該要求拒否
			$message = __('Credit card is unusable.','usces');//クレジットカードが使用不可能です。
			break;
		case 'G54'://利用回数エラー
			$message = __('It is over 1 day usage or over amount.','usces');//1日利用回数または金額オーバーです。
			break;
		case 'G55'://限度額オーバー
			$message = __('It is over limit for 1 day use.','usces');//1日利用限度額オーバーです。
			break;
		case 'G68'://金額エラー
		case 'G72'://ボーナス額エラー
			$message = __('Amount is not appropriate.','usces');//Amount is not appropriate.
			break;
		case 'G75'://分割金額エラー
			$message = __('It is lower than the lower limit of installment payment.','usces');//分割払いの下限金額を下回っています。
			break;
		case 'K28':
			$message = __('Customer telephone number is not appropriate.','usces');//お客様電話番号が適切ではありません。
			break;
		case 'K51'://項目「NameKanji」精査エラー
			$message = __('Customer name is not entered properly.','usces');//お客様氏名が適切に入力されていません。
			break;
		case 'K52'://項目「NameKana」精査エラー
			$message = __('Customer kana name is not entered properly.','usces');//お客様氏名カナが適切に入力されていません。
			break;
		default:
			$message = __('Sorry, please contact the administrator from the inquiry form.','usces');//恐れ入りますが、お問い合わせフォームより管理者にお問い合わせください。
		}
		return $message;
	}
	
	function is_validity_escot( $type='' ){
		global $usces;
		
		if( !isset($usces->options['acting_settings']['escott']) )
			return false;
			
		$payments = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method = false;
			
		$acting_opts = $usces->options['acting_settings']['escott'];
		switch( $type ){
			case 'cart':
				foreach( $payments as $payment ){
					if( 'acting_escott_card' == $payment['settlement'] && 'activate' == $payment['use'] ){
						$method = true;
						break;
					}
				}
				if( $method && 'on' == $acting_opts['activate'] && 'on' == $acting_opts['card_activate'] ){
					return true;
				}else{
					return false;
				}
				break;

			case 'conv':
				foreach( $payments as $payment ){
					if( 'acting_escott_conv' == $payment['settlement'] && 'activate' == $payment['use'] ){
						$method = true;
						break;
					}
				}
				if( $method && 'on' == $acting_opts['activate'] && 'on' == $acting_opts['conv_activate'] ){
					return true;
				}else{
					return false;
				}
				break;
			
			default:
			if( 'on' == $acting_opts['activate'] ){
				return true;
			}else{
				return false;
			}
		}
	}

	function delete_member_check( $del, $member_id ) {
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		if( !empty($KaiinId) ) {
			$del = false;
		}
		return $del;
	}
}

/**************************************************************************************/
//クラス定義 : SLNConnection 

class SLNConnection 
{ 
	//  プロパティ定義 
	// 接続先URLアドレス 
	private $connection_url; 

	// 通信タイムアウト 
	private $connection_timeout; 

	// メソッド定義 
	// コンストラクタ 
	// 引数： なし 
	// 戻り値： なし 
	function __construct() 
	{ 
		// プロパティ初期化 
		$this->connection_url = ""; 
		$this->connection_timeout = 600; 
	} 

	// 接続先URLアドレスの設定 
	// 引数： 接続先URLアドレス 
	// 戻り値： なし 
	function set_connection_url( $connection_url = "" ) 
	{ 
		$this->connection_url = $connection_url; 
	} 

	// 接続先URLアドレスの取得 
	// 引数： なし 
	// 戻り値： 接続先URLアドレス 
	function get_connection_url() 
	{ 
		return $this->connection_url; 
	} 

	// 通信タイムアウト時間（s）の設定 
	// 引数： 通信タイムアウト時間（s） 
	// 戻り値： なし 
	function set_connection_timeout( $connection_timeout = 0 ) 
	{ 
		$this->connection_timeout = $connection_timeout; 
	} 

	// 通信タイムアウト時間（s）の取得 
	// 引数： なし 
	// 戻り値： 通信タイムアウト時間（s） 
	function get_connection_timeout() 
	{ 
		return $this->connection_timeout; 
	} 

	// リクエスト送信クラス 
	// 引数： リクエストパラメータ（要求電文）配列 
	// 戻り値： レスポンスパラメータ（応答電文）配列 
	function send_request( &$param_list = array() ) 
	{ 
		$rValue = array(); 
		// パラメータチェック 
		if( empty($param_list) === false ){ 
			// 送信先情報の準備 
			$url = parse_url( $this->connection_url ); 

			// HTTPデータ生成 
			$http_data = ""; 
			reset( $param_list ); 
			while( list($key,$value) = each($param_list) ){ 
				$http_data .= (($http_data!=="") ? "&" : "") . $key . "=" . $value; 
			} 

			// HTTPヘッダ生成 
			$http_header = "POST " . $url['path'] . " HTTP/1.1" . "\r\n" .  
			"Host: " . $url['host'] . "\r\n" .  
			"User-Agent:SLN_PAYMENT_CLIENT_PG_PHP_VERSION_1_0" . "\r\n" .  
			"Content-Type:application/x-www-form-urlencoded" . "\r\n" .  
			"Content-Length:" . strlen($http_data) . "\r\n" .  
			"Connection: close";

			// POSTデータ生成 
			$http_post = $http_header . "\r\n\r\n" . $http_data; 

			// 送信処理 
			$errno = 0; 
			$errstr = ""; 
			$hm = array(); 
			$context = stream_context_create( array(
											'ssl' => array( 'capture_session_meta' => true )
										)
								);

			// ソケット通信接続 
			$fp = @stream_socket_client( 'tlsv1.2://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );

//			$meta = stream_context_get_options($context);
//			usces_log('e-SCOTT meta1 : '.print_r($meta, true), 'acting_transaction.log');

			if( $fp === false ){ 
				usces_log('e-SCOTT send error : '."TLS1.2接続に失敗しました", 'acting_transaction.log');
				$fp = @stream_socket_client( 'ssl://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context ); 

//				$meta = stream_context_get_options($context);
//				usces_log('e-SCOTT meta2 : '.print_r($meta, true), 'acting_transaction.log');

				if( $fp === false ){ 
					usces_log('e-SCOTT send error : '."SSL接続に失敗しました", 'acting_transaction.log');
					return $rValue; 
				} 
			}
			
			if( $fp !== false ){ 

				// 接続後タイムアウト設定 
				$result = socket_set_timeout( $fp, $this->connection_timeout ); 
				if( $result === true ){ 
					// データ送信 
					fwrite( $fp, $http_post ); 
					// 応答受信 
					$response_data = ""; 
					while( !feof($fp) ){ 
						$response_data .= fgets( $fp, 4096 ); 
					} 

					// ソケット通信情報を取得 
					$hm = stream_get_meta_data( $fp ); 
					// ソケット通信切断 
					$result = fclose( $fp ); 
					if( $result === true ){ 
						if( $hm['timed_out'] !== true ){ 
							// レスポンスデータ生成 
							$rValue = $response_data ; 
						}else{ 
							// エラー： タイムアウト発生 
							usces_log('e-SCOTT send error : '."通信中にタイムアウトが発生しました", 'acting_transaction.log');
						} 
					}else{ 
						// エラー： ソケット通信切断失敗 
						usces_log('e-SCOTT send error : '."SLNとの切断に失敗しました", 'acting_transaction.log');
					} 
				}else{ 
					// エラー： タイムアウト設定失敗 
					usces_log('e-SCOTT send error : '."タイムアウト設定に失敗しました", 'acting_transaction.log');
				} 
			}
		}else{ 
			// エラー： パラメータ不整合 
			usces_log('e-SCOTT send error : '."リクエストパラメータの指定が正しくありません", 'acting_transaction.log');
		} 
		return $rValue; 
	}
	
} 

