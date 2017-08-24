<?php
/*
WelcartPay based on e-SCOTT
Version: 1.0.0
Author: Collne Inc.
*/

class WELCARTPAY_SETTLEMENT
{
	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	private $pay_method, $unavailable_method, $paymod_id, $merchantfree3;
	private $continuation_charging_mail;

	public function __construct() {

		$this->pay_method = array(
			'acting_welcart_card',
			'acting_welcart_conv',
			//'acting_welcart_atodene'
		);
		$this->unavailable_method = array(
			'acting_zeus_card',
			'acting_zeus_conv',
			'acting_escott_card',
			'acting_escott_conv'
		);
		$this->paymod_id = 'welcart';
		$this->merchantfree3 = 'wc2collne';
		$this->key_aes = 'HgmhZ94rN799CD3F';
		$this->key_iv = 'gNqc4zwhNLCSC5cv';

		$this->initialize_data();

		if( is_admin() ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
			if( defined('WCEX_AUTO_DELIVERY') ) {
				add_filter( 'wcad_filter_admin_notices', array( $this, 'admin_notices_autodelivery' ) );
			}
		}

		if( $this->is_activate_card() || $this->is_activate_conv() ) {
			add_action( 'usces_after_cart_instant', array( $this, 'acting_transaction' ), 9 );
			add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment' ), 10, 5 );
			add_filter( 'usces_filter_is_complete_settlement', array( $this, 'is_complete_settlement' ), 10, 3 );
			add_action( 'usces_action_revival_order_data', array( $this, 'revival_order_metadata' ), 10, 3 );
			//add_filter( 'usces_filter_get_link_key', array( $this, 'get_link_key' ), 10, 2 );

			if( is_admin() ) {
				add_action( 'usces_action_admin_ajax', array( $this, 'admin_ajax' ) );
				add_filter( 'usces_filter_orderlist_detail_value', array( $this, 'orderlist_settlement_status' ), 10, 4 );
				add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ) );
				add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
				add_action( 'usces_action_order_edit_form_status_block_middle', array( $this, 'settlement_status' ), 10, 3 );
				add_action( 'usces_action_order_edit_form_settle_info', array( $this, 'settlement_information' ), 10, 2 );
				add_action( 'usces_action_endof_order_edit_form', array( $this, 'settlement_dialog' ), 10, 2 );
				add_action( 'usces_action_admin_member_info', array( $this, 'admin_member_info' ), 10, 3 );
				add_action( 'usces_action_post_update_memberdata', array( $this, 'admin_update_memberdata' ), 10, 2 );

			} else {
				add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ), 9 );
				add_filter( 'usces_filter_delivery_secure_form_loop', array( $this, 'delivery_secure_form' ), 10, 2 );
				add_filter( 'usces_filter_delivery_check', array( $this, 'delivery_check' ), 15 );
				add_filter( 'usces_filter_payment_detail', array( $this, 'payment_detail' ), 10, 2 );
				add_filter( 'usces_filter_payments_str', array( $this, 'payments_str' ), 10, 2 );
				add_filter( 'usces_filter_payments_arr', array( $this, 'payments_arr' ), 10, 2 );
				add_filter( 'usces_filter_confirm_inform', array( $this, 'confirm_inform' ), 10, 5 );
				add_action( 'usces_action_confirm_page_point_inform', array( $this, 'e_point_inform' ), 10, 5 );
				add_filter( 'usces_filter_confirm_point_inform', array( $this, 'point_inform' ), 10, 5 );
				if( defined('WCEX_COUPON') ) {
					add_filter( 'wccp_filter_coupon_inform', array( $this, 'point_inform' ), 10, 5 );
				}
				add_action( 'usces_action_acting_processing', array( $this, 'acting_processing' ), 10, 2 );
				add_filter( 'usces_filter_check_acting_return_results', array( $this, 'acting_return' ) );
				add_filter( 'usces_filter_check_acting_return_duplicate', array( $this, 'check_acting_return_duplicate' ), 10, 2 );
				add_action( 'usces_action_reg_orderdata', array( $this, 'register_order_metadata' ) );
				add_filter( 'usces_filter_get_error_settlement', array( $this, 'error_page_message' ) );
				add_filter( 'usces_filter_send_order_mail_payment', array( $this, 'order_mail_payment' ), 10, 6 );
			}
		}

		if( $this->is_validity_welcart('card') ) {
			add_filter( 'usces_fiter_the_payment_method_explanation', array( $this, 'set_payment_method_explanation' ), 10, 3 );
			add_filter( 'usces_filter_available_payment_method', array( $this, 'set_available_payment_method' ) );
			add_filter( 'usces_filter_delivery_secure_form_howpay', array( $this, 'delivery_secure_form_howpay' ) );
			add_filter( 'usces_filter_template_redirect', array( $this, 'member_update_settlement' ), 1 );
			add_action( 'usces_action_member_submenu_list', array( $this, 'e_update_settlement' ) );
			add_filter( 'usces_filter_member_submenu_list', array( $this, 'update_settlement' ), 10, 2 );
			add_filter( 'usces_filter_delete_member_check', array( $this, 'delete_member_check' ), 10, 2 );

			//*** WCEX DL Seller ***
			if( defined('WCEX_DLSELLER') ) {
				if( defined('WCEX_DLSELLER_VERSION') and version_compare( WCEX_DLSELLER_VERSION, '2.2-beta', '<=' ) ) {
					add_filter( 'usces_filter_the_continue_payment_method', array( $this, 'continuation_payment_method' ) );
				}
				add_filter( 'dlseller_filter_first_charging', array( $this, 'first_charging_date' ), 9, 5 );
				add_filter( 'dlseller_filter_the_payment_method_restriction', array( $this, 'payment_method_restriction' ), 10, 2 );
				add_filter( 'dlseller_filter_continue_member_list_limitofcard', array( $this, 'continue_member_list_limitofcard' ), 10, 4 );
				//add_filter( 'dlseller_filter_continue_member_list_continue_status', array( $this, 'continue_member_list_continue_status' ), 10, 4 );
				add_filter( 'dlseller_filter_continue_member_list_condition', array( $this, 'continue_member_list_condition' ), 10, 4 );
				add_action( 'dlseller_action_continue_member_list_page', array( $this, 'continue_member_list_page' ) );
				add_filter( 'dlseller_filter_card_update_mail', array( $this, 'continue_member_card_update_mail' ), 10, 3 );
				add_action( 'dlseller_action_do_continuation_charging', array( $this, 'auto_continuation_charging' ), 10, 4 );
				add_action( 'dlseller_action_do_continuation', array( $this, 'do_auto_continuation' ), 10, 2 );
			}

			//*** WCEX Auto Delivery ***
			if( defined('WCEX_AUTO_DELIVERY') ) {
				add_filter( 'wcad_filter_shippinglist_acting', array( $this, 'set_shippinglist_acting' ) );
				add_filter( 'wcad_filter_available_regular_payment_method', array( $this, 'available_regular_payment_method' ) );
				add_filter( 'wcad_filter_the_payment_method_restriction', array( $this, 'payment_method_restriction' ), 10, 2 );
				add_action( 'wcad_action_reg_auto_orderdata', array( $this, 'register_auto_orderdata' ) );
			}
		}

		if( $this->is_validity_welcart('conv') || $this->is_validity_welcart('atodene') ) {
			add_filter( 'usces_filter_cod_label', array( $this, 'set_fee_label' ) );
			add_filter( 'usces_filter_member_history_cod_label', array( $this, 'set_member_history_fee_label' ), 10, 2 );
			if( is_admin() ) {
			} else {
				add_filter( 'usces_fiter_the_payment_method', array( $this, 'payment_method' ) );
				add_filter( 'usces_filter_set_cart_fees_cod', array( $this, 'add_fee' ), 10, 7 );
				add_filter( 'usces_filter_delivery_check', array( $this, 'check_fee_limit' ) );
				add_filter( 'usces_filter_point_check_last', array( $this, 'check_fee_limit' ) );
			}
		}

		if( $this->is_validity_welcart('atodene') ) {
			if( is_admin() ) {
				add_action( 'usces_after_cart_instant', array( $this, 'atodene_upload' ), 9 );
				add_action( 'usces_action_order_list_page', array( $this, 'output_atodene_csv' ) );
				add_action( 'usces_action_order_list_searchbox_bottom', array( $this, 'action_atodene_button' ) );
				add_action( 'usces_action_order_list_footer', array( $this, 'order_list_footer' ) );
				add_filter( 'usces_filter_order_list_page_js', array( $this, 'order_list_page_js' ) );
				add_filter( 'usces_order_list_action_status', array( $this, 'order_list_action_status' ) );
				add_filter( 'usces_order_list_action_message', array( $this, 'order_list_action_message' ) );

				$acting_opts = $this->get_acting_settings();
				if( isset($acting_opts['atodene_byitem']) && 'on' == $acting_opts['atodene_byitem'] ) {
					add_filter( 'usces_item_master_second_section', array( $this, 'edit_item_atodene_byitem' ), 10, 2 );
					add_action( 'usces_action_save_product', array( $this, 'save_item_atodene_byitem' ), 10, 2 );
				}
			} else {
				add_filter( 'usces_filter_nonacting_settlements', array( $this, 'nonacting_settlements' ) );
			}

			//*** WCEX Auto Delivery ***
			if( defined('WCEX_AUTO_DELIVERY') ) {
				add_filter( 'wcad_filter_the_payment_method_restriction', array( $this, 'payment_method_restriction_atodene' ), 11, 2 );
			}
		}
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**********************************************
	* Initialize
	***********************************************/
	public function initialize_data() {

		$options = get_option( 'usces' );
		if( !in_array( 'welcart', $options['acting_settings'] ) ) {
			$options['acting_settings']['welcart']['merchant_id'] = ( isset($options['acting_settings']['welcart']['merchant_id']) ) ? $options['acting_settings']['welcart']['merchant_id'] : '';
			$options['acting_settings']['welcart']['merchant_pass'] = ( isset($options['acting_settings']['welcart']['merchant_pass']) ) ? $options['acting_settings']['welcart']['merchant_pass'] : '';
			$options['acting_settings']['welcart']['tenant_id'] = ( isset($options['acting_settings']['welcart']['tenant_id']) ) ? $options['acting_settings']['welcart']['tenant_id'] : '0001';
			$options['acting_settings']['welcart']['auth_key'] = ( isset($options['acting_settings']['welcart']['auth_key']) ) ? $options['acting_settings']['welcart']['auth_key'] : '';
			$options['acting_settings']['welcart']['ope'] = ( isset($options['acting_settings']['welcart']['ope']) ) ? $options['acting_settings']['welcart']['ope'] : 'test';
			$options['acting_settings']['welcart']['card_activate'] = ( isset($options['acting_settings']['welcart']['card_activate']) ) ? $options['acting_settings']['welcart']['card_activate'] : 'off';
			$options['acting_settings']['welcart']['foreign_activate'] = ( isset($options['acting_settings']['welcart']['foreign_activate']) ) ? $options['acting_settings']['welcart']['foreign_activate'] : 'off';
			$options['acting_settings']['welcart']['seccd'] = ( isset($options['acting_settings']['welcart']['seccd']) ) ? $options['acting_settings']['welcart']['seccd'] : 'on';
			$options['acting_settings']['welcart']['quickpay'] = ( isset($options['acting_settings']['welcart']['quickpay']) ) ? $options['acting_settings']['welcart']['quickpay'] : 'off';
			$options['acting_settings']['welcart']['operateid'] = ( isset($options['acting_settings']['welcart']['operateid']) ) ? $options['acting_settings']['welcart']['operateid'] : '1Auth';
			$options['acting_settings']['welcart']['operateid_dlseller'] = ( isset($options['acting_settings']['welcart']['operateid_dlseller']) ) ? $options['acting_settings']['welcart']['operateid_dlseller'] : '1Gathering';
			$options['acting_settings']['welcart']['auto_settlement_mail'] = ( isset($options['acting_settings']['welcart']['auto_settlement_mail']) ) ? $options['acting_settings']['welcart']['auto_settlement_mail'] : 'on';
			$options['acting_settings']['welcart']['howtopay'] = ( isset($options['acting_settings']['welcart']['howtopay']) ) ? $options['acting_settings']['welcart']['howtopay'] : '1';
			$options['acting_settings']['welcart']['conv_activate'] = ( isset($options['acting_settings']['welcart']['conv_activate']) ) ? $options['acting_settings']['welcart']['conv_activate'] : 'off';
			$options['acting_settings']['welcart']['conv_limit'] = ( !empty($options['acting_settings']['welcart']['conv_limit']) ) ? $options['acting_settings']['welcart']['conv_limit'] : '7';
			$options['acting_settings']['welcart']['conv_fee_type'] = ( isset($options['acting_settings']['welcart']['conv_fee_type']) ) ? $options['acting_settings']['welcart']['conv_fee_type'] : '';
			$options['acting_settings']['welcart']['conv_fee'] = ( isset($options['acting_settings']['welcart']['conv_fee']) ) ? $options['acting_settings']['welcart']['conv_fee'] : '';
			$options['acting_settings']['welcart']['conv_fee_limit_amount'] = ( isset($options['acting_settings']['welcart']['conv_fee_limit_amount']) ) ? $options['acting_settings']['welcart']['conv_fee_limit_amount'] : '';
			$options['acting_settings']['welcart']['conv_fee_first_amount'] = ( isset($options['acting_settings']['welcart']['conv_fee_first_amount']) ) ? $options['acting_settings']['welcart']['conv_fee_first_amount'] : '';
			$options['acting_settings']['welcart']['conv_fee_first_fee'] = ( isset($options['acting_settings']['welcart']['conv_fee_first_fee']) ) ? $options['acting_settings']['welcart']['conv_fee_first_fee'] : '';
			$options['acting_settings']['welcart']['conv_fee_amounts'] = ( isset($options['acting_settings']['welcart']['conv_fee_amounts']) ) ? $options['acting_settings']['welcart']['conv_fee_amounts'] : array();
			$options['acting_settings']['welcart']['conv_fee_fees'] = ( isset($options['acting_settings']['welcart']['conv_fee_fees']) ) ? $options['acting_settings']['welcart']['conv_fee_fees'] : array();
			$options['acting_settings']['welcart']['conv_fee_end_fee'] = ( isset($options['acting_settings']['welcart']['conv_fee_end_fee']) ) ? $options['acting_settings']['welcart']['conv_fee_end_fee'] : '';
			$options['acting_settings']['welcart']['atodene_activate'] = ( isset($options['acting_settings']['welcart']['atodene_activate']) ) ? $options['acting_settings']['welcart']['atodene_activate'] : 'off';
			$options['acting_settings']['welcart']['atodene_byitem'] = ( isset($options['acting_settings']['welcart']['atodene_byitem']) ) ? $options['acting_settings']['welcart']['atodene_byitem'] : 'off';
			$options['acting_settings']['welcart']['atodene_billing_method'] = ( isset($options['acting_settings']['welcart']['atodene_billing_method']) ) ? $options['acting_settings']['welcart']['atodene_billing_method'] : '2';
			$options['acting_settings']['welcart']['atodene_fee_type'] = ( isset($options['acting_settings']['welcart']['atodene_fee_type']) ) ? $options['acting_settings']['welcart']['atodene_fee_type'] : '';
			$options['acting_settings']['welcart']['atodene_fee'] = ( isset($options['acting_settings']['welcart']['atodene_fee']) ) ? $options['acting_settings']['welcart']['atodene_fee'] : '';
			$options['acting_settings']['welcart']['atodene_fee_limit_amount'] = ( isset($options['acting_settings']['welcart']['atodene_fee_limit_amount']) ) ? $options['acting_settings']['welcart']['atodene_fee_limit_amount'] : '';
			$options['acting_settings']['welcart']['atodene_fee_first_amount'] = ( isset($options['acting_settings']['welcart']['atodene_fee_first_amount']) ) ? $options['acting_settings']['welcart']['atodene_fee_first_amount'] : '';
			$options['acting_settings']['welcart']['atodene_fee_first_fee'] = ( isset($options['acting_settings']['welcart']['atodene_fee_first_fee']) ) ? $options['acting_settings']['welcart']['atodene_fee_first_fee'] : '';
			$options['acting_settings']['welcart']['atodene_fee_amounts'] = ( isset($options['acting_settings']['welcart']['atodene_fee_amounts']) ) ? $options['acting_settings']['welcart']['atodene_fee_amounts'] : array();
			$options['acting_settings']['welcart']['atodene_fee_fees'] = ( isset($options['acting_settings']['welcart']['atodene_fee_fees']) ) ? $options['acting_settings']['welcart']['atodene_fee_fees'] : array();
			$options['acting_settings']['welcart']['atodene_fee_end_fee'] = ( isset($options['acting_settings']['welcart']['atodene_fee_end_fee']) ) ? $options['acting_settings']['welcart']['atodene_fee_end_fee'] : '';
			$options['acting_settings']['welcart']['activate'] = ( isset($options['acting_settings']['welcart']['activate']) ) ? $options['acting_settings']['welcart']['activate'] : 'off';
			update_option( 'usces', $options );
		}

		$welcartpay_keys = get_option( 'usces_welcartpay_keys' );
		if( empty( $welcartpay_keys ) ) {
			$welcartpay_keys = array(
				'c0778c9aefe850d5ac8efed5d62ed281',
				'd0771e4b42ef683223df03f9558c23fd',
				'dfef8e46f7231e7e8271f906582a4e1d',
				'ad6dbb5e26cc9db1fe5d876a75764559',
				'4fc1738fffa5aa33792ddf8e5c183f72',
				'd255b1cb2c4d20959e3c80e457e5274c',
				'479ffcfe47db920e972a8c7932e581d9',
				'43c7f4782379b05cf69bbbfb547e3312',
				'524047b0e0ad64d4f7b42c14c77758e2',
				'b848aed9c05cbf2c85d2889b274c18ec'
			);
			update_option( 'usces_welcartpay_keys', $welcartpay_keys );
		}

		$available_settlement = get_option( 'usces_available_settlement' );
		if( !in_array( 'welcart', $available_settlement ) ) {
			$settlement = array( 'welcart'=>__('WelcartPay','usces') );
			$available_settlement = array_merge( $settlement, $available_settlement );
			update_option( 'usces_available_settlement', $available_settlement );
		}

		$noreceipt_status = get_option( 'usces_noreceipt_status' );
		if( !in_array( 'acting_welcart_conv', $noreceipt_status ) || !in_array( 'acting_welcart_atodene', $noreceipt_status ) ) {
			$noreceipt_status[] = 'acting_welcart_conv';
			$noreceipt_status[] = 'acting_welcart_atodene';
			update_option( 'usces_noreceipt_status', $noreceipt_status );
		}
	}

	/**********************************************
	* 決済有効判定
	* 引数が指定されたとき、支払方法で使用している場合に「有効」とする
	* @param  ($type)
	* @return boorean
	***********************************************/
	function is_validity_welcart( $type = '' ) {

		$acting_opts = $this->get_acting_settings();
		if( empty($acting_opts) ) {
			return false;
		}

		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method = false;

		switch( $type ) {
		case 'card':
			foreach( $payment_method as $payment ) {
				if( 'acting_welcart_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_card() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'conv':
			foreach( $payment_method as $payment ) {
				if( 'acting_welcart_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_conv() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'atodene':
			foreach( $payment_method as $payment ) {
				if( 'acting_welcart_atodene' == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_atodene() ) {
				return true;
			} else {
				return false;
			}
			break;

		default:
			if( 'on' == $acting_opts['activate'] ) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**********************************************
	* カード決済有効判定
	* @param  -
	* @return boolean $res
	***********************************************/
	public function is_activate_card() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset($acting_opts['activate']) && 'on' == $acting_opts['activate'] ) && 
			( isset($acting_opts['card_activate']) && ( 'on' == $acting_opts['card_activate'] || 'link' == $acting_opts['card_activate'] ) ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**********************************************
	* オンライン収納代行有効判定
	* @param  -
	* @return boolean $res
	***********************************************/
	public function is_activate_conv() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset($acting_opts['activate']) && 'on' == $acting_opts['activate'] ) && 
			( isset($acting_opts['conv_activate']) && 'on' == $acting_opts['conv_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**********************************************
	* 後払い決済有効判定
	* @param  -
	* @return boolean $res
	***********************************************/
	public function is_activate_atodene() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset($acting_opts['activate']) && 'on' == $acting_opts['activate'] ) && 
			( isset($acting_opts['atodene_activate']) && 'on' == $acting_opts['atodene_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**********************************************
	* usces_filter_is_complete_settlement
	* ポイント即時付与
	* @param  $complete $payment_name $status
	* @return boorean $complete
	***********************************************/
	public function is_complete_settlement( $complete, $payment_name, $status ) {

		$payment = usces_get_payments_by_name( $payment_name );
		if( 'acting_welcart_card' == $payment['settlement'] ) {
			$complete = true;
		}
		return $complete;
	}

	/**********************************************
	* usces_after_cart_instant
	* 入金通知処理および、三者間決済画面からのリダイレクト
	* @param  -
	* @return -
	***********************************************/
	public function acting_transaction() {
		global $wpdb, $usces;

		if( isset($_REQUEST['MerchantFree1']) && isset($_REQUEST['MerchantId']) && isset($_REQUEST['TransactionId']) && isset($_REQUEST['RecvNum']) && isset($_REQUEST['NyukinDate']) && 
			( isset($_REQUEST['MerchantFree2']) && 'acting_welcart_conv' == $_REQUEST['MerchantFree2'] ) ) {

			$response_data = $_REQUEST;
			$acting_opts = $this->get_acting_settings();
			if( $acting_opts['merchant_id'] == $response_data['MerchantId'] ) {

				$order_meta_table_name = $wpdb->prefix.'usces_order_meta';
				$query = $wpdb->prepare( "SELECT order_id FROM $order_meta_table_name WHERE meta_key = %s", $response_data['MerchantFree1'] );
				$order_id = $wpdb->get_var($query);
				if( !empty($order_id) ) {

					//オーダーステータス変更
					usces_change_order_receipt( $order_id, 'receipted' );
					//ポイント付与
					usces_action_acting_getpoint( $order_id );

					$response_data['OperateId'] = 'receipted';
					$order_meta = maybe_unserialize( $usces->get_order_meta_value( $response_data['MerchantFree2'], $order_id ) );
					$meta_value = array_merge( $order_meta, $response_data );
					$usces->set_order_meta_value( $response_data['MerchantFree2'], serialize($meta_value), $order_id );
					$this->save_acting_history_log( $response_data, $order_id.'_'.$response_data['MerchantFree1'] );
					usces_log('[WelcartPay] conv receipted : '.print_r($response_data, true), 'acting_transaction.log');
				} else {
					usces_log('[WelcartPay] conv receipted order_id error : '.print_r($response_data, true), 'acting_transaction.log');
				}
			}
			header("HTTP/1.0 200 OK");
			die();

		} elseif( isset($_REQUEST['EncryptValue']) ) {
			$acting_opts = $this->get_acting_settings();
			$encryptvalue = openssl_decrypt( $_REQUEST['EncryptValue'], 'aes-128-cbc', $acting_opts['key_aes'], false, $acting_opts['key_iv'] );
			if( $encryptvalue ) {
				parse_str( $encryptvalue, $response_data );
//usces_log(print_r($response_data,true),"test.log");
				if( isset($response_data['OperateId']) && isset($response_data['ResponseCd']) && ( isset($response_data['MerchantId']) && $acting_opts['merchant_id'] == $response_data['MerchantId'] ) ) {
					$cancel = array( 'P51', 'P52', 'P55', 'P56', 'P62', 'P63', 'P64', 'P65', 'P69', 'P70' );
					if( isset($response_data['MerchantFree1']) && ( isset($response_data['MerchantFree2']) && 'acting_welcart_card' == $response_data['MerchantFree2'] ) ) {
						if( 'OK' == $response_data['ResponseCd'] ) {
							//会員登録・会員変更
							if( '4MemAdd' == $response_data['OperateId'] || '4MemChg' == $response_data['OperateId'] ) {
								$member = $usces->get_member();
								$usces->set_member_meta_value( 'wcpay_member_id', $response_data['KaiinId'], $member['ID'] );
								$usces->set_member_meta_value( 'wcpay_member_passwd', $response_data['KaiinPass'], $member['ID'] );

								$usces_entries = $usces->cart->get_entry();
								$cart = $usces->cart->get_cart();

								if( usces_have_continue_charge() ) {
									$chargingday = $usces->getItemChargingDay( $cart[0]['post_id'] );
									if( 99 == $chargingday ) {//受注日課金
										$OperateId = $acting_opts['operateid'];
									} else {
										$OperateId = '1Auth';
									}
								} else {
									$OperateId = $acting_opts['operateid'];
								}

/*								$home_url = str_replace( 'http://', 'https://', home_url('/') );
								$redirecturl = $home_url.'?page_id='.USCES_CART_NUMBER;
								$posturl = $home_url;

								$data_list = array();
								$data_list['OperateId'] = $OperateId;
								$data_list['MerchantPass'] = $acting_opts['merchant_pass'];
								$data_list['TransactionDate'] = $response_data['TransactionDate'];
								$data_list['MerchantFree1'] = $response_data['MerchantFree1'];
								$data_list['MerchantFree2'] = $response_data['MerchantFree2'];
								$data_list['MerchantFree3'] = $this->merchantfree3;
								$data_list['TenantId'] = $acting_opts['tenant_id'];
								$data_list['KaiinId'] = $response_data['KaiinId'];
								$data_list['KaiinPass'] = $response_data['KaiinPass'];
								$data_list['PayType'] = '01';
								$data_list['Amount'] = $usces_entries['order']['total_full_price'];
								$data_list['ProcNo'] = '0000000';
								$data_list['RedirectUrl'] = $redirecturl;
								//$data_list['PostUrl'] = $posturl;
//usces_log(print_r($data_list,true),"test.log");
								$data_query = http_build_query( $data_list );
								$encryptvalue = openssl_encrypt( $data_query, 'aes-128-cbc', $acting_opts['key_aes'], false, $acting_opts['key_iv'] );

								$param_list = array();
								$param_list['MerchantId'] = $acting_opts['merchant_id'];
								$param_list['EncryptValue'] = urlencode($encryptvalue);
								wp_redirect( add_query_arg( $param_list, $acting_opts['send_url_link'] ) );
*/
								$acting = 'welcart_card';
								$param_list = array();
								$params = array();

								//共通部
								$param_list['MerchantId'] = $acting_opts['merchant_id'];
								$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
								$param_list['TransactionDate'] = $response_data['TransactionDate'];
								$param_list['MerchantFree1'] = $response_data['MerchantFree1'];
								$param_list['MerchantFree2'] = $response_data['MerchantFree2'];
								$param_list['MerchantFree3'] = $this->merchantfree3;
								$param_list['TenantId'] = $acting_opts['tenant_id'];
								$param_list['KaiinId'] = $response_data['KaiinId'];
								$param_list['KaiinPass'] = $response_data['KaiinPass'];
								$param_list['OperateId'] = $OperateId;
								$param_list['PayType'] = '01';
								$param_list['Amount'] = $usces_entries['order']['total_full_price'];
								$params['send_url'] = $acting_opts['send_url'];
								$params['param_list'] = $param_list;
								//e-SCOTT 決済
								$response_data = $this->connection( $params );
								$response_data['acting'] = $acting;
								$response_data['PayType'] = '01';

								if( 'OK' == $response_data['ResponseCd'] ) {
									$res = $usces->order_processing( $response_data );
									if( 'ordercompletion' == $res ) {
										$response_data['acting_return'] = 1;
										$response_data['result'] = 1;
										$response_data['nonce'] = wp_create_nonce( 'welcart_transaction' );
										wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
									} else {
										//$response_data['acting_return'] = 0;
										//$response_data['result'] = 0;
										$logdata = array_merge( $usces_entries['order'], $response_data );
										$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$logdata );
										usces_save_order_acting_error( $log );
										//wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
										wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
									}
								} else {
									//$response_data['acting_return'] = 0;
									//$response_data['result'] = 0;
									$responsecd = explode( '|', $response_data['ResponseCd'] );
									foreach( (array)$responsecd as $cd ) {
										$response_data[$cd] = $this->response_message( $cd );
									}
									$logdata = array_merge( $params, $response_data );
									$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$logdata );
									usces_save_order_acting_error( $log );
									//wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
									wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
								}

							//決済
							} else {
								$res = $usces->order_processing( $response_data );
								if( 'ordercompletion' == $res ) {
									$response_data['acting'] = 'welcart_card';
									$response_data['acting_return'] = 1;
									$response_data['result'] = 1;
									$response_data['nonce'] = wp_create_nonce( 'welcart_transaction' );
									wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
									//wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>1, 'result'=>1 ), USCES_CART_URL ) );
								} else {
									$log = array( 'acting'=>$acting, 'key'=>$response_data['MerchantFree1'], 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$response_data );
									usces_save_order_acting_error( $log );
									wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
								}
							}

						} elseif( in_array( $response_data['ResponseCd'], $cancel ) ) {
//usces_log("cancel=".$response_data['ResponseCd'],"test.log");
							wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'confirm'=>1 ), USCES_CART_URL ) );

						} else {
							$log = array( 'acting'=>$acting, 'key'=>$response_data['MerchantFree1'], 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$response_data );
							usces_save_order_acting_error( $log );
							wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
						}

					} else {
						//マイページからの会員登録・会員変更
						if( '4MemAdd' == $response_data['OperateId'] || '4MemChg' == $response_data['OperateId'] ) {
							if( 'OK' == $response_data['ResponseCd'] ) {
								$member = $usces->get_member();
								$usces->set_member_meta_value( 'wcpay_member_id', $response_data['KaiinId'], $member['ID'] );
								$usces->set_member_meta_value( 'wcpay_member_passwd', $response_data['KaiinPass'], $member['ID'] );

							} elseif( in_array( $response_data['ResponseCd'], $cancel ) ) {

							} else {
								usces_log('[WelcartPay] 4MemChg NG : '.print_r($response_data,true), 'acting_transaction.log');
							}
							wp_redirect( USCES_MEMBER_URL );
						}
					}
					exit();
				}
			}
		}
	}

	/**********************************************
	* usces_filter_order_confirm_mail_payment
	* 管理画面送信メール
	* @param  $msg_payment $order_id $payment $cart $data
	* @return str $msg_payment
	***********************************************/
	public function order_confirm_mail_payment( $msg_payment, $order_id, $payment, $cart, $data ) {
		global $usces;

		if( 'acting_welcart_card' == $payment['settlement'] ) {
			$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
			if( isset($acting_data['PayType']) ) {
				$msg_payment = __('** Payment method **','usces')."\r\n";
				$msg_payment .= usces_mail_line( 1, $data['order_email'] );//********************
				$msg_payment .= $payment['name'];
				switch( $acting_data['PayType'] ) {
				case '01':
					$msg_payment .= ' ('.__('One time payment','usces').')';
					break;
				case '02':
				case '03':
				case '05':
				case '06':
				case '10':
				case '12':
				case '15':
				case '18':
				case '20':
				case '24':
					$times = (int)$acting_data['PayType'];
					$msg_payment .= ' ('.$times.__('-time payment','usces').')';
					break;
				case '80':
					$msg_payment .= ' ('.__('Bonus lump-sum payment','usces').')';
					break;
				case '88':
					$msg_payment .= ' ('.__('Libor Funding pay','usces').')';
					break;
				}
				$msg_payment .= "\r\n\r\n";
			}

		} elseif( 'acting_welcart_conv' == $payment['settlement'] && ('orderConfirmMail' == $_POST['mode'] || 'changeConfirmMail' == $_POST['mode']) ) {
			$acting_opts = $this->get_acting_settings();
			$url = $usces->get_order_meta_value( 'welcart_conv_url', $order_id );
			$msg_payment .= sprintf( __("Payment expiration date is %s days.",'usces'), $acting_opts['conv_limit'] )."\r\n";
			$msg_payment .= __("If payment has not yet been completed, please payment procedure from the following URL.",'usces')."\r\n\r\n";
			$msg_payment .= __("[Payment URL]",'usces')."\r\n";
			$msg_payment .= $url."\r\n";
		}
		return $msg_payment;
	}

	/**********************************************
	* usces_filter_send_order_mail_payment
	* オンライン収納代行決済用サンキューメール
	* @param  $msg_payment $order_id $payment $cart $entry $data
	* @return str $msg_payment
	***********************************************/
	public function order_mail_payment( $msg_payment, $order_id, $payment, $cart, $entry, $data ) {
		global $usces;

		if( 'acting_welcart_conv' != $payment['settlement'] ) {
			return $msg_payment;
		}

		$acting_opts = $this->get_acting_settings();
		$url = $usces->get_order_meta_value( 'welcart_conv_url', $order_id );
		$msg_payment .= sprintf( __("Payment expiration date is %s days.",'usces'), $acting_opts['conv_limit'] )."\r\n";
		$msg_payment .= __("If payment has not yet been completed, please payment procedure from the following URL.",'usces')."\r\n\r\n";
		$msg_payment .= __("[Payment URL]",'usces')."\r\n";
		$msg_payment .= $url."\r\n";
		return $msg_payment;
	}

	/**********************************************
	* usces_filter_get_error_settlement
	* 決済エラーメッセージ
	* @param  $html
	* @return str $html
	***********************************************/
	public function error_page_message( $html ) {

		$acting = ( isset($_REQUEST['MerchantFree2']) ) ? $_REQUEST['MerchantFree2'] : '';
		switch( $acting ) {
		case 'acting_welcart_card':
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
					<p class="return_settlement"><a href="'.add_query_arg( array( 'backDelivery'=>'welcart_card', 're-enter'=>1 ), USCES_CART_URL ).'">'.__('Card number re-enter','usces').'</a></p>
					</div>';
				}
			}
			break;

		case 'acting_welcart_conv':
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

		case 'acting_welcart_atodene':
			break;
		}
		return $html;
	}

	/**********************************************
	* usces_filter_orderlist_detail_value
	* 決済状況
	* @param  $detail $value $key $order_id
	* @return array $keys
	***********************************************/
	public function orderlist_settlement_status( $detail, $value, $key, $order_id ) {
		global $usces;

		if( 'wc_trans_id' != $key || empty($value) ) {
			return $detail;
		}

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$payment = usces_get_payments_by_name( $order_data['order_payment_name'] );
		$acting_flg = ( isset($payment['settlement']) ) ? $payment['settlement'] : '';

		switch( $acting_flg ) {
		case 'acting_welcart_card':
			$trans_id = $usces->get_order_meta_value( 'trans_id', $order_id );
			$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id );
			if( isset($latest_log['OperateId']) ) {
				$class = ( ctype_digit(substr($latest_log['OperateId'],0,1)) ) ? ' card-'.mb_strtolower(substr($latest_log['OperateId'],1)) : ' card-'.$latest_log['OperateId'];
				$detail = '<td>'.$value.'<span class="acting-status'.$class.'">'.$this->get_operate_name( $latest_log['OperateId'] ).'</span></td>';
			} elseif( defined('WCEX_AUTO_DELIVERY') ) {
				$regular_id = $usces->get_order_meta_value( 'regular_id', $order_id );
				if( !empty($regular_id) && empty($trans_id) ) {
					$detail = '<td>'.$value.'<span class="acting-status card-error">'.__('Card unregistered','usces').'</span></td>';
				}
			}
			break;

		case 'acting_welcart_conv':
			$trans_id = $usces->get_order_meta_value( 'trans_id', $order_id );
			$expiration = $this->check_paylimit( $order_id, $trans_id );
			if( $expiration ) {
				$detail = '<td>'.$value.'<span class="acting-status conv-expiration">'.__('Expired','usces').'</span></td>';
			} else {
				$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id );
				if( isset($latest_log['OperateId']) && '2Del' == $latest_log['OperateId'] ) {
					$detail = '<td>'.$value.'<span class="acting-status conv-del">'.__('Canceled','usces').'</span></td>';
				} else {
					$management_status = apply_filters( 'usces_filter_management_status', get_option( 'usces_management_status' ) );
					if( $usces->is_status('noreceipt', $value) ) {
						$detail = '<td>'.$value.'<span class="acting-status conv-noreceipt">'.esc_html($management_status['noreceipt']).'</span></td>';
					} elseif( $usces->is_status('receipted', $value) ) {
						$detail = '<td>'.$value.'<span class="acting-status conv-receipted">'.esc_html($management_status['receipted']).'</span></td>';
					} else {
						$detail = '<td>'.$value.'</td>';
					}
				}
			}
			break;

		case 'acting_welcart_atodene':
			break;
		}
		return $detail;
	}

	/**********************************************
	* usces_filter_settle_info_field_keys
	* 受注編集画面に表示する決済情報のキー
	* @param  $keys
	* @return array $keys
	***********************************************/
	public function settlement_info_field_keys( $keys ) {

		$field_keys = array_merge( $keys, array( 'MerchantFree1', 'ResponseCd', 'PayType', 'CardNo', 'CardExp', 'KessaiNumber', 'NyukinDate', 'CvsCd', 'PayLimit' ) );
		return $field_keys;
	}

	/**********************************************
	* usces_filter_settle_info_field_value
	* 受注編集画面に表示する決済情報の値整形
	* @param  $value $key $acting
	* @return str $value
	***********************************************/
	public function settlement_info_field_value( $value, $key, $acting ) {

		if( 'welcart_card' != $acting && 'welcart_conv' != $acting && 'welcart_atodene' != $acting ) {
			return $value;
		}

		switch( $key ) {
		case 'acting':
			switch( $value ) {
			case 'welcart_card':
				$value = __('WelcartPay - Credit card transaction','usces');
				break;
			case 'welcart_conv':
				$value = __('WelcartPay - Online storage agency','usces');
				break;
			case 'welcart_atodene':
				$value = __('WelcartPay - Postpay settlement','usces');
				break;
			}
			break;

		case 'CvsCd':
			$value = $this->get_cvs_name($value);
			break;

		case 'PayType':
			switch( $value ) {
			case '01':
				$value = __('One time payment','usces');
				break;
			case '02':
			case '03':
			case '05':
			case '06':
			case '10':
			case '12':
			case '15':
			case '18':
			case '20':
			case '24':
				$times = (int)$value;
				$value = $times.__('-time payment','usces');
				break;
			case '80':
				$value = __('Bonus lump-sum payment','usces');
				break;
			case '88':
				$value = __('Libor Funding pay','usces');
				break;
			}
		}
		return $value;
	}

	/**********************************************
	* usces_action_order_edit_form_status_block_middle
	* 
	* @param  $data $cscs_meta $action_args = array( 'order_action', 'order_id', 'cart' );
	* @return -
	***********************************************/
	public function settlement_status( $data, $cscs_meta, $action_args ) {
		global $usces;
		extract($action_args);

		if( $order_action != 'new' && !empty($order_id) ) {
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if( in_array( $payment['settlement'], $this->pay_method ) ) {
				$acting_data = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
				$MerchantFree1 = ( isset($acting_data['MerchantFree1']) ) ? $acting_data['MerchantFree1'] : '';
				if( !empty($MerchantFree1) ) {
					$status_name = '';
					$class = '';
					$latest_log = $this->get_acting_latest_log( $order_id.'_'.$MerchantFree1 );
					if( isset($latest_log['OperateId']) ) {
						if( 'acting_welcart_conv' == $payment['settlement'] ) {
							$expiration = $this->check_paylimit( $order_id, $MerchantFree1 );
							if( $expiration ) {
								$class = ' conv-expiration';
								$status_name = __('Expired','usces');
							} else {
								if( '2Del' == $latest_log['OperateId'] ) {
									$class = ' conv-del';
									$status_name = __('Canceled','usces');
								}
							}
						} else {
							$class = ' card-'.mb_strtolower(substr($latest_log['OperateId'],1));
							$status_name = $this->get_operate_name( $latest_log['OperateId'] );
						}
						if( !empty($status_name) ) {
							echo '
							<tr>
								<td class="label status">'.__('Settlement status','usces').'</td>
								<td class="col1 status"><span id="settlement-status"><span class="acting-status'.$class.'">'.$status_name.'</span></span></td>
							</tr>';
						}
					}
				} elseif( defined('WCEX_AUTO_DELIVERY') ) {
					$regular_id = $usces->get_order_meta_value( 'regular_id', $order_id );
					if( !empty($regular_id) ) {
						echo '
						<tr>
							<td class="label status">'.__('Settlement status','usces').'</td>
							<td class="col1 status"><span id="settlement-status"><span class="acting-status card-error">'.__('Card unregistered','usces').'</span></span></td>
						</tr>';
					}
				}
			}
		}
	}

	/**********************************************
	* usces_action_order_edit_form_settle_info
	* 
	* @param  $data $action_args = array( 'order_action', 'order_id', 'cart' );
	* @return -
	***********************************************/
	public function settlement_information( $data, $action_args ) {
		global $usces;
		extract($action_args);

		if( $order_action != 'new' && !empty($order_id) ) {
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if( in_array( $payment['settlement'], $this->pay_method ) ) {
				$acting_data = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
				$MerchantFree1 = ( isset($acting_data['MerchantFree1']) && isset($acting_data['ProcessId']) && isset($acting_data['ProcessPass']) ) ? $acting_data['MerchantFree1'] : '9999999999';
				//if( isset($acting_data['MerchantFree1']) && isset($acting_data['ProcessId']) && isset($acting_data['ProcessPass']) ) {
				//	echo '<input type="button" id="settlement-information-'.$acting_data['MerchantFree1'].'-1" class="button settlement-information" value="'.__('Settlement info','usces').'">';
				//}
				echo '<input type="button" id="settlement-information-'.$MerchantFree1.'-1" class="button settlement-information" value="'.__('Settlement info','usces').'">';
			}
		}
	}

	/**********************************************
	* usces_action_endof_order_edit_form
	* 決済情報ダイアログ
	* @param  $data $action_args = array( 'order_action', 'order_id', 'cart' );
	* @return -
	* @echo   html
	***********************************************/
	public function settlement_dialog( $data, $action_args ) {
		global $usces;
		extract($action_args);

		if( $order_action != 'new' && !empty($order_id) ):
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if( in_array( $payment['settlement'], $this->pay_method ) ):
				//$acting_data = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
				//if( isset($acting_data['MerchantFree1']) && isset($acting_data['ProcessId']) && isset($acting_data['ProcessPass']) ):
?>
<div id="settlement_dialog" title="">
	<div id="settlement-response-loading"></div>
	<fieldset>
	<div id="settlement-response"></div>
	<input type="hidden" id="order_num">
	<input type="hidden" id="trans_id">
	<input type="hidden" id="acting" value="<?php echo $payment['settlement']; ?>">
	<input type="hidden" id="error">
	</fieldset>
</div>
<?php
				//endif;
			endif;
		endif;
	}

	/**********************************************
	* usces_action_reg_orderdata
	* 受注データ登録
	* @param  $args = array(
				'cart'=>$cart, 'entry'=>$entry, 'order_id'=>$order_id, 
				'member_id'=>$member['ID'], 'payments'=>$set, 'charging_type'=>$charging_type, 
				'results'=>$results
				);
	* @return -
	***********************************************/
	public function register_order_metadata( $args ) {
		global $usces;
		extract($args);

		$acting_flg = $payments['settlement'];
		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		if( !$entry['order']['total_full_price'] ) {
			return;
		}

		$usces->set_order_meta_value( 'trans_id', $results['MerchantFree1'], $order_id );
		$usces->set_order_meta_value( 'wc_trans_id', $results['MerchantFree1'], $order_id );
		$usces->set_order_meta_value( $acting_flg, serialize($results), $order_id );
		$this->save_acting_history_log( $results, $order_id.'_'.$results['MerchantFree1'] );

		if( 'acting_welcart_conv' == $acting_flg ) {
			$usces->set_order_meta_value( $results['MerchantFree1'], $acting_flg, $order_id );

			$acting_opts = $this->get_acting_settings();
			$FreeArea = trim($results['FreeArea']);
			$url = add_query_arg( array( 'code'=>$FreeArea, 'rkbn'=>2 ), $acting_opts['redirect_url_conv'] );
			$usces->set_order_meta_value( 'welcart_conv_url', $url, $order_id );
		}
	}

	/**********************************************
	* usces_action_revival_order_data
	* 受注データ復旧処理
	* @param  $order_id $log_key $acting
	* @return -
	***********************************************/
	public function revival_order_metadata( $order_id, $log_key, $acting ) {
		global $usces;

		if( !in_array( $acting, $this->pay_method ) ) {
			return;
		}

		$usces->set_order_meta_value( 'trans_id', $log_key, $order_id );
		$usces->set_order_meta_value( 'wc_trans_id', $log_key, $order_id );

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$order_meta = array();
		$order_meta['acting'] = substr($acting,7);
		$order_meta['MerchantFree1'] = $log_key;
		$total_full_price = $order_data['order_item_total_price'] - $order_data['order_usedpoint'] + $order_data['order_discount'] + $order_data['order_shipping_charge'] + $order_data['order_cod_fee'] + $order_data['order_tax'];
		if( $total_full_price < 0 ) $total_full_price = 0;
		$order_meta['Amount'] = $total_full_price;
		if( 'acting_welcart_conv' == $acting ) {
			$acting_opts = $this->get_acting_settings();
			$paylimit = date_i18n( 'Ymd', strtotime($order_data['order_date'])+(86400*$acting_opts['conv_limit']) ).'2359';
			$order_meta['PayLimit'] = $paylimit;
		}
		$usces->set_order_meta_value( $acting, serialize($order_meta), $order_id );

		if( 'acting_welcart_conv' == $acting ) {
			$usces->set_order_meta_value( $log_key, $acting, $order_id );
		}
	}

	/**********************************************
	* usces_filter_check_acting_return_duplicate
	* 重複オーダー禁止処理
	* @param  $trans_id $results
	* @return str RandId
	***********************************************/
	public function check_acting_return_duplicate( $trans_id, $results ) {
		global $usces;

		$entry = $usces->cart->get_entry();
		if( !$entry['order']['total_full_price'] ) {
			return 'not_credit';
		} elseif( isset($results['MerchantFree1']) && isset($results['acting']) && ( 'welcart_card' == $results['acting'] || 'welcart_conv' == $results['acting'] || 'welcart_atodene' == $results['acting'] ) ) {
			return $results['MerchantFree1'];
		} else {
			return $trans_id;
		}
	}

	/**********************************************
	* usces_filter_delivery_check
	* カード情報入力チェック
	* @param  $mes
	* @return str $mes
	***********************************************/
	public function delivery_check( $mes ) {
		global $usces;

		if( !isset($_POST['offer']['payment_name']) ) {
			return $mes;
		}

		$payment = $usces->getPayments( $_POST['offer']['payment_name'] );
		if( 'acting_welcart_card' == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( 'on' == $acting_opts['seccd'] ) {
				if( ( isset($_POST['acting']) && 'welcart' == $_POST['acting'] ) && 
					( isset($_POST['cardno']) && empty($_POST['cardno']) ) || 
					( isset($_POST['seccd']) && empty($_POST['seccd']) ) || 
					( isset($_POST['expyy']) && empty($_POST['expyy']) ) || 
					( isset($_POST['expmm']) && empty($_POST['expmm']) ) ) {
					$mes .= __('Please enter the card information correctly.','usces').'<br />';
				}
			} else {
				if( ( isset($_POST['acting']) && 'welcart' == $_POST['acting'] ) && 
					( isset($_POST['cardno']) && empty($_POST['cardno']) ) || 
					( isset($_POST['expyy']) && empty($_POST['expyy']) ) || 
					( isset($_POST['expmm']) && empty($_POST['expmm']) ) ) {
					$mes .= __('Please enter the card information correctly.','usces').'<br />';
				}
			}
		}
		return $mes;
	}

	/**********************************************
	* usces_filter_payment_detail
	* 支払方法説明
	* @param  $str $usces_entries
	* @return str $str
	***********************************************/
	public function payment_detail( $str, $usces_entries ) {
		global $usces;

		$payment = $usces->getPayments( $usces_entries['order']['payment_name'] );
		if( 'acting_welcart_card' == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( 1 === (int)$acting_opts['howtopay'] ) {
				$str = ' ('.__('One time payment','usces').')';
			} else {
				$paytype = ( isset($usces_entries['order']['paytype']) ) ? esc_html($usces_entries['order']['paytype']) : '';
				switch( $paytype ) {
				case '01':
					$str = ' ('.__('One time payment','usces').')';
					break;
				case '02':
				case '03':
				case '05':
				case '06':
				case '10':
				case '12':
				case '15':
				case '18':
				case '20':
				case '24':
					$times = (int)$paytype;
					$str = ' ('.$times.__('-time payment','usces').')';
					break;
				case '80':
					$str = ' ('.__('Bonus lump-sum payment','usces').')';
					break;
				case '88':
					$str = ' ('.__('Libor Funding pay','usces').')';
					break;
				}
			}
		}
		return $str;
	}

	/**********************************************
	* usces_filter_payments_str
	* 支払方法 JavaScript 用決済名追加
	* @param  $payments_str $payment
	* @return str $payments_str
	***********************************************/
	public function payments_str( $payments_str, $payment ) {
		global $usces;

		switch( $payment['settlement'] ) {
		case 'acting_welcart_card':
			if( $this->is_activate_card() ) {
				$payments_str .= "'".$payment['name']."': '".$this->paymod_id."', ";
			}
			break;
		}
		return $payments_str;
	}

	/**********************************************
	* usces_filter_payments_arr
	* 支払方法 JavaScript 用決済追加
	* @param  $payments_arr $payment
	* @return array $payments_arr
	***********************************************/
	public function payments_arr( $payments_arr, $payment ) {
		global $usces;

		switch( $payment['settlement'] ) {
		case 'acting_welcart_card':
			if( $this->is_activate_card() ) {
				$payments_arr[] = $this->paymod_id;
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

		//発送・支払方法ページ
		if( 'delivery' == $usces->page && $this->is_validity_welcart('card') ):
			$acting_opts = $this->get_acting_settings();
			if( isset($acting_opts['card_activate']) && 'on' == $acting_opts['card_activate'] ):
?>
<script type="text/javascript">
(function($) {
	$("#welcart_cnum").change( function(e) {
		//console.log($(this).val());
		var first_c = $(this).val().substr( 0, 1 );
		var second_c = $(this).val().substr( 1, 1 );
		if( '4' == first_c || '5' == first_c || ( '3' == first_c && '5' == second_c ) ) {
			$("#welcart_paytype_default").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype4535").removeAttr("disabled").css("display", "inline");
			$("#welcart_paytype37").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype36").attr("disabled", "disabled").css("display", "none");
		} else if( '3' == first_c && '6' == second_c ) {
			$("#welcart_paytype_default").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype4535").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype37").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype36").removeAttr("disabled").css("display", "inline");
		} else if( '3' == first_c && '7' == second_c ) {
			$("#welcart_paytype_default").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype4535").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype37").removeAttr("disabled").css("display", "inline");
			$("#welcart_paytype36").attr("disabled", "disabled").css("display", "none");
		} else {
			$("#welcart_paytype_default").removeAttr("disabled").css("display", "inline");
			$("#welcart_paytype4535").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype37").attr("disabled", "disabled").css("display", "none");
			$("#welcart_paytype36").attr("disabled", "disabled").css("display", "none");
		}
	});
	$("#welcart_cnum").trigger( "change" );
		<?php if( isset($_REQUEST['backDelivery']) && 'welcart_card' == substr($_REQUEST['backDelivery'], 0, 12) ):
			$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );
			$id = $payment_method['acting_welcart_card']['sort']; ?>
	$("#payment_name_<?php echo $id; ?>").prop( "checked", true );
		<?php endif; ?>
})(jQuery);
</script>
<?php
			endif;

		//クレジットカード情報更新ページ
		elseif( 'member_register_settlement' == $usces->page || 'member_update_settlement' == $usces->page ):
?>
<script type="text/javascript">
(function($) {
	$(document).on( "click", "#card-delete", function() {
		if( confirm("<?php _e('Are you sure delete credit card registration?','usces'); ?>") ) {
			$("input[name='delete']").val("delete");
			$("form#member-card-info").submit();
		}
	});

})(jQuery);
</script>
<?php
		//マイページ
		elseif( $usces->is_member_page($_SERVER['REQUEST_URI']) ):
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
	* usces_filter_delivery_secure_form_loop
	* 支払方法ページ用入力フォーム
	* @param  $nouse $payment
	* @return str $html
	***********************************************/
	public function delivery_secure_form( $nouse, $payment ) {
		global $usces;

		$html = '';
		switch( $payment['settlement'] ) {
		case 'acting_welcart_card':
			$acting_opts = $this->get_acting_settings();
			if( ( !isset($acting_opts['activate']) || 'on' != $acting_opts['activate'] ) || 
				( !isset($acting_opts['card_activate']) || 'on' != $acting_opts['card_activate'] ) ) {
				continue;
			}

			$backDelivery = ( isset($_REQUEST['backDelivery']) && 'welcart_card' == substr($_REQUEST['backDelivery'], 0, 12) ) ? true : false;
			$card_change = ( isset($_REQUEST['card_change']) ) ? true : false;
			if( $card_change ) {
				if( 'on' == $acting_opts['seccd'] ) {
					if( ( isset($_POST['acting']) && 'welcart' == $_POST['acting'] ) && 
						( isset($_POST['cardno']) && empty($_POST['cardno']) ) || 
						( isset($_POST['seccd']) && empty($_POST['seccd']) ) || 
						( isset($_POST['expyy']) && empty($_POST['expyy']) ) || 
						( isset($_POST['expmm']) && empty($_POST['expmm']) ) ) {
						$backDelivery = true;
					}
				} else {
					if( ( isset($_POST['acting']) && 'welcart' == $_POST['acting'] ) && 
						( isset($_POST['cardno']) && empty($_POST['cardno']) ) || 
						( isset($_POST['expyy']) && empty($_POST['expyy']) ) || 
						( isset($_POST['expmm']) && empty($_POST['expmm']) ) ) {
						$backDelivery = true;
					}
				}
			}

			$cardno = ( isset($_POST['cardno']) ) ? esc_html($_POST['cardno']) : '';
			$expyy = ( isset($_POST['expyy']) ) ? esc_html($_POST['expyy']) : '';
			$expmm = ( isset($_POST['expmm']) ) ? esc_html($_POST['expmm']) : '';
			$paytype = ( isset($usces_entries['order']['paytype']) ) ? esc_html($usces_entries['order']['paytype']) : '01';

			$html .= '<input type="hidden" name="acting" value="'.$this->paymod_id.'">';
			$html .= '
			<table class="customer_form" id="'.$this->paymod_id.'">';

			if( usces_is_login() ) {
				$member = $usces->get_member();
				$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
				$KaiinPass = $this->get_quick_pass( $member['ID'] );
			}

			$response_member = array( 'ResponseCd'=>'' );

			if( 'on' == $acting_opts['quickpay'] && !empty($KaiinId) && !empty($KaiinPass) && !$card_change ) {
				//e-SCOTT 会員照会
				$response_member = $this->escott_member_reference( $member['ID'], $KaiinId, $KaiinPass );
			}

			if( 'OK' == $response_member['ResponseCd'] && !$backDelivery ) {
				$cardlast4 = substr($response_member['CardNo'], -4);
				$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
				$expmm = substr($response_member['CardExp'], 2, 2);
				$html .= '
				<input name="cardno" type="hidden" value="8888888888888888" />
				<input name="cardlast4" type="hidden" value="'.$cardlast4.'" />
				<input name="expyy" type="hidden" value="'.$expyy.'" />
				<input name="expmm" type="hidden" value="'.$expmm.'" />
				<input name="quick_member" type="hidden" value="add">
				<tr>
					<th scope="row">'.__('The last four digits of your card number','usces').'</th>
					<td colspan="2"><p>'.$cardlast4.' (<a href="'.add_query_arg( array('backDelivery'=>'welcart_card','card_change'=>1), USCES_CART_URL ).'">'.__('Change of card information, click here','usces').'</a>)</p></td>
				</tr>';

			} else {
				$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __('(Single-byte numbers only)','usces').'<div class="attention">'.__('* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.','usces').'</div>' );
				$change = ( $card_change ) ? '<input type="hidden" name="card_change" value="1">' : '';
				$quickpay = '';
				if( usces_is_login() && 'on' == $acting_opts['quickpay'] ) {
					if( usces_have_regular_order() || usces_have_continue_charge() ) {
						$quickpay = '<input type="hidden" name="quick_member" value="add">';
					} else {
						$quickpay = '<p class="welcartpay_quick_member"><label type="add"><input type="checkbox" name="quick_member" value="add"><span>'.__('Register and purchase a credit card','usces').'</span></label></p>';
					}
				} else {
					$quickpay = '<input type="hidden" name="quick_member" value="no">';
				}
				$html .= '
				<tr>
					<th scope="row">'.__('card number','usces').'<input name="acting" type="hidden" value="'.$this->paymod_id.'" /></th>
					<td colspan="2"><input name="cardno" type="text" id="welcart_cnum" size="16" value="'.$cardno.'" />'.$cardno_attention.$change.$quickpay.'</td>
				</tr>';
				if( 'on' == $acting_opts['seccd'] ) {
					$seccd = ( isset($_POST['seccd']) ) ? esc_html($_POST['seccd']) : '';
					$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __('(Single-byte numbers only)','usces') );
					$html .= '
				<tr>
					<th scope="row">'.__('security code','usces').'</th>
					<td colspan="2"><input name="seccd" type="text" size="6" value="'.$seccd.'" />'.$seccd_attention.'</td>
				</tr>';
				}
				$html .= '
				<tr>
					<th scope="row">'.__('Card expiration','usces').'</th>
					<td colspan="2">
						<select name="expmm">
							<option value=""'.(empty($expmm) ? ' selected="selected"' : '').'>----</option>';
				for( $i = 1; $i <= 12; $i++ ) {
					$html .= '
							<option value="'.sprintf('%02d', $i).'"'.(( $i == (int)$expmm ) ? ' selected="selected"' : '').'>'.sprintf('%2d', $i).'</option>';
				}
				$html .= '
						</select>'.__('month','usces').'&nbsp;
						<select name="expyy">
							<option value=""'.(empty($expyy) ? ' selected="selected"' : '').'>------</option>';
				for( $i = 0; $i < 15; $i++ ) {
					$year = date_i18n('Y') - 1 + $i;
					$selected = ( $year == $expyy ) ? ' selected="selected"' : '';
					$html .= '
							<option value="'.$year.'"'.$selected.'>'.$year.'</option>';
				}
				$html .= '
						</select>'.__('year','usces').'
					</td>
				</tr>';
			}

			$html_paytype = '';
			if( ( usces_have_regular_order() || usces_have_continue_charge() ) && usces_is_login() ) {
				$html_paytype .= '<input type="hidden" name="offer[paytype]" value="01" />';

			} else {
				if( 1 === (int)$acting_opts['howtopay'] ) {
					$html_paytype .= '
				<tr>
					<th scope="row">'.__('Number of payments','usces').'</th>
					<td colspan="2">'.__('Single payment only','usces').'
						<input type="hidden" name="offer[paytype]" value="01" />
					</td>
				</tr>';

				} elseif( 2 <= $acting_opts['howtopay'] ) {
					$cardfirst4 = ( isset($response_member['CardNo']) ) ? substr($response_member['CardNo'], 0, 4) : '';//先頭4桁
					$html_paytype .= '
				<tr>
					<th scope="row">'.__('Number of payments','usces').'</th>
					<td colspan="2"><input type="hidden" id="welcart_cnum" value="'.$cardfirst4.'" /><div class="paytype">';

					$html_paytype .= '
						<select name="offer[paytype]" id="welcart_paytype_default" >
							<option value="01"'.(('01' == $paytype) ? ' selected="selected"' : '').'>'.__('One time payment','usces').'</option>
						</select>';

					$html_paytype .= '
						<select name="offer[paytype]" id="welcart_paytype4535" style="display:none;" disabled="disabled" >
							<option value="01"'.(('01' == $paytype) ? ' selected="selected"' : '').'>1'.__('-time payment','usces').'</option>
							<option value="02"'.(('02' == $paytype) ? ' selected="selected"' : '').'>2'.__('-time payment','usces').'</option>
							<option value="03"'.(('03' == $paytype) ? ' selected="selected"' : '').'>3'.__('-time payment','usces').'</option>
							<option value="05"'.(('05' == $paytype) ? ' selected="selected"' : '').'>5'.__('-time payment','usces').'</option>
							<option value="06"'.(('06' == $paytype) ? ' selected="selected"' : '').'>6'.__('-time payment','usces').'</option>
							<option value="10"'.(('10' == $paytype) ? ' selected="selected"' : '').'>10'.__('-time payment','usces').'</option>
							<option value="12"'.(('12' == $paytype) ? ' selected="selected"' : '').'>12'.__('-time payment','usces').'</option>
							<option value="15"'.(('15' == $paytype) ? ' selected="selected"' : '').'>15'.__('-time payment','usces').'</option>
							<option value="18"'.(('18' == $paytype) ? ' selected="selected"' : '').'>18'.__('-time payment','usces').'</option>
							<option value="20"'.(('20' == $paytype) ? ' selected="selected"' : '').'>20'.__('-time payment','usces').'</option>
							<option value="24"'.(('24' == $paytype) ? ' selected="selected"' : '').'>24'.__('-time payment','usces').'</option>
							<option value="88"'.(('88' == $paytype) ? ' selected="selected"' : '').'>'.__('Libor Funding pay','usces').'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80"'.(('80' == $paytype) ? ' selected="selected"' : '').'>'.__('Bonus lump-sum payment','usces').'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '
						<select name="offer[paytype]" id="welcart_paytype37" style="display:none;" disabled="disabled" >
							<option value="01"'.(('01' == $paytype) ? ' selected="selected"' : '').'>1'.__('-time payment','usces').'</option>
							<option value="03"'.(('03' == $paytype) ? ' selected="selected"' : '').'>3'.__('-time payment','usces').'</option>
							<option value="05"'.(('05' == $paytype) ? ' selected="selected"' : '').'>5'.__('-time payment','usces').'</option>
							<option value="06"'.(('06' == $paytype) ? ' selected="selected"' : '').'>6'.__('-time payment','usces').'</option>
							<option value="10"'.(('10' == $paytype) ? ' selected="selected"' : '').'>10'.__('-time payment','usces').'</option>
							<option value="12"'.(('12' == $paytype) ? ' selected="selected"' : '').'>12'.__('-time payment','usces').'</option>
							<option value="15"'.(('15' == $paytype) ? ' selected="selected"' : '').'>15'.__('-time payment','usces').'</option>
							<option value="18"'.(('18' == $paytype) ? ' selected="selected"' : '').'>18'.__('-time payment','usces').'</option>
							<option value="20"'.(('20' == $paytype) ? ' selected="selected"' : '').'>20'.__('-time payment','usces').'</option>
							<option value="24"'.(('24' == $paytype) ? ' selected="selected"' : '').'>24'.__('-time payment','usces').'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80"'.(('80' == $paytype) ? ' selected="selected"' : '').'>'.__('Bonus lump-sum payment','usces').'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '
						<select name="offer[paytype]" id="welcart_paytype36" style="display:none;" disabled="disabled" >
							<option value="01"'.(('01' == $paytype) ? ' selected="selected"' : '').'>'.__('One time payment','usces').'</option>
							<option value="88"'.(('88' == $paytype) ? ' selected="selected"' : '').'>'.__('Libor Funding pay','usces').'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80"'.(('80' == $paytype) ? ' selected="selected"' : '').'>'.__('Bonus lump-sum payment','usces').'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '</div>
					</td>
				</tr>';
				}
			}
			$html .= apply_filters( 'usces_filter_welcart_secure_form_paytype', $html_paytype );
			$html .= '
			</table><table>';
			break;
		}
		return $html;
	}

	/**********************************************
	* usces_action_acting_processing
	* 決済処理
	* @param  $acting_flg $post_query
	* @return -
	***********************************************/
	public function acting_processing( $acting_flg, $post_query ) {
		global $usces;

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		$usces_entries = $usces->cart->get_entry();
		$cart = $usces->cart->get_cart();

		if( !$usces_entries || !$cart ) {
			wp_redirect(USCES_CART_URL);
		}

		if( !wp_verify_nonce( $_REQUEST['_nonce'], $acting_flg ) ) {
			wp_redirect(USCES_CART_URL);
		}

		$acting_opts = $this->get_acting_settings();
		parse_str( $post_query, $post_data );
//usces_log(print_r($post_data,true),"test.log");
		$TransactionDate = $this->get_transaction_date();
		$rand = $post_data['rand'];
		$member = $usces->get_member();


		//Duplication control
		$this->duplication_control( $acting_flg, $rand );

		usces_save_order_acting_data( $rand );

		//処理部
		switch( $acting_flg ) {
		case 'acting_welcart_card':
			$acting = 'welcart_card';
			$param_list = array();
			$params = array();

			if( 'on' == $acting_opts['card_activate'] ) {
				//共通部
				$param_list['MerchantId'] = $acting_opts['merchant_id'];
				$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
				$param_list['TransactionDate'] = $TransactionDate;
				$param_list['MerchantFree1'] = $rand;
				$param_list['MerchantFree2'] = $acting_flg;
				$param_list['MerchantFree3'] = $this->merchantfree3;
				$param_list['TenantId'] = $acting_opts['tenant_id'];
				$param_list['Amount'] = $usces_entries['order']['total_full_price'];

				$quick_member = ( isset($post_data['quick_member']) ) ? $post_data['quick_member'] : '';
				if( !empty($member['ID']) && 'on' == $acting_opts['quickpay'] && 'add' == $quick_member ) {
					$response_member = $this->escott_member_process( $param_list );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$param_list['KaiinId'] = $response_member['KaiinId'];
						$param_list['KaiinPass'] = $response_member['KaiinPass'];
					} else {
						$response_data['MerchantFree2'] = $response_member['MerchantFree2'];
						$response_data['ResponseCd'] = $response_member['ResponseCd'];
						$response_data['acting'] = $acting;
						$response_data['acting_return'] = 0;
						$response_data['result'] = 0;
						unset( $response_member['CardNo'] );
						unset( $response_member['CardExp'] );
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach( (array)$responsecd as $cd ) {
							$response_member[$cd] = $this->response_message( $cd );
						}
						$logdata = array_merge( $param_list, $response_member );
						$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$rand, 'result'=>$response_member['ResponseCd'], 'data'=>$logdata );
						usces_save_order_acting_error( $log );
						wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
						exit();
					}
					if( usces_have_continue_charge() ) {
						$chargingday = $usces->getItemChargingDay( $cart[0]['post_id'] );
						if( 99 == $chargingday ) {//受注日課金
							$param_list['OperateId'] = $acting_opts['operateid'];
						} else {
							$param_list['OperateId'] = '1Auth';
						}
						$param_list['PayType'] = '01';
					} else {
						$param_list['OperateId'] = $acting_opts['operateid'];
						$param_list['PayType'] = $post_data['paytype'];
					}
				} else {
					$param_list['OperateId'] = $acting_opts['operateid'];
					$param_list['PayType'] = $post_data['paytype'];
					$param_list['CardNo'] = trim($post_data['cardno']);
					$param_list['CardExp'] = substr($post_data['expyy'],2).$post_data['expmm'];
					if( 'on' == $acting_opts['seccd'] ) {
						$param_list['SecCd'] = trim($post_data['seccd']);
					}
				}
				$params['send_url'] = $acting_opts['send_url'];
				$params['param_list'] = $param_list;
				//e-SCOTT 決済
				$response_data = $this->connection( $params );
				$response_data['acting'] = $acting;
				$response_data['PayType'] = $param_list['PayType'];
				$response_data['CardNo'] = ( !empty($post_data['cardlast4']) ) ? $post_data['cardlast4'] : substr($post_data['cardno'],-4);
				$response_data['CardExp'] = $post_data['expyy'].'/'.$post_data['expmm'];

				if( 'OK' == $response_data['ResponseCd'] ) {
					$res = $usces->order_processing( $response_data );
					if( 'ordercompletion' == $res ) {
						$response_data['acting_return'] = 1;
						$response_data['result'] = 1;
						$response_data['nonce'] = wp_create_nonce( 'welcart_transaction' );
						wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
					} else {
						$response_data['acting_return'] = 0;
						$response_data['result'] = 0;
						unset( $response_data['CardNo'] );
						unset( $response_data['CardExp'] );
						$logdata = array_merge( $usces_entries['order'], $response_data );
						$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$logdata );
						usces_save_order_acting_error( $log );
						wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
					}
				} else {
					$response_data['acting_return'] = 0;
					$response_data['result'] = 0;
					unset( $response_data['CardNo'] );
					unset( $response_data['CardExp'] );
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)$responsecd as $cd ) {
						$response_data[$cd] = $this->response_message( $cd );
					}
					$logdata = array_merge( $params, $response_data );
					$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$logdata );
					usces_save_order_acting_error( $log );
					wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
				}

			} elseif( 'link' == $acting_opts['card_activate'] ) {

				$quick_member = ( isset($post_data['quick_member']) ) ? $post_data['quick_member'] : '';
				if( !empty($member['ID']) && 'on' == $acting_opts['quickpay'] ) {
					$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
					$KaiinPass = $this->get_quick_pass( $member['ID'] );
				} else {
					$KaiinId = '';
					$KaiinPass = '';
				}
				if( empty($KaiinId) || empty($KaiinPass) ) {
					$quick_member = 'no';
				}

				if( usces_is_login() && 'on' == $acting_opts['quickpay'] && empty($quick_member) ) {
					//共通部
					$param_list['MerchantId'] = $acting_opts['merchant_id'];
					$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
					$param_list['TransactionDate'] = $TransactionDate;
					$param_list['MerchantFree1'] = $rand;
					$param_list['MerchantFree2'] = $acting_flg;
					$param_list['MerchantFree3'] = $this->merchantfree3;
					$param_list['TenantId'] = $acting_opts['tenant_id'];
					$param_list['Amount'] = $usces_entries['order']['total_full_price'];
					$param_list['KaiinId'] = $KaiinId;
					$param_list['KaiinPass'] = $KaiinPass;
					if( usces_have_continue_charge() ) {
						$chargingday = $usces->getItemChargingDay( $cart[0]['post_id'] );
						if( 99 == $chargingday ) {//受注日課金
							$param_list['OperateId'] = $acting_opts['operateid'];
						} else {
							$param_list['OperateId'] = '1Auth';
						}
						$param_list['PayType'] = '01';
					} else {
						$param_list['OperateId'] = $acting_opts['operateid'];
						//$param_list['PayType'] = $post_data['paytype'];
						$param_list['PayType'] = '01';
					}
					$params['send_url'] = $acting_opts['send_url'];
					$params['param_list'] = $param_list;
					//e-SCOTT 決済
					$response_data = $this->connection( $params );
					$response_data['acting'] = $acting;

					if( 'OK' == $response_data['ResponseCd'] ) {
						$res = $usces->order_processing( $response_data );
						if( 'ordercompletion' == $res ) {
							$response_data['acting_return'] = 1;
							$response_data['result'] = 1;
							$response_data['nonce'] = wp_create_nonce( 'welcart_transaction' );
							wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
						} else {
							$logdata = array_merge( $usces_entries['order'], $response_data );
							$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$logdata );
							usces_save_order_acting_error( $log );
							wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
						}
					} else {
						$responsecd = explode( '|', $response_data['ResponseCd'] );
						foreach( (array)$responsecd as $cd ) {
							$response_data[$cd] = $this->response_message( $cd );
						}
						$logdata = array_merge( $params, $response_data );
						$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$logdata );
						usces_save_order_acting_error( $log );
						wp_redirect( add_query_arg( array( 'acting'=>'welcart_card', 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
					}

				} else {
					$home_url = str_replace( 'http://', 'https://', home_url('/') );
					$redirecturl = $home_url.'?page_id='.USCES_CART_NUMBER;
					$posturl = $home_url;

					if( !empty($member['ID']) && 'on' == $acting_opts['quickpay'] && ( 'add' == $quick_member || 'update' == $quick_member ) ) {
						$data_list = array();
						$data_list['MerchantPass'] = $acting_opts['merchant_pass'];
						$data_list['TransactionDate'] = $TransactionDate;
						$data_list['MerchantFree1'] = $rand;
						$data_list['MerchantFree2'] = $acting_flg;
						$data_list['MerchantFree3'] = $this->merchantfree3;
						$data_list['TenantId'] = $acting_opts['tenant_id'];
						if( 'add' == $quick_member ) {
							$data_list['OperateId'] = '4MemAdd';
							$data_list['KaiinId'] = $this->make_kaiin_id( $member['ID'] );
							$data_list['KaiinPass'] = $this->make_kaiin_pass();
						} elseif( 'update' == $quick_member ) {
							$data_list['OperateId'] = '4MemChg';
							$data_list['KaiinId'] = $KaiinId;
							$data_list['KaiinPass'] = $KaiinPass;
						}
						$data_list['ProcNo'] = '0000000';
						$data_list['RedirectUrl'] = $redirecturl;
						//$data_list['PostUrl'] = $posturl;
						$data_query = http_build_query( $data_list );
						$encryptvalue = openssl_encrypt( $data_query, 'aes-128-cbc', $acting_opts['key_aes'], false, $acting_opts['key_iv'] );

						$param_list['MerchantId'] = $acting_opts['merchant_id'];
						$param_list['EncryptValue'] = urlencode($encryptvalue);
						wp_redirect( add_query_arg( $param_list, $acting_opts['send_url_link'] ) );
					} else {
						if( usces_have_continue_charge() ) {
							$chargingday = $usces->getItemChargingDay( $cart[0]['post_id'] );
							if( 99 == $chargingday ) {//受注日課金
								$OperateId = $acting_opts['operateid'];
							} else {
								$OperateId = '1Auth';
							}
						} else {
							$OperateId = $acting_opts['operateid'];
						}

						$data_list = array();
						$data_list['OperateId'] = $OperateId;
						$data_list['MerchantPass'] = $acting_opts['merchant_pass'];
						$data_list['TransactionDate'] = $TransactionDate;
						$data_list['MerchantFree1'] = $rand;
						$data_list['MerchantFree2'] = $acting_flg;
						$data_list['MerchantFree3'] = $this->merchantfree3;
						$data_list['TenantId'] = $acting_opts['tenant_id'];
						if( 'on' == $acting_opts['quickpay'] && !empty($KaiinId) && !empty($KaiinPass) ) {
							$data_list['KaiinId'] = $KaiinId;
							$data_list['KaiinPass'] = $KaiinPass;
						}
						$data_list['PayType'] = '01';
						$data_list['Amount'] = $usces_entries['order']['total_full_price'];
						$data_list['ProcNo'] = '0000000';
						$data_list['RedirectUrl'] = $redirecturl;
						//$data_list['PostUrl'] = $posturl;
						$data_query = http_build_query( $data_list );
						$encryptvalue = openssl_encrypt( $data_query, 'aes-128-cbc', $acting_opts['key_aes'], false, $acting_opts['key_iv'] );

						$param_list['MerchantId'] = $acting_opts['merchant_id'];
						$param_list['EncryptValue'] = urlencode($encryptvalue);
						wp_redirect( add_query_arg( $param_list, $acting_opts['send_url_link'] ) );
					}
				}
			}
			exit();
			break;

		case 'acting_welcart_conv':
			$acting = 'welcart_conv';
			$param_list = array();
			$params = array();

			$item_name = mb_convert_kana($usces->getItemName($cart[0]['post_id']), 'ASK', 'UTF-8');
			if( 1 < count($cart) ) {
				if( 11 < mb_strlen($item_name.__(' etc.','usces'), 'UTF-8') ) {
					$item_name = mb_substr($item_name, 0, 10, 'UTF-8').__(' etc.','usces');
				}
			} else {
				if( 11 < mb_strlen($item_name, 'UTF-8') ) {
					$item_name = mb_substr($item_name, 0, 10, 'UTF-8').__('...','usces');
				}
			}
			$paylimit = date_i18n( 'Ymd', current_time('timestamp')+(86400*$acting_opts['conv_limit']) ).'2359';

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $rand;
			$param_list['MerchantFree2'] = $acting_flg;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$param_list['Amount'] = $usces_entries['order']['total_full_price'];
			$param_list['OperateId'] = '2Add';
			$param_list['PayLimit'] = urlencode( $paylimit );
			$param_list['NameKanji'] = urlencode( $usces_entries['customer']['name1'].$usces_entries['customer']['name2'] );
			$param_list['NameKana'] = ( !empty($usces_entries['customer']['name3']) ) ? urlencode( $usces_entries['customer']['name3'].$usces_entries['customer']['name4'] ) : $param_list['NameKanji'];
			$param_list['TelNo'] = urlencode( $usces_entries['customer']['tel'] );
			$param_list['ShouhinName'] = urlencode( $item_name );
			$param_list['Comment'] = urlencode( __('Thank you for using.','usces') );
			$param_list['ReturnURL'] = urlencode( home_url('/') );
			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = $param_list;
			//e-SCOTT オンライン収納代行データ登録
			$response_data = $this->connection( $params );
			$response_data['acting'] = $acting;
			$response_data['PayLimit'] = $paylimit;
			$response_data['Amount'] = $param_list['Amount'];

			if( 'OK' == $response_data['ResponseCd'] ) {
				$FreeArea = trim($response_data['FreeArea']);
				$url = add_query_arg( array( 'code'=>$FreeArea, 'rkbn'=>1 ), $acting_opts['redirect_url_conv'] );
				$res = $usces->order_processing( $response_data );
				if( 'ordercompletion' == $res ) {
					if( isset($response_data['MerchantFree1']) ) {
						usces_ordered_acting_data( $response_data['MerchantFree1'] );
					}
					$usces->cart->clear_cart();
					header( 'location:'.$url );
					exit();
				} else {
					$response_data['acting_return'] = 0;
					$response_data['result'] = 0;
					unset( $response_data['CardNo'] );
					unset( $response_data['CardExp'] );
					$logdata = array_merge( $usces_entries['order'], $response_data );
					$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$logdata );
					usces_save_order_acting_error( $log );
					wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
				}
			} else {
				$response_data['acting_return'] = 0;
				$response_data['result'] = 0;
				unset( $response_data['CardNo'] );
				unset( $response_data['CardExp'] );
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$response_data[$cd] = $this->response_message( $cd );
				}
				$logdata = array_merge( $params, $response_data );
				$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$logdata );
				usces_save_order_acting_error( $log );
				wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
			}
			exit();
			break;

		//case 'acting_welcart_atodene':
		//	$acting = 'welcart_atodene';
		//	break;
		}
	}

	/**********************************************
	* usces_filter_check_acting_return_results
	* 決済完了ページ制御
	* @param  $results
	* @return array $results
	***********************************************/
	public function acting_return( $results ) {

		if( !in_array( 'acting_'.$results['acting'], $this->pay_method ) ) {
			return $results;
		}

		if( isset($results['acting_return']) && $results['acting_return'] != 1 ) {
			return $results;
		}

		$results['reg_order'] = false;

		usces_log('[WelcartPay] results : '.print_r($results, true), 'acting_transaction.log');
		if( !isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'welcart_transaction') ) {
			wp_redirect( home_url() );
			exit();
		}

		return $results;
	}

	/**********************************************
	* usces_filter_confirm_inform
	* 内容確認ページ Purchase Button
	* @param  $html $payments $acting_flg $rand $purchase_disabled
	* @return str $html
	***********************************************/
	public function confirm_inform( $html, $payments, $acting_flg, $rand, $purchase_disabled ) {
		global $usces;

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return $html;
		}

		$usces_entries = $usces->cart->get_entry();
		if( !$usces_entries['order']['total_full_price'] ) {
			return $html;
		}

		if( 'acting_welcart_card' == $acting_flg ) {
			$acting_opts = $this->get_acting_settings();
			if( 'on' == $acting_opts['card_activate'] ) {
				$cardlast4 = ( isset($_POST['cardlast4']) ) ? $_POST['cardlast4'] : '';
				$quick_member = ( isset($_POST['quick_member']) ) ? $_POST['quick_member'] : '';
				$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
					<input type="hidden" name="cardno" value="'.trim($_POST['cardno']).'">
					<input type="hidden" name="cardlast4" value="'.trim($cardlast4).'">';
				if( 'on' == $acting_opts['seccd'] ) {
					$seccd = ( isset($_POST['seccd']) ) ? $_POST['seccd'] : '';
					$html .= '
					<input type="hidden" name="seccd" value="'.trim($seccd).'">';
				}
				$html .= '
					<input type="hidden" name="expyy" value="'.trim($_POST['expyy']).'">
					<input type="hidden" name="expmm" value="'.trim($_POST['expmm']).'">
					<input type="hidden" name="paytype" value="'.$usces_entries['order']['paytype'].'">
					<input type="hidden" name="rand" value="'.$rand.'">
					<input type="hidden" name="quick_member" value="'.$quick_member.'">
					<div class="send">
						<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__('Back','usces').'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
						<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.__('Checkout','usces').'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
					</div>
					<input type="hidden" name="_nonce" value="'.wp_create_nonce($acting_flg).'">';
				if( isset($_POST['card_change']) ) {
					$html .= '
					<input type="hidden" name="card_change" value="1">';
				}

			} elseif( 'link' == $acting_opts['card_activate'] ) {
				$quick_member = ( isset($_POST['quick_member']) ) ? $_POST['quick_member'] : '';
				$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
					<input type="hidden" name="quick_member" value="'.$quick_member.'">
					<input type="hidden" name="rand" value="'.$rand.'">
					<div class="send">
						<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__('Back','usces').'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
						<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.__('Checkout','usces').'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
					</div>
					<input type="hidden" name="_nonce" value="'.wp_create_nonce($acting_flg).'">';
			}

		} elseif( 'acting_welcart_conv' == $acting_flg ) {
			$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
				<input type="hidden" name="rand" value="'.$rand.'">
				<div class="send">
					<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__('Back','usces').'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
					<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.__('Checkout','usces').'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
				</div>
				<input type="hidden" name="_nonce" value="'.wp_create_nonce($acting_flg).'">';
		}
		return $html;
	}

	/**********************************************
	* usces_action_confirm_page_point_inform
	* 内容確認ページ Point form
	* @param  -
	* @return -
	* @echo point_inform()
	***********************************************/
	public function e_point_inform() {

		$html = $this->point_inform( '' );
		echo $html;
	}

	/**********************************************
	* usces_filter_confirm_point_inform
	* 内容確認ページ Point form
	* @param  $html
	* @return str $html
	***********************************************/
	public function point_inform( $html ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$usces_entries = $usces->cart->get_entry();
		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		$acting_flg = $payment['settlement'];
		if( 'acting_welcart_card' != $acting_flg ) {
			return $html;
		}

		if( 'on' == $acting_opts['card_activate'] ) {
			$cardlast4 = ( isset($_POST['cardlast4']) ) ? $_POST['cardlast4'] : '';
			$html .= '
			<input type="hidden" name="cardno" value="'.$_POST['cardno'].'">
			<input type="hidden" name="cardlast4" value="'.$cardlast4.'">';
			if( 'on' == $acting_opts['seccd'] ) {
				$seccd = ( isset($_POST['seccd']) ) ? $_POST['seccd'] : '';
				$html .= '
				<input type="hidden" name="seccd" value="'.$seccd.'">';
			}
			$html .= '
			<input type="hidden" name="expyy" value="'.$_POST['expyy'].'">
			<input type="hidden" name="expmm" value="'.$_POST['expmm'].'">
			<input type="hidden" name="offer[paytype]" value="'.$usces_entries['order']['paytype'].'">';

		//} elseif( 'link' == $acting_opts['card_activate'] ) {
			$quick_member = ( isset($_POST['quick_member']) ) ? $_POST['quick_member'] : '';
			$html .= '
			<input type="hidden" name="quick_member" value="'.$quick_member.'">';
		}
		return $html;
	}

	/**********************************************
	* usces_action_admin_settlement_update
	* 決済オプション登録・更新
	* @param  -
	* @return -
	***********************************************/
	public function settlement_update() {
		global $usces;

		if( 'welcart' != $_POST['acting'] ) {
			return;
		}

		$this->error_mes = '';
		$options = get_option( 'usces' );
		$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );

		unset( $options['acting_settings']['welcart'] );
		$options['acting_settings']['welcart']['merchant_id'] = ( isset($_POST['merchant_id']) ) ? $_POST['merchant_id'] : '';
		$options['acting_settings']['welcart']['merchant_pass'] = ( isset($_POST['merchant_pass']) ) ? $_POST['merchant_pass'] : '';
		$options['acting_settings']['welcart']['tenant_id'] = ( isset($_POST['tenant_id']) ) ? $_POST['tenant_id'] : '';
		$options['acting_settings']['welcart']['auth_key'] = ( isset($_POST['auth_key']) ) ? $_POST['auth_key'] : '';
		$options['acting_settings']['welcart']['ope'] = ( isset($_POST['ope']) ) ? $_POST['ope'] : '';
		$options['acting_settings']['welcart']['card_activate'] = ( isset($_POST['card_activate']) ) ? $_POST['card_activate'] : '';
		$options['acting_settings']['welcart']['foreign_activate'] = ( isset($_POST['foreign_activate']) ) ? $_POST['foreign_activate'] : '';
		$options['acting_settings']['welcart']['seccd'] = ( isset($_POST['seccd']) ) ? $_POST['seccd'] : 'on';
		$options['acting_settings']['welcart']['quickpay'] = ( isset($_POST['quickpay']) ) ? $_POST['quickpay'] : '';
		$options['acting_settings']['welcart']['operateid'] = ( isset($_POST['operateid']) ) ? $_POST['operateid'] : '1Auth';
		$options['acting_settings']['welcart']['operateid_dlseller'] = ( isset($_POST['operateid_dlseller']) ) ? $_POST['operateid_dlseller'] : '1Auth';
		$options['acting_settings']['welcart']['auto_settlement_mail'] = ( isset($_POST['auto_settlement_mail']) ) ? $_POST['auto_settlement_mail'] : '';
		$options['acting_settings']['welcart']['howtopay'] = ( isset($_POST['howtopay']) ) ? $_POST['howtopay'] : '';
		$options['acting_settings']['welcart']['conv_activate'] = ( isset($_POST['conv_activate']) ) ? $_POST['conv_activate'] : '';
		$options['acting_settings']['welcart']['conv_limit'] = ( !empty($_POST['conv_limit']) ) ? $_POST['conv_limit'] : '7';
		$options['acting_settings']['welcart']['conv_fee_type'] = ( isset($_POST['conv_fee_type']) ) ? $_POST['conv_fee_type'] : '';
		$options['acting_settings']['welcart']['conv_fee'] = ( isset($_POST['conv_fee']) ) ? $_POST['conv_fee'] : '';
		$options['acting_settings']['welcart']['conv_fee_limit_amount'] = ( isset($_POST['conv_fee_limit_amount']) ) ? $_POST['conv_fee_limit_amount'] : '';
		$options['acting_settings']['welcart']['conv_fee_first_amount'] = ( isset($_POST['conv_fee_first_amount']) ) ? $_POST['conv_fee_first_amount'] : '';
		$options['acting_settings']['welcart']['conv_fee_first_fee'] = ( isset($_POST['conv_fee_first_fee']) ) ? $_POST['conv_fee_first_fee'] : '';
		$options['acting_settings']['welcart']['conv_fee_amounts'] = ( isset($_POST['conv_fee_amounts']) ) ? explode( '|', $_POST['conv_fee_amounts'] ) : array();
		$options['acting_settings']['welcart']['conv_fee_fees'] = ( isset($_POST['conv_fee_fees']) ) ? explode( '|', $_POST['conv_fee_fees'] ) : array();
		$options['acting_settings']['welcart']['conv_fee_end_fee'] = ( isset($_POST['conv_fee_end_fee']) ) ? $_POST['conv_fee_end_fee'] : '';
		$options['acting_settings']['welcart']['atodene_activate'] = ( isset($_POST['atodene_activate']) ) ? $_POST['atodene_activate'] : '';
		$options['acting_settings']['welcart']['atodene_byitem'] = ( isset($_POST['atodene_byitem']) ) ? $_POST['atodene_byitem'] : 'off';
		$options['acting_settings']['welcart']['atodene_billing_method'] = ( isset($_POST['atodene_billing_method']) ) ? $_POST['atodene_billing_method'] : '2';
		$options['acting_settings']['welcart']['atodene_fee_type'] = ( isset($_POST['atodene_fee_type']) ) ? $_POST['atodene_fee_type'] : '';
		$options['acting_settings']['welcart']['atodene_fee'] = ( isset($_POST['atodene_fee']) ) ? $_POST['atodene_fee'] : '';
		$options['acting_settings']['welcart']['atodene_fee_limit_amount'] = ( isset($_POST['atodene_fee_limit_amount']) ) ? $_POST['atodene_fee_limit_amount'] : '';
		$options['acting_settings']['welcart']['atodene_fee_first_amount'] = ( isset($_POST['atodene_fee_first_amount']) ) ? $_POST['atodene_fee_first_amount'] : '';
		$options['acting_settings']['welcart']['atodene_fee_first_fee'] = ( isset($_POST['atodene_fee_first_fee']) ) ? $_POST['atodene_fee_first_fee'] : '';
		$options['acting_settings']['welcart']['atodene_fee_amounts'] = ( isset($_POST['atodene_fee_amounts']) ) ? explode( '|', $_POST['atodene_fee_amounts'] ) : array();
		$options['acting_settings']['welcart']['atodene_fee_fees'] = ( isset($_POST['atodene_fee_fees']) ) ? explode( '|', $_POST['atodene_fee_fees'] ) : array();
		$options['acting_settings']['welcart']['atodene_fee_end_fee'] = ( isset($_POST['atodene_fee_end_fee']) ) ? $_POST['atodene_fee_end_fee'] : '';

		if( WCUtils::is_blank($_POST['merchant_id']) ) {
			$this->error_mes .= __('* Please enter the Merchant ID.','usces').'<br />';
		}
		if( WCUtils::is_blank($_POST['merchant_pass']) ) {
			$this->error_mes .= __('* Please enter the Merchant Password.','usces').'<br />';
		}
		if( WCUtils::is_blank($_POST['tenant_id']) ) {
			$this->error_mes .= __('* Please enter the Tenant ID.','usces').'<br />';
		}
		if( WCUtils::is_blank($_POST['auth_key']) ) {
			$this->error_mes .= __('* Please enter the Settlement auth key.','usces').'<br />';
		} else {
			$auth_key = md5($_POST['auth_key']);
			$welcartpay_keys = get_option( 'usces_welcartpay_keys' );
			if( !in_array( $auth_key, $welcartpay_keys ) ) {
				$this->error_mes .= __('* The Settlement auth key is incorrect.','usces').'<br />';
			}
		}
		if( WCUtils::is_blank($_POST['ope']) ) {
			$this->error_mes .= __('* Please select the operating environment.','usces').'<br />';
		}
		if( 'on' == $options['acting_settings']['welcart']['card_activate'] ) {
			$unavailable_activate = false;
			foreach( $payment_method as $key => $payment ) {
				foreach( (array)$this->unavailable_method as $unavailable ) {
					if( $unavailable == $key && 'activate' == $payment['use'] ) {
						$unavailable_activate = true;
						break;
					}
				}
			}
			if( $unavailable_activate ) {
				$this->error_mes .= __('* Settlement that can not be used together is activated.','usces').'<br />';
			}
		}

		if( WCUtils::is_blank($this->error_mes) ) {
			$usces->action_status = 'success';
			$usces->action_message = __('options are updated','usces');
			$options['acting_settings']['welcart']['activate'] = 'on';
			if( 'public' == $options['acting_settings']['welcart']['ope'] ) {
				$options['acting_settings']['welcart']['send_url'] = 'https://www.e-scott.jp/online/aut/OAUT002.do';
				$options['acting_settings']['welcart']['send_url_member'] = 'https://www.e-scott.jp/online/crp/OCRP005.do';
				$options['acting_settings']['welcart']['send_url_conv'] = 'https://www.e-scott.jp/online/cnv/OCNV005.do';
				$options['acting_settings']['welcart']['redirect_url_conv'] = 'https://link.kessai.info/JLP/JLPcon';
				$options['acting_settings']['welcart']['send_url_link'] = 'https://www.e-scott.jp/euser/snp/SSNP005ReferStart.do';
				$options['acting_settings']['welcart']['key_aes'] = $this->key_aes;
				$options['acting_settings']['welcart']['key_iv'] = $this->key_iv;
			} else {
				$options['acting_settings']['welcart']['send_url'] = 'https://www.test.e-scott.jp/online/aut/OAUT002.do';
				$options['acting_settings']['welcart']['send_url_member'] = 'https://www.test.e-scott.jp/online/crp/OCRP005.do';
				$options['acting_settings']['welcart']['send_url_conv'] = 'https://www.test.e-scott.jp/online/cnv/OCNV005.do';
				$options['acting_settings']['welcart']['redirect_url_conv'] = 'https://link.kessai.info/JLPCT/JLPcon';
				$options['acting_settings']['welcart']['send_url_link'] = 'https://www.test.e-scott.jp/euser/snp/SSNP005ReferStart.do';
				$options['acting_settings']['welcart']['key_aes'] = $this->key_aes;
				$options['acting_settings']['welcart']['key_iv'] = $this->key_iv;
				$options['acting_settings']['welcart']['tenant_id'] = '0001';
			}
			if( 'on' == $options['acting_settings']['welcart']['card_activate'] || 'link' == $options['acting_settings']['welcart']['card_activate'] ) {
				$usces->payment_structure['acting_welcart_card'] = __('Credit card transaction (WelcartPay)','usces');
			} else {
				unset($usces->payment_structure['acting_welcart_card']);
			}
			if( 'on' == $options['acting_settings']['welcart']['conv_activate'] ) {
				$usces->payment_structure['acting_welcart_conv'] = __('Online storage agency (WelcartPay)','usces');
			} else {
				unset($usces->payment_structure['acting_welcart_conv']);
			}
			if( 'on' == $options['acting_settings']['welcart']['atodene_activate'] ) {
				$usces->payment_structure['acting_welcart_atodene'] = __('Postpay settlement (WelcartPay/ATODENE)','usces');
			} else {
				unset($usces->payment_structure['acting_welcart_atodene']);
			}
		} else {
			$usces->action_status = 'error';
			$usces->action_message = __('Data have deficiency.','usces');
			$options['acting_settings']['welcart']['activate'] = 'off';
			unset( $usces->payment_structure['acting_welcart_card'] );
			unset( $usces->payment_structure['acting_welcart_conv'] );
			unset( $usces->payment_structure['acting_welcart_atodene'] );
		}
		ksort( $usces->payment_structure );
		update_option( 'usces', $options );
		update_option( 'usces_payment_structure', $usces->payment_structure );
	}

	/**********************************************
	* usces_action_settlement_tab_title
	* クレジット決済設定画面タブ
	* @param  -
	* @return -
	* @echo   html
	***********************************************/
	public function settlement_tab_title() {

		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'welcart', (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_welcart">'.__('WelcartPay','usces').'</a></li>';
		}
	}

	/**********************************************
	* usces_action_settlement_tab_body
	* クレジット決済設定画面フォーム
	* @param  -
	* @return -
	* @echo   html
	***********************************************/
	public function settlement_tab_body() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'welcart', (array)$settlement_selected ) ):
?>
	<div id="uscestabs_welcart">
	<div class="settlement_service"><span class="service_title"><?php _e('WelcartPay','usces'); ?></span></div>

	<?php if( isset($_POST['acting']) && 'welcart' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset($acting_opts['activate']) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message"><?php _e('Test thoroughly before use.','usces'); ?></div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="welcart_form" id="welcart_form">
		<table class="settle_table">
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_merchant_id_welcart');"><?php _e('Merchant ID','usces');//マーチャントID ?></a></th>
				<td colspan="4"><input name="merchant_id" type="text" id="merchant_id_welcart" value="<?php echo $acting_opts['merchant_id']; ?>" size="20" /></td>
				<td><div id="ex_merchant_id_welcart" class="explanation"><?php _e('Merchant ID (single-byte numbers only) issued from e-SCOTT.','usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_merchant_pass_welcart');"><?php _e('Merchant Password','usces');//マーチャントパスワード ?></a></th>
				<td colspan="4"><input name="merchant_pass" type="text" id="merchant_pass_welcart" value="<?php echo $acting_opts['merchant_pass']; ?>" size="20" /></td>
				<td><div id="ex_merchant_pass_welcart" class="explanation"><?php _e('Merchant Password (single-byte alphanumeric characters only) issued from e-SCOTT.','usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_tenant_id_welcart');"><?php _e('Tenant ID','usces');//店舗コード ?></a></th>
				<td colspan="4"><input name="tenant_id" type="text" id="tenant_id_welcart" value="<?php echo $acting_opts['tenant_id']; ?>" size="20" /></td>
				<td><div id="ex_tenant_id_welcart" class="explanation"><?php _e('Tenant ID issued from e-SCOTT.<br />If you have only one shop to contract, enter 0001.','usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_auth_key_welcart');"><?php _e('Settlement auth key','usces');//決済認証キー ?></a></th>
				<td colspan="4"><input name="auth_key" type="text" id="auth_key_welcart" value="<?php echo $acting_opts['auth_key']; ?>" size="20" /></td>
				<td><div id="ex_auth_key_welcart" class="explanation"><?php _e('Settlement auth key (single-byte numbers only) issued from e-SCOTT.','usces'); ?></div></td>
			</tr>
			<tr>
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_ope_welcart');"><?php _e('Operation Environment','usces');//動作環境 ?></a></th>
				<td><input name="ope" type="radio" id="ope_welcart_1" value="test"<?php if( $acting_opts['ope'] == 'test' ) echo ' checked="checked"'; ?> /></td><td><label for="ope_welcart_1"><?php _e('Testing environment','usces'); ?></label></td>
				<td><input name="ope" type="radio" id="ope_welcart_2" value="public"<?php if( $acting_opts['ope'] == 'public' ) echo ' checked="checked"'; ?> /></td><td><label for="ope_welcart_2"><?php _e('Production environment','usces'); ?></label></td>
				<td><div id="ex_ope_welcart" class="explanation"><?php _e('Switch the operating environment.','usces'); ?></div></td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><?php _e('Credit card settlement','usces');//クレジットカード決済 ?></th>
				<td><input name="card_activate" type="radio" class="card_activate_welcart" id="card_activate_welcart_1" value="on"<?php if( $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="card_activate_welcart_1"><?php _e('Use with embedded type','usces'); ?></label></td>
				<td><input name="card_activate" type="radio" class="card_activate_welcart" id="card_activate_welcart_2" value="link"<?php if( $acting_opts['card_activate'] == 'link' ) echo ' checked="checked"'; ?> /></td><td><label for="card_activate_welcart_2"><?php _e('Use with external link type','usces'); ?></label></td>
				<td><input name="card_activate" type="radio" class="card_activate_welcart" id="card_activate_welcart_0" value="off"<?php if( $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="card_activate_welcart_0"><?php _e('Do not Use','usces'); ?></label></td>
				<td></td>
			</tr>
			<!--<tr class="card_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_foreign_activate_welcart');"><?php _e('Foreign exchange settlement','usces');//外貨決済 ?></a></th>
				<td><input name="foreign_activate" type="radio" id="foreign_activate_welcart_1" value="on"<?php //if( $acting_opts['foreign_activate'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="foreign_activate_welcart_1"><?php _e('Use','usces'); ?></label></td>
				<td><input name="foreign_activate" type="radio" id="foreign_activate_welcart_2" value="off"<?php //if( $acting_opts['foreign_activate'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="foreign_activate_welcart_2"><?php _e('Do not Use','usces'); ?></label></td>
				<td></td><td></td>
				<td><div id="ex_foreign_activate_welcart" class="explanation"><?php _e('Foreign exchange settlement, only two VISA and MasterCard card companies are available.<br />If switching to Yen card is done during operation, the member information registered in e-SCOTT will be invalid.','usces'); ?></div></td>
			</tr>-->
			<tr class="card_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_seccd_welcart');"><?php _e('Security code <br /> (authentication assist)','usces');//セキュリティコード ?></a></th>
				<td><input name="seccd" type="radio" id="seccd_welcart_1" value="on"<?php if( $acting_opts['seccd'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="seccd_welcart_1"><?php _e('Use','usces'); ?></label></td>
				<td><input name="seccd" type="radio" id="seccd_welcart_0" value="off"<?php if( $acting_opts['seccd'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="seccd_welcart_0"><?php _e('Do not Use','usces'); ?></label></td>
				<td></td><td></td>
				<td><div id="ex_seccd_welcart" class="explanation"><?php _e("Use 'Security code' of authentication assist matching. If you decide not to use, please also set 'Do not verify matching' on the e-SCOTT management screen.",'usces'); ?></div></td>
			</tr>
			<tr class="card_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_quickpay_welcart');"><?php _e('Quick payment','usces');//クイック決済 ?></a></th>
				<td><input name="quickpay" type="radio" id="quickpay_welcart_1" value="on"<?php if( $acting_opts['quickpay'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="quickpay_welcart_1"><?php _e('Use','usces'); ?></label></td>
				<td><input name="quickpay" type="radio" id="quickpay_welcart_0" value="off"<?php if( $acting_opts['quickpay'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="quickpay_welcart_0"><?php _e('Do not Use','usces'); ?></label></td>
				<td></td><td></td>
				<td><div id="ex_quickpay_welcart" class="explanation"><?php _e("When using automatic continuing charging (required WCEX DLSeller) or subscription (required WCEX Auto Delivery), please make 'Quick payment' of 'Use'.",'usces'); ?></div></td>
			</tr>
			<tr class="card_welcart">
				<th><?php _e('Processing classification','usces');//処理区分 ?></th>
				<td><input name="operateid" type="radio" id="operateid_welcart_1" value="1Auth"<?php if( $acting_opts['operateid'] == '1Auth' ) echo ' checked="checked"'; ?> /></td><td><label for="operateid_welcart_1"><?php _e('Credit','usces');//与信 ?></label></td>
				<td><input name="operateid" type="radio" id="operateid_welcart_2" value="1Gathering"<?php if( $acting_opts['operateid'] == '1Gathering' ) echo ' checked="checked"'; ?> /></td><td><label for="operateid_welcart_2"><?php _e('Credit sales','usces');//与信売上計上 ?></label></td>
				<td></td><td></td><td></td>
			</tr>
			<?php if( defined('WCEX_DLSELLER') ): ?>
			<tr class="card_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_operateid_dlseller_welcart');"><?php _e('Automatic Continuing Charging Processing Classification','usces');//自動継続課金処理区分 ?></a></th>
				<td><input name="operateid_dlseller" type="radio" id="operateid_dlseller_welcart_1" value="1Auth"<?php if( $acting_opts['operateid_dlseller'] == '1Auth' ) echo ' checked="checked"'; ?> /></td><td><label for="operateid_dlseller_welcart_1"><?php _e('Credit','usces');//与信 ?></label></td>
				<td><input name="operateid_dlseller" type="radio" id="operateid_dlseller_welcart_2" value="1Gathering"<?php if( $acting_opts['operateid_dlseller'] == '1Gathering' ) echo ' checked="checked"'; ?> /></td><td><label for="operateid_dlseller_welcart_2"><?php _e('Credit sales','usces');//与信売上計上 ?></label></td>
				<td></td><td></td>
				<td><div id="ex_operateid_dlseller_welcart" class="explanation"><?php _e('Processing classification when automatic continuing charging (required WCEX DLSeller).','usces'); ?></div></td>
			</tr>
			<tr class="card_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_auto_settlement_mail_welcart');"><?php _e('Automatic Continuing Charging Completion Mail','usces');//自動継続課金完了メール ?></a></th>
				<td><input name="auto_settlement_mail" type="radio" id="auto_settlement_mail_welcart_1" value="on"<?php if( $acting_opts['auto_settlement_mail'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="auto_settlement_mail_welcart_1"><?php _e("Send",'usces'); ?></label></td>
				<td><input name="auto_settlement_mail" type="radio" id="auto_settlement_mail_welcart_0" value="off"<?php if( $acting_opts['auto_settlement_mail'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="auto_settlement_mail_welcart_0"><?php _e("Don't send",'usces'); ?></label></td>
				<td></td><td></td>
				<td><div id="ex_auto_settlement_mail_welcart" class="explanation"><?php _e('Send billing completion mail to the member on which automatic continuing charging processing (required WCEX DLSeller) is executed.','usces'); ?></div></td>
			</tr>
			<?php endif; ?>
			<tr class="card_howtopay_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_howtopay_welcart');"><?php _e('Number of payments','usces');//支払い回数 ?></a></th>
				<td><input name="howtopay" type="radio" id="howtopay_welcart_1" value="1"<?php if( $acting_opts['howtopay'] == '1' ) echo ' checked="checked"'; ?> /></td><td><label for="howtopay_welcart_1"><?php _e('Lump-sum payment only','usces');//一括払いのみ ?></label></td>
				<td><input name="howtopay" type="radio" id="howtopay_welcart_2" value="2"<?php if( $acting_opts['howtopay'] == '2' ) echo ' checked="checked"'; ?> /></td><td><label for="howtopay_welcart_2"><?php _e('Activate installment payment','usces');//分割払いを有効にする ?></label></td>
				<td><input name="howtopay" type="radio" id="howtopay_welcart_3" value="3"<?php if( $acting_opts['howtopay'] == '3' ) echo ' checked="checked"'; ?> /></td><td><label for="howtopay_welcart_3"><?php _e('Activate installment payments and bonus payments','usces');//分割払いとボーナス払いを有効にする ?></label></td>
				<td><div id="ex_howtopay_welcart" class="explanation"><?php _e('It can be selected when using in embedded type.','usces'); ?></div></td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><?php _e('Online storage agency','usces');//オンライン収納代行 ?></th>
				<td><input name="conv_activate" type="radio" class="conv_activate_welcart" id="conv_activate_welcart_1" value="on"<?php if( $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="conv_activate_welcart_1"><?php _e('Use','usces'); ?></label></td>
				<td><input name="conv_activate" type="radio" class="conv_activate_welcart" id="conv_activate_welcart_0" value="off"<?php if( $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="conv_activate_welcart_0"><?php _e('Do not Use','usces'); ?></label></td>
				<td></td>
			</tr>
			<tr class="conv_welcart">
				<th><?php _e('Payment due days','usces');//支払期限日数 ?></th>
				<td colspan="4"><input name="conv_limit" type="text" id="conv_limit" value="<?php echo $acting_opts['conv_limit']; ?>" size="5" /><?php _e('days','usces'); ?></td>
				<td></td>
			</tr>
			<tr class="conv_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_conv_fee_welcart');"><?php _e('Fee','usces');//手数料 ?></a></th>
				<td colspan="2" id="conv_fee_type_field"><?php echo $this->get_fee_name( $acting_opts['conv_fee_type'] ); ?></td><td colspan="2"><input type="button" class="button" value="<?php _e('Detailed setting','usces'); ?>" id="conv_fee_setting" /></td>
				<td><div id="ex_conv_fee_welcart" class="explanation"><?php _e('Set the online storage agency commission and settlement upper limit. Leave it blank if you do not need it.','usces'); ?></div></td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><?php _e('Postpay settlement (ATODENE)','usces');//後払い決済 ?></th>
				<td><input name="atodene_activate" type="radio" class="atodene_activate_welcart" id="atodene_activate_welcart_1" value="on"<?php if( $acting_opts['atodene_activate'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="atodene_activate_welcart_1"><?php _e('Use','usces'); ?></label></td>
				<td><input name="atodene_activate" type="radio" class="atodene_activate_welcart" id="atodene_activate_welcart_0" value="off"<?php if( $acting_opts['atodene_activate'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="atodene_activate_welcart_0"><?php _e('Do not Use','usces'); ?></label></td>
				<td></td>
			</tr>
			<tr class="atodene_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_atodene_byitem_welcart');"><?php _e('Possibility of each items','usces');//商品ごとの可否 ?></a></th>
				<td><input name="atodene_byitem" type="radio" id="atodene_byitem_welcart_1" value="on"<?php if( $acting_opts['atodene_byitem'] == 'on' ) echo ' checked="checked"'; ?> /></td><td><label for="atodene_byitem_welcart_1"><?php _e("Enabled",'usces'); ?></label></td>
				<td><input name="atodene_byitem" type="radio" id="atodene_byitem_welcart_0" value="off"<?php if( $acting_opts['atodene_byitem'] == 'off' ) echo ' checked="checked"'; ?> /></td><td><label for="atodene_byitem_welcart_0"><?php _e("Disabled",'usces'); ?></label></td>
				<td><div id="ex_atodene_byitem_welcart" class="explanation"><?php _e('It is effective when setting possibility of each items. Invalid when not distinguished in particular.<br />If enabled, a selection field will be added to determine whether postpay settlement is possible on the product registration screen. If there is a product in the cart that can not be postpaid settlement, we exclude postpaid settlement from the payment method options.<br />In addition, availability data is added to the product CSV as a custom field (welcartpay_atodene).','usces'); ?></div></td>
			</tr>
			<tr class="atodene_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_atodene_billing_method_welcart');"><?php _e('Invoice delivery method','usces');//請求書送付方法 ?></a></th>
				<td><input name="atodene_billing_method" type="radio" id="atodene_billing_method_welcart_2" value="2"<?php if( $acting_opts['atodene_billing_method'] == '2' ) echo ' checked="checked"'; ?> /></td><td><label for="atodene_billing_method_welcart_2"><?php _e('Separate shipment','usces');//別送 ?></label></td>
				<td><input name="atodene_billing_method" type="radio" id="atodene_billing_method_welcart_3" value="3"<?php if( $acting_opts['atodene_billing_method'] == '3' ) echo ' checked="checked"'; ?> /></td><td><label for="atodene_billing_method_welcart_3"><?php _e('Include shipment','usces');//同梱 ?></label></td>
				<td><div id="ex_atodene_billing_method_welcart" class="explanation"><?php _e('How to send invoices from ATODENE.','usces'); ?></div></td>
			</tr>
			<tr class="atodene_welcart">
				<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_atodene_fee_welcart');"><?php _e('Fee','usces'); ?></a></th>
				<td colspan="2" id="atodene_fee_type_field"><?php echo $this->get_fee_name( $acting_opts['atodene_fee_type'] ); ?></td><td colspan="2"><input type="button" class="button" value="<?php _e('Detailed setting','usces'); ?>" id="atodene_fee_setting" /></td>
				<td><div id="ex_atodene_fee_welcart" class="explanation"><?php _e('Set up postpaid settlement fee and maximum settlement amount. Leave it blank if you do not need it.','usces'); ?></div></td>
			</tr>
		</table>
		<input type="hidden" name="acting" value="welcart" />
		<input type="hidden" name="conv_fee_type" id="conv_fee_type" value="<?php echo $acting_opts['conv_fee_type']; ?>" />
		<input type="hidden" name="conv_fee" id="conv_fee" value="<?php echo $acting_opts['conv_fee']; ?>" />
		<input type="hidden" name="conv_fee_limit_amount_fix" id="conv_fee_limit_amount_fix" value="<?php echo $acting_opts['conv_fee_limit_amount']; ?>" />
		<input type="hidden" name="conv_fee_first_amount" id="conv_fee_first_amount" value="<?php echo $acting_opts['conv_fee_first_amount']; ?>" />
		<input type="hidden" name="conv_fee_first_fee" id="conv_fee_first_fee" value="<?php echo $acting_opts['conv_fee_first_fee']; ?>" />
		<input type="hidden" name="conv_fee_limit_amount_change" id="conv_fee_limit_amount_change" value="<?php echo $acting_opts['conv_fee_limit_amount']; ?>" />
		<input type="hidden" name="conv_fee_amounts" id="conv_fee_amounts" value="<?php echo implode('|', $acting_opts['conv_fee_amounts']); ?>" />
		<input type="hidden" name="conv_fee_fees" id="conv_fee_fees" value="<?php echo implode('|', $acting_opts['conv_fee_fees']); ?>" />
		<input type="hidden" name="conv_fee_end_fee" id="conv_fee_end_fee" value="<?php echo $acting_opts['conv_fee_end_fee']; ?>" />
		<input type="hidden" name="atodene_fee_type" id="atodene_fee_type" value="<?php echo $acting_opts['atodene_fee_type']; ?>" />
		<input type="hidden" name="atodene_fee" id="atodene_fee" value="<?php echo $acting_opts['atodene_fee']; ?>" />
		<input type="hidden" name="atodene_fee_limit_amount_fix" id="atodene_fee_limit_amount_fix" value="<?php echo $acting_opts['atodene_fee_limit_amount']; ?>" />
		<input type="hidden" name="atodene_fee_first_amount" id="atodene_fee_first_amount" value="<?php echo $acting_opts['atodene_fee_first_amount']; ?>" />
		<input type="hidden" name="atodene_fee_first_fee" id="atodene_fee_first_fee" value="<?php echo $acting_opts['atodene_fee_first_fee']; ?>" />
		<input type="hidden" name="atodene_fee_limit_amount_change" id="atodene_fee_limit_amount_change" value="<?php echo $acting_opts['atodene_fee_limit_amount']; ?>" />
		<input type="hidden" name="atodene_fee_amounts" id="atodene_fee_amounts" value="<?php echo implode('|', $acting_opts['atodene_fee_amounts']); ?>" />
		<input type="hidden" name="atodene_fee_fees" id="atodene_fee_fees" value="<?php echo implode('|', $acting_opts['atodene_fee_fees']); ?>" />
		<input type="hidden" name="atodene_fee_end_fee" id="atodene_fee_end_fee" value="<?php echo $acting_opts['atodene_fee_end_fee']; ?>" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php _e('Update WelcartPay settings','usces'); ?>" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong>WelcartPay based on e-SCOTT</strong></p>
		<a href="http://www.sonypaymentservices.jp/intro/" target="_blank"><?php _e('Details of e-SCOTT Smart is here >>','usces'); ?></a>
		<p>&nbsp;</p>
		<p><?php echo __("'Embedded type' is a settlement system that completes with shop site only, without transitioning to the page of the settlement company.",'usces'); ?><br />
			<?php echo __("Stylish with unified design is possible. However, because we will handle the card number, dedicated SSL is required.",'usces'); ?><br />
			<?php echo __("'External link type' is a settlement system that moves to the page of the settlement company and inputs card information.",'usces'); ?></p>
		<p><?php echo __("In both types, the entered card number will be sent to the e-SCOTT Smart system, so it will not be saved in Welcart.",'usces'); ?></p>
		<p><?php echo __("'WCEX DL Seller' is necessary when using 'automatic continuing charging'.",'usces'); ?><br />
			<?php echo __("'WCEX Auto Delivery' is necessary when using 'subscription'.",'usces'); ?></p>
		<p><?php echo __("In addition, in the production environment, it is SSL communication with only an authorized SSL certificate, so it is necessary to be careful.",'usces'); ?></p>
		<p><?php echo __("The Welcart member account used in the test environment may not be available in the production environment.",'usces'); ?><br />
			<?php echo __("Please make another member registration in the test environment and production environment, or delete the member used in the test environment once and register again in the production environment.",'usces'); ?></p>
	</div>
	</div><!--uscestabs_welcart-->

	<div id="welcartpay_fee_dialog" class="cod_dialog">
		<fieldset>
		<table id="welcartpay_fee_type_table" class="cod_type_table">
			<tr>
				<th><?php _e('Type of the fee','usces'); ?></th>
				<td class="radio"><input name="fee_type" type="radio" id="fee_type_fix" class="fee_type" value="fix" /></td><td><label for="fee_type_fix"><?php _e('Fixation','usces'); ?></label></td>
				<td class="radio"><input name="fee_type" type="radio" id="fee_type_change" class="fee_type" value="change" /></td><td><label for="fee_type_change"><?php _e('Variable','usces'); ?></label></td>
			</tr>
		</table>
		<table id="welcartpay_fee_fix_table" class="cod_fix_table">
			<tr>
				<th><?php _e('Fee','usces'); ?></th>
				<td><input name="fee" type="text" id="fee_fix" class="short_str num" /><?php usces_crcode(); ?></td>
			</tr>
			<tr>
				<th><?php _e('Upper limit','usces'); ?></th>
				<td><input name="fee_limit_amount" type="text" id="fee_limit_amount_fix" class="short_str num" /><?php usces_crcode(); ?></td>
			</tr>
		</table>
		<div id="welcartpay_fee_change_table" class="cod_change_table">
		<input type="button" class="button" id="fee_add_row" value="<?php _e('Add row','usces'); ?>" />
		<input type="button" class="button" id="fee_del_row" value="<?php _e('Delete row','usces'); ?>" />
		<table>
			<thead>
				<tr>
					<th colspan="3"><?php _e('A purchase amount','usces'); ?>(<?php usces_crcode(); ?>)</th>
					<th><?php _e('Fee','usces'); ?>(<?php usces_crcode(); ?>)</th>
				</tr>
				<tr>
					<td class="cod_f">0</td>
					<td class="cod_m"><?php _e(' - ','usces'); ?></td>
					<td class="cod_e"><input name="fee_first_amount" id="fee_first_amount" type="text" class="short_str num" /></td>
					<td class="cod_cod"><input name="fee_first_fee" id="fee_first_fee" type="text" class="short_str num" /></td>
				</tr>
			</thead>
			<tbody id="fee_change_field"></tbody>
			<tfoot>
				<tr>
					<td class="cod_f"><span id="end_amount"></span></td>
					<td class="cod_m"><?php _e(' - ','usces'); ?></td>
					<td class="cod_e"><input name="fee_limit_amount" type="text" id="fee_limit_amount_change" class="short_str num" /></td>
					<td class="cod_cod"><input name="fee_end_fee" type="text" id="fee_end_fee" class="short_str num" /></td>
				</tr>
			</tfoot>
		</table>
		</div>
		</fieldset>
		<input type="hidden" id="welcartpay_fee_mode">
	</div><!--welcartpay_fee_dialog-->
<?php
		endif;
	}

	/**********************************************
	* usces_action_admin_member_info
	* 
	* @param  $data $member_metas $usces_member_history
	* @return -
	* @echo   html
	***********************************************/
	public function admin_member_info( $data, $member_metas, $usces_member_history ) {

		if( 0 < count($member_metas) ):
			//e-SCOTT 会員照会
			$response_member = $this->escott_member_reference( $data['ID'] );
			if( 'OK' == $response_member['ResponseCd'] ):
				$cardlast4 = substr($response_member['CardNo'], -4);
				$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
				$expmm = substr($response_member['CardExp'], 2, 2);
?>
		<tr>
			<td class="label"><?php _e('Lower 4 digits','usces'); ?></td>
			<td><div class="rod_left shortm"><?php echo $cardlast4; ?></div></td>
		</tr>
		<tr>
			<td class="label"><?php _e('Expiration date','usces'); ?></td>
			<td><div class="rod_left shortm"><?php echo $expyy.'/'.$expmm; ?></div></td>
		</tr>
		<tr>
			<td class="label"><?php _e('Quick payment','usces'); ?></td>
			<td><div class="rod_left shortm"><?php _e('Registered','usces'); ?></div></td>
		</tr>
<?php			if( !usces_have_member_continue_order( $data['ID'] ) && !usces_have_member_regular_order( $data['ID'] ) ): ?>
		<tr>
			<td class="label"><input type="checkbox" name="welcart_quickpay" id="welcart-quickpay-release" value="release"></td>
			<td><label for="welcart-quickpay-release"><?php _e('Release quick payment','usces'); ?></label></td>
		</tr>
<?php			endif;
			endif;
		endif;
	}

	/**********************************************
	* usces_action_post_update_memberdata
	* 管理画面会員情報更新
	* @param  $member_id $res
	* @return -
	***********************************************/
	public function admin_update_memberdata( $member_id, $res ) {
		global $usces;

		if( !$this->is_activate_card() || false === $res ) {
			return;
		}

		if( isset($_POST['welcart_quickpay']) and $_POST['welcart_quickpay'] == 'release' ) {
			$this->escott_member_delete( $member_id );
		}
	}

	/**********************************************
	* usces_fiter_the_payment_method_explanation
	* 
	* @param  $explanation $payment $value
	* @return str $explanation
	***********************************************/
	function set_payment_method_explanation( $explanation, $payment, $value ) {
		global $usces;

		$quickpay = '';
		if( 'acting_welcart_card' == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( 'link' == $acting_opts['card_activate'] ) {
				if( usces_is_login() && 'on' == $acting_opts['quickpay'] ) {
					$member = $usces->get_member();
					$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
					$KaiinPass = $this->get_quick_pass( $member['ID'] );
					if( !empty($KaiinId) && !empty($KaiinPass) ) {
						$quickpay = '<p class="welcartpay_quick_member"><label type="update"><input type="checkbox" name="quick_member" value="update"><span>'.__('Change and register purchased credit card','usces').'</span></label></p>';
					} else {
						if( usces_have_regular_order() || usces_have_continue_charge() ) {
							$quickpay = '<input type="hidden" name="quick_member" value="add">';
						} else {
							$quickpay = '<p class="welcartpay_quick_member"><label type="add"><input type="checkbox" name="quick_member" value="add"><span>'.__('Register and purchase a credit card','usces').'</span></label></p>';
						}
					}
				} else {
					$quickpay = '<input type="hidden" name="quick_member" value="no">';
				}
			}
		}
		return $quickpay.$explanation;
	}

	/**********************************************
	* usces_filter_available_payment_method
	* 
	* @param  $payments
	* @return array $payments
	***********************************************/
	function set_available_payment_method( $payments ) {
		global $usces;

		if( $usces->is_member_page($_SERVER['REQUEST_URI']) ) {
			$payment_method = array();
			foreach( (array)$payments as $id => $payment ) {
				if( 'acting_welcart_card' == $payment['settlement'] ) {
					$payment_method[$id] = $payments[$id];
					break;
				}
			}
			if( !empty($payment_method) ) {
				$payments = $payment_method;
			}
		}
		return $payments;
	}

	/**********************************************
	* usces_filter_delivery_secure_form_howpay
	* 
	* @param  $html
	* @return str $html
	***********************************************/
	function delivery_secure_form_howpay( $html ) {

		if( isset($_GET['page'] ) && ( 'member_update_settlement' == $_GET['page'] || 'member_register_settlement' == $_GET['page'] ) ) {
			$html = '';
		}
		return $html;
	}

	/**********************************************
	* usces_filter_template_redirect
	* クレジットカード登録・変更ページ表示
	* @param  -
	* @return -
	***********************************************/
	function member_update_settlement() {
		global $usces;

		if( $usces->is_member_page($_SERVER['REQUEST_URI']) ) {
			if( !usces_is_membersystem_state() or !usces_is_login() ) {
				return;
			}

			$acting_opts = $this->get_acting_settings();
			if( 'on' != $acting_opts['quickpay'] ) {
				return;
			}

			if( isset($_REQUEST['page']) and 'member_update_settlement' == $_REQUEST['page'] ) {
				$usces->page = 'member_update_settlement';
				$this->member_update_settlement_form();
				exit();

			} elseif( isset($_REQUEST['page']) and 'member_register_settlement' == $_REQUEST['page'] ) {
				$usces->page = 'member_register_settlement';
				$this->member_update_settlement_form();
				exit();
			}
		}
		return false;
	}

	/**********************************************
	* usces_filter_delete_member_check
	* 会員データ削除チェック
	* @param  $del $member_id
	* @return boolean $del
	***********************************************/
	function delete_member_check( $del, $member_id ) {
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		if( !empty($KaiinId) ) {
			$del = false;
		}
		return $del;
	}

	/**********************************************
	* usces_action_member_submenu_list
	* クレジットカード登録・変更ページリンク
	* @param  -
	* @return -
	* @echo   update_settlement()
	***********************************************/
	function e_update_settlement() {
		global $usces;

		$member = $usces->get_member();
		$html = $this->update_settlement( '', $member );
		echo $html;
	}

	/**********************************************
	* usces_filter_member_submenu_list
	* クレジットカード登録・変更ページリンク
	* @param  $html $member
	* @return str $html
	***********************************************/
	function update_settlement( $html, $member ) {
		global $usces;

		//if( defined('WCEX_MOBILE') ) {
		//	global $wcmb;
		//	if( DOCOMO === $wcmb['device_div'] || SOFTBANK === $wcmb['device_div'] || KDDI === $wcmb['device_div'] ) return $html;
		//}

		$acting_opts = $this->get_acting_settings();
		if( 'on' == $acting_opts['quickpay'] ) {
			//e-SCOTT 会員照会
			$response_member = $this->escott_member_reference( $member['ID'] );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$update_settlement_url = add_query_arg( array( 'page'=>'member_update_settlement', 're-enter'=>1 ), USCES_MEMBER_URL );
				$html .= '
				<div class="gotoedit">
				<a href="'.$update_settlement_url.'">'.__("Change the credit card is here >>",'usces').'</a>
				</div>';
			} else {
				$register_settlement_url = add_query_arg( array( 'page'=>'member_register_settlement', 're-enter'=>1 ), USCES_MEMBER_URL );
				$html .= '
				<div class="gotoedit">
				<a href="'.$register_settlement_url.'">'.__("Credit card registration is here >>",'usces').'</a>
				</div>';
			}
		}
		return $html;
	}

	/**********************************************
	* クレジットカード登録・変更ページ
	* @param  -
	* @return -
	* @echo   html
	***********************************************/
	function member_update_settlement_form() {
		global $usces;

		$member = $usces->get_member();
		$acting_opts = $this->get_acting_settings();

		if( 'link' == $acting_opts['card_activate'] ) {
			$TransactionDate = $this->get_transaction_date();
			$home_url = str_replace( 'http://', 'https://', home_url('/') );
			$redirecturl = $home_url.'?page_id='.USCES_MEMBER_NUMBER;
			$posturl = $home_url;

			$data_list = array();
			$data_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$data_list['TransactionDate'] = $TransactionDate;
			$data_list['MerchantFree3'] = $this->merchantfree3;
			$data_list['TenantId'] = $acting_opts['tenant_id'];
			if( 'member_register_settlement' == $usces->page ) {
				$data_list['OperateId'] = '4MemAdd';
				$data_list['KaiinId'] = $this->make_kaiin_id( $member['ID'] );
				$data_list['KaiinPass'] = $this->make_kaiin_pass();
			} else {
				$data_list['OperateId'] = '4MemChg';
				$data_list['KaiinId'] = $this->get_quick_kaiin_id( $member['ID'] );
				$data_list['KaiinPass'] = $this->get_quick_pass( $member['ID'] );
			}
			$data_list['ProcNo'] = '0000000';
			$data_list['RedirectUrl'] = $redirecturl;
			//$data_list['PostUrl'] = $posturl;
			$data_query = http_build_query( $data_list );
			$encryptvalue = openssl_encrypt( $data_query, 'aes-128-cbc', $acting_opts['key_aes'], false, $acting_opts['key_iv'] );

			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['EncryptValue'] = urlencode($encryptvalue);
			wp_redirect( add_query_arg( $param_list, $acting_opts['send_url_link'] ) );

		} else {
			$script = '';
			$message = '';
			$html = '';
			$register = ( 'member_register_settlement' == $usces->page ) ? true : false;
			$deleted = false;

			$cardno = '';
			$seccd = '';
			$expyy = '';
			$expmm = '';

			if( 'on' == $acting_opts['quickpay'] ) {
				if( isset($_POST['update']) ) {
					check_admin_referer( 'member_update_settlement', 'wc_nonce' );
					$response_member = $this->escott_member_update( $member['ID'] );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$message = __('Successfully updated.','usces');
					} else {
						$error_message = array();
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach( (array)$responsecd as $cd ) {
							$error_message[] = $this->error_message( $cd );
						}
						$error_message = array_unique( $error_message );
						if( 0 < count($error_message) ) {
							foreach( $error_message as $message ) {
								$usces->error_message .= '<p>'.$message.'</p>';
							}
						}
						$cardno = ( isset($_POST['cardno']) ) ? esc_html($_POST['cardno']) : '';
						$seccd = ( isset($_POST['seccd']) ) ? esc_html($_POST['seccd']) : '';
						$expyy = ( isset($_POST['expyy']) ) ? esc_html($_POST['expyy']) : '';
						$expmm = ( isset($_POST['expmm']) ) ? esc_html($_POST['expmm']) : '';
					}
				} elseif( isset($_POST['register']) ) {
					check_admin_referer( 'member_update_settlement', 'wc_nonce' );
					$response_member = $this->escott_member_register( $member['ID'] );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$message = __('Successfully registered.','usces');
						$register = false;
					} else {
						$error_message = array();
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach( (array)$responsecd as $cd ) {
							$error_message[] = $this->error_message( $cd );
						}
						$error_message = array_unique( $error_message );
						if( 0 < count($error_message) ) {
							foreach( $error_message as $message ) {
								$usces->error_message .= '<p>'.$message.'</p>';
							}
						}
						$cardno = ( isset($_POST['cardno']) ) ? esc_html($_POST['cardno']) : '';
						$seccd = ( isset($_POST['seccd']) ) ? esc_html($_POST['seccd']) : '';
						$expyy = ( isset($_POST['expyy']) ) ? esc_html($_POST['expyy']) : '';
						$expmm = ( isset($_POST['expmm']) ) ? esc_html($_POST['expmm']) : '';
					}
				/*} elseif( isset($_POST['delete']) && 'delete' == $_POST['delete'] ) {
					check_admin_referer( 'member_update_settlement', 'wc_nonce' );
					$response_member = $this->escott_member_delete( $member['ID'] );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$message = __('Credit card registration deleted.','usces');
						$deleted = true;
					} else {
						$error_message = array();
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach( (array)$responsecd as $cd ) {
							$error_message[] = $this->error_message( $cd );
						}
						$error_message = array_unique( $error_message );
						if( 0 < count($error_message) ) {
							foreach( $error_message as $message ) {
								$usces->error_message .= '<p>'.$message.'</p>';
							}
						}
						$cardno = ( isset($_POST['cardno']) ) ? esc_html($_POST['cardno']) : '';
						$seccd = ( isset($_POST['seccd']) ) ? esc_html($_POST['seccd']) : '';
						$expyy = ( isset($_POST['expyy']) ) ? esc_html($_POST['expyy']) : '';
						$expmm = ( isset($_POST['expmm']) ) ? esc_html($_POST['expmm']) : '';
					}*/
				}

				if( !$deleted ) {
					//e-SCOTT 会員照会
					$response_member = $this->escott_member_reference( $member['ID'] );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$cardlast4 = substr($response_member['CardNo'], -4);
						$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
						$expmm = substr($response_member['CardExp'], 2, 2);
					} else {
						$cardlast4 = '';
					}
					$html .= '<input name="acting" type="hidden" value="'.$this->paymod_id.'" />
					<table class="customer_form" id="'.$this->paymod_id.'">';
					if( !empty($cardlast4) ) {
						$html .= '
						<tr>
							<th scope="row">'.__('The last four digits of your card number','usces').'</th>
							<td colspan="2"><p>'.$cardlast4.'</p></td>
						</tr>';
					}
					$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __('(Single-byte numbers only)','usces').'<div class="attention">'.__('* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.','usces').'</div>' );
					$html .= '
						<tr>
							<th scope="row">'.__('card number','usces').'</th>
							<td colspan="2"><input name="cardno" type="text" id="welcart_cnum" size="16" value="'.$cardno.'" />'.$cardno_attention.'</td>
						</tr>';
					if( 'on' == $acting_opts['seccd'] ) {
						$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __('(Single-byte numbers only)','usces') );
						$html .= '
						<tr>
							<th scope="row">'.__('security code','usces').'</th>
							<td colspan="2"><input name="seccd" type="text" size="6" value="'.$seccd.'" />'.$seccd_attention.'</td>
						</tr>';
					}
					$html .= '
						<tr>
							<th scope="row">'.__('Card expiration','usces').'</th>
							<td colspan="2">
							<select name="expmm">
								<option value=""'.(empty($expmm) ? ' selected="selected"' : '').'>----</option>';
					for( $i = 1; $i <= 12; $i++ ) {
						$html .= '
								<option value="'.sprintf('%02d', $i).'"'.(( $i == (int)$expmm ) ? ' selected="selected"' : '').'>'.sprintf('%2d', $i).'</option>';
					}
					$html .= '
							</select>'.__('month','usces').'&nbsp;
							<select name="expyy">
								<option value=""'.(empty($expyy) ? ' selected="selected"' : '').'>------</option>';
					for( $i = 0; $i < 15; $i++ ) {
						$year = date_i18n('Y') - 1 + $i;
						$selected = ( $year == $expyy ) ? ' selected="selected"' : '';
						$html .= '
								<option value="'.$year.'"'.$selected.'>'.$year.'</option>';
					}
					$html .= '
							</select>'.__('year','usces').'
							</td>
						</tr>
					</table>';
				}
			}

			$update_settlement_url = add_query_arg( array( 'page'=>$usces->page, 'settlement'=>1, 're-enter'=>1 ), USCES_MEMBER_URL );
			if( '' != $message ) {
				$script .= '
				<script type="text/javascript">
					jQuery.event.add( window, "load", function() {
						alert("'.$message.'");
					});
				</script>';
			}

			ob_start();
			get_header();
?>
<?php if( '' != $script ) echo $script; ?>
<div id="content" class="two-column">
<div class="catbox">
<?php if( have_posts() ): usces_remove_filter(); ?>
<div class="post" id="wc_member_update_settlement">
<?php if( $register ): ?>
<h1 class="member_page_title"><?php _e('Credit card registration','usces'); ?></h1>
<?php else: ?>
<h1 class="member_page_title"><?php _e('Credit card update','usces'); ?></h1>
<?php endif; ?>
<div class="entry">
<div id="memberpages">
<div class="whitebox">
	<div id="memberinfo">
	<div class="header_explanation"></div>
	<?php if( !$deleted && !$register ): ?>
	<p><?php _e('If you want to change the expiration date only, please the card number to the blank.','usces'); ?></p>
	<?php endif; ?>
	<div class="error_message"><?php usces_error_message(); ?></div>
	<form id="member-card-info" action="<?php echo $update_settlement_url; ?>" method="post" onKeyDown="if(event.keyCode == 13) {return false;}">
		<?php echo $html; ?>
		<div class="send">
	<?php if( $register ): ?>
			<input type="submit" name="register" id="card-register" value="<?php _e('Register'); ?>" />
	<?php else: ?>
		<?php if( !$deleted ): ?>
			<input type="submit" name="update" id="card-update" value="<?php _e('Update'); ?>" />
			<?php //if( !usces_have_member_continue_order( $member['ID'] ) && !usces_have_member_regular_order( $member['ID'] ) ): ?>
			<!--<input type="button" id="card-delete" value="<?php _e('Remove'); ?>" />
			<input type="hidden" name="delete" value="" />-->
			<?php //endif; ?>
		<?php endif; ?>
	<?php endif; ?>
			<input type="button" name="back" value="<?php _e('Back to the member page.','usces'); ?>" onclick="location.href='<?php echo USCES_MEMBER_URL; ?>'" />
			<input type="button" name="top" value="<?php _e('Back to the top page.','usces'); ?>" onclick="location.href='<?php echo home_url(); ?>'" />
		</div>
	<?php wp_nonce_field( 'member_update_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="footer_explanation"></div>
	</div><!-- end of memberinfo -->
</div><!-- end of whitebox -->
</div><!-- end of memberpages -->
</div><!-- end of entry -->
</div><!-- end of post -->
<?php else: ?>
<p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
<?php endif; ?>
</div><!-- end of catbox -->
</div><!-- end of content -->
<?php
			$sidebar = apply_filters( 'usces_filter_member_update_settlement_page_sidebar', 'cartmember' );
			if( !empty($sidebar) ) {
				get_sidebar( $sidebar );
			}
			get_footer();
			$html = ob_get_contents();
			ob_end_clean();

			echo $html;
		}
	}

	/**********************************************
	* クレジットカード変更メール
	* @param  -
	* @return -
	***********************************************/
	function send_update_settlement_mail() {
		global $usces;

		$member = $usces->get_member();
		$mail_data = $usces->options['mail_data'];

		$subject = apply_filters( 'usces_filter_send_update_settlement_mail_subject', __('Confirmation of credit card update','usces'), $member );
		$mail_header = __('Your credit card information has been updated.','usces')."\r\n\r\n";
		$mail_footer = $mail_data['footer']['thankyou'];
		$name = usces_localized_name( $member['name1'], $member['name2'], 'return' );

		$message  = '--------------------------------'."\r\n";
		$message .= __('Member ID','usces').' : '.$member['ID']."\r\n";
		$message .= __('Name','usces').' : '.sprintf( _x('%s','honorific','usces'), $name )."\r\n";
		$message .= __('e-mail adress','usces').' : '.$member['mailaddress1']."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message .= __('If you have not requested this email, sorry to trouble you, but please contact us.','usces')."\r\n\r\n";
		$message  = apply_filters( 'usces_filter_send_update_settlement_mail_message', $message, $member );
		$message  = apply_filters( 'usces_filter_send_update_settlement_mail_message_head', $mail_header, $member ).$message.apply_filters( 'usces_filter_send_update_settlement_mail_message_foot', $mail_footer, $member )."\r\n";

		//if( $usces->options['put_customer_name'] == 1 ) {
			$message = sprintf( __('Dear %s','usces'), $name )."\r\n\r\n".$message;
		//}

		$send_para = array(
			'to_name' => sprintf( _x('%s','honorific','usces'), $name ),
			'to_address' => $member['mailaddress1'],
			'from_name' => get_option( 'blogname' ),
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => $message
		);
		usces_send_mail( $send_para );

		$admin_para = array(
			'to_name' => 'Shop Admin',
			'to_address' => $usces->options['order_mail'],
			'from_name' => 'Welcart Auto BCC',
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => $message
		);
		usces_send_mail( $admin_para );
	}

	/**********************************************
	* usces_filter_the_continue_payment_method
	* 
	* @param  $payment_method
	* @return array $payment_method
	***********************************************/
	function continuation_payment_method( $payment_method ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( 'on' == $acting_opts['quickpay'] ) {
			$payment_method[] = 'acting_welcart_card';
		}
		return $payment_method;
	}

	/**********************************************
	* dlseller_filter_the_payment_method_restriction wcad_filter_the_payment_method_restriction
	* 
	* @param  $payments_restriction $value
	* @return array $payments_restriction
	***********************************************/
	function payment_method_restriction( $payments_restriction, $value ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( ( usces_have_regular_order() || usces_have_continue_charge() ) && usces_is_login() && 'on' == $acting_opts['quickpay'] ) {
			$payments = usces_get_system_option( 'usces_payment_method', 'settlement' );
			$payments_restriction[] = $payments['acting_welcart_card'];
			foreach( (array)$payments_restriction as $key => $value ) {
				$sort[$key] = $value['sort'];
			}
			array_multisort( $sort, SORT_ASC, $payments_restriction );
		}
		return $payments_restriction;
	}

	/**********************************************
	* dlseller_filter_first_charging
	* 「初回引落し日」
	* @param  $time $post_id $usces_item $order_id $continue_data
	* @return datetime $time
	***********************************************/
	public function first_charging_date( $time, $post_id, $usces_item, $order_id, $continue_data ) {

		if( 99 == $usces_item['item_chargingday'] ) {
			if( empty($order_id) ) {
				$today = date_i18n( 'Y-m-d', current_time('timestamp') );
				list( $year, $month, $day ) = explode( "-", $today );
				$time = mktime( 0, 0, 0, (int)$month, (int)$day, (int)$year );
			}
		}
		return $time;
	}

	/**********************************************
	* dlseller_filter_continue_member_list_limitofcard
	* 継続課金会員リスト「有効期限」
	* @param  $limitofcard $member_id $order_id $meta_data
	* @return str $limitofcard
	***********************************************/
	public function continue_member_list_limitofcard( $limitofcard, $member_id, $order_id, $meta_data ) {

		if( isset($meta_data['acting']) ) {
			if( version_compare( WCEX_DLSELLER_VERSION, '3.0-beta', '<=' ) ) {
				$payment = usces_get_payments_by_name( $meta_data['acting'] );
				$acting = $payment['settlement'];
			} else {
				$acting = $meta_data['acting'];
			}
			if( 'acting_welcart_card' != $acting ) {
				return $limitofcard;
			}

			$acting_opts = $this->get_acting_settings();
			if( 'on' != $acting_opts['quickpay'] ) {
				return $limitofcard;
			}

			$KaiinId = $this->get_quick_kaiin_id( $member_id );
			$KaiinPass = $this->get_quick_pass( $member_id );

			if( !empty($KaiinId) && !empty($KaiinPass) ) {
				//e-SCOTT 会員照会
				$response_member = $this->escott_member_reference( $member_id, $KaiinId, $KaiinPass );
				if( 'OK' == $response_member['ResponseCd'] ) {
					$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
					$expmm = substr($response_member['CardExp'], 2, 2);
					$limit = $expyy.$expmm;
					$now = date_i18n( 'Ym', current_time('timestamp', 0) );
					$limitofcard = $expmm.'/'.substr($response_member['CardExp'], 0, 2);
					if( $limit <= $now ) {
						$limitofcard .= '<br /><a href="javascript:void(0)" onClick="uscesMail.getMailData(\''.$member_id.'\', \''.$order_id.'\')">'.__('Update Request Email','dlseller').'</a>';
					}
				}
			} else {
				$limitofcard = '';
			}
		}
		return $limitofcard;
	}

	/**********************************************
	* dlseller_filter_continue_member_list_continue_status
	* 継続課金会員リスト「契約」
	* @param  $status $member_id $order_id $meta_data
	* @return str $status
	***********************************************/
	public function continue_member_list_continue_status( $status, $member_id, $order_id, $meta_data ) {
		return $status;
	}

	/**********************************************
	* dlseller_filter_continue_member_list_condition
	* 継続課金会員リスト「状態」
	* @param  $condition $member_id $order_id $meta_data
	* @return str $condition
	***********************************************/
	public function continue_member_list_condition( $condition, $member_id, $order_id, $meta_data ) {
		global $usces;

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$payment = $usces->getPayments( $order_data['order_payment_name'] );
		if( 'acting_welcart_card' == $payment['settlement'] ) {
			$url = admin_url( 'admin.php?page=usces_continue&continue_action=settlement&member_id='.$member_id.'&order_id='.$order_id );
			$condition = '<a href="'.$url.'">'.__('Detail','usces').'</a>';

			if( $meta_data['status'] == 'continuation' ) {
				$status = $this->get_latest_status( $member_id, $order_id );
				if( !empty($status) && 'OK' != $status ) {
					$condition .= '<div class="acting-status card-error">'.__('Settlement error','usces').'</div>';
				}
			}
		}
		return $condition;
	}

	/**********************************************
	* dlseller_action_continue_member_list_page
	* 継続課金会員決済状況ページ表示
	* @param  $continue_action
	* @return -
	***********************************************/
	public function continue_member_list_page( $continue_action ) {

		if( 'settlement' == $continue_action ) {
			$member_id = ( isset($_GET['member_id']) ) ? $_GET['member_id'] : '';
			$order_id = ( isset($_GET['order_id']) ) ? $_GET['order_id'] : '';
			if( !empty($member_id) && !empty($order_id) ) {
				$this->continue_member_settlement_info_page( $member_id, $order_id );
				exit();
			}
		}
	}

	/**********************************************
	* 継続課金会員決済状況ページ
	* @param  $member_id $order_id
	* @return -
	* @echo   html
	***********************************************/
	public function continue_member_settlement_info_page( $member_id, $order_id ) {
		global $usces;

		if( version_compare( WCEX_DLSELLER_VERSION, '3.0-beta', '<=' ) ) {
			$continue_data = unserialize( $usces->get_member_meta_value( 'continuepay_'.$order_id, $member_id ) );
		} else {
			$continue_data = $this->get_continuation_data( $order_id, $member_id );
		}
		$curent_url = esc_url($_SERVER['REQUEST_URI']);
		$navibutton = '<a href="'.esc_url($_SERVER['HTTP_REFERER']).'" class="back-list"><span class="dashicons dashicons-list-view"></span>'.__('Back to the continue members list','dlseller').'</a>';

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		if( !$order_data ) {
			return;
		}

		$name = usces_localized_name( $order_data['order_name1'], $order_data['order_name2'], 'return' );
		$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );

		$payment = $usces->getPayments( $order_data['order_payment_name'] );
		if( 'acting_welcart_card' != $payment['settlement'] ) {
			return;
		}

		$contracted_date = ( empty($continue_data['contractedday']) ) ? dlseller_next_contracting( $order_id ) : $continue_data['contractedday'];
		if( !empty($contracted_date) ) {
			list( $contracted_year, $contracted_month, $contracted_day ) = explode( '-', $contracted_date );
		} else {
			$contracted_year = 0;
			$contracted_month = 0;
			$contracted_day = 0;
		}
		$charged_date = ( empty($continue_data['chargedday']) ) ? dlseller_next_charging( $order_id ) : $continue_data['chargedday'];
		if( !empty($charged_date) ) {
			list( $charged_year, $charged_month, $charged_day ) = explode( '-', $charged_date );
		} else {
			$charged_year = 0;
			$charged_month = 0;
			$charged_day = 0;
		}
		$year = substr(date_i18n('Y', current_time('timestamp')), 0, 4);
		$total_full_price = $order_data['order_item_total_price'] - $order_data['order_usedpoint'] + $order_data['order_discount'] + $order_data['order_shipping_charge'] + $order_data['order_cod_fee'] + $order_data['order_tax'];

		$log_data = $this->get_acting_log( $order_id );
		$num = count($log_data) + 1;

		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$card = ( empty($KaiinId) ) ? '&nbsp;<span id="settlement-status"><span class="acting-status card-error">'.__('Card unregistered','usces').'</span></span>' : '';
?>
<div class="wrap">
<div class="usces_admin">
<h1>Welcart Management <?php _e('Continuation charging member information','dlseller'); ?></h1>
<p class="version_info">Version <?php echo WCEX_DLSELLER_VERSION; ?></p>
<?php usces_admin_action_status(); ?>
<div class="edit_pagenav"><?php echo $navibutton; ?></div>
<div id="datatable">
<div id="tablesearch" class="usces_tablesearch">
<div id="searchBox" style="display:block">
	<table class="search_table">
	<tr>
		<td class="label"><?php _e('Continuation charging information','dlseller'); ?></td>
		<td>
			<table class="order_info">
			<tr>
				<th><?php _e('Member ID','dlseller'); ?></th>
				<td><?php echo $member_id.$card; ?></td>
				<th><?php _e('Contractor name','dlseller'); ?></th>
				<td><?php echo esc_html($name); ?></td>
			</tr>
			<tr>
				<th><?php _e('Order ID','dlseller'); ?></th>
				<td><?php echo $order_id; ?></td>
				<th><?php _e('Application Date','dlseller'); ?></th>
				<td><?php echo $order_data['order_date']; ?></td>
			</tr>
			<tr>
				<th><?php _e('Renewal Date','dlseller'); ?></th>
				<td>
					<select id="contracted-year">
						<option value="0"<?php if( $contracted_year == 0 ) echo ' selected="selected"'; ?>></option>
						<option value="<?php echo $year; ?>"<?php if( $contracted_year == $year ) echo ' selected="selected"'; ?>><?php echo $year; ?></option>
						<option value="<?php echo $year+1; ?>"<?php if( $contracted_year == ($year+1) ) echo ' selected="selected"'; ?>><?php echo $year+1; ?></option>
					</select>-
					<select id="contracted-month">
			    		<option value="0"<?php if( $contracted_month == 0 ) echo ' selected="selected"'; ?>></option>
						<?php for( $i = 1; $i <= 12; $i++ ): ?>
				    	<option value="<?php printf("%02d",$i); ?>"<?php if( (int)$contracted_month == $i ) echo ' selected="selected"'; ?>><?php printf("%2d",$i); ?></option>
						<?php endfor; ?>
					</select>-
					<select id="contracted-day">
			    		<option value="0"<?php if( $contracted_day == 0 ) echo ' selected="selected"'; ?>></option>
						<?php for( $i = 1; $i <= 31; $i++ ): ?>
						<option value="<?php printf("%02d",$i); ?>"<?php if( (int)$contracted_day == $i ) echo ' selected="selected"'; ?>><?php printf("%2d",$i); ?></option>
						<?php endfor; ?>
					</select>
				</td>
				<th><?php _e('Next Withdrawal Date','dlseller'); ?></th>
				<td>
					<select id="charged-year">
						<option value="0"<?php if( $charged_year == 0 ) echo ' selected="selected"'; ?>></option>
						<option value="<?php echo $year; ?>"<?php if( $charged_year == $year ) echo ' selected="selected"'; ?>><?php echo $year; ?></option>
						<option value="<?php echo $year+1; ?>"<?php if( $charged_year == ($year+1) ) echo ' selected="selected"'; ?>><?php echo $year+1; ?></option>
					</select>-
					<select id="charged-month">
			    		<option value="0"<?php if( $charged_month == 0 ) echo ' selected="selected"'; ?>></option>
						<?php for( $i = 1; $i <= 12; $i++ ): ?>
				    	<option value="<?php printf("%02d",$i); ?>"<?php if( (int)$charged_month == $i ) echo ' selected="selected"'; ?>><?php printf("%2d",$i); ?></option>
						<?php endfor; ?>
					</select>-
					<select id="charged-day">
			    		<option value="0"<?php if( $charged_day == 0 ) echo ' selected="selected"'; ?>></option>
						<?php for( $i = 1; $i <= 31; $i++ ): ?>
						<option value="<?php printf("%02d",$i); ?>"<?php if( (int)$charged_day == $i ) echo ' selected="selected"'; ?>><?php printf("%2d",$i); ?></option>
						<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php _e('Amount on order','usces'); ?></th>
				<td><?php usces_crform( $continue_data['order_price'], false );//usces_crform( $total_full_price, false ); ?></td>
				<th><?php _e('Settlement amount','usces'); ?></th>
				<td><input type="text" id="price" style="text-align: right;" value="<?php usces_crform( $continue_data['price'], false, false, '', false ); ?>"><?php usces_crcode(); ?></td>
			</tr>
			<tr>
				<th><?php _e('Status','dlseller'); ?></th>
				<td><select id="dlseller-status">
				<?php if( $continue_data['status'] == 'continuation' ): ?>
					<option value="continuation" selected="selected"><?php _e('Continuation','dlseller'); ?></option>
					<option value="cancellation"><?php _e('Stop','dlseller'); ?></option>
				<?php else: ?>
					<option value="cancellation" selected="selected"><?php _e('Cancellation','dlseller'); ?></option>
					<option value="continuation"><?php _e('Resumption','dlseller'); ?></option>
				<?php endif; ?>
				</select></td>
				<td colspan="2"><input id="continuation-update" type="button" class="button button-primary" value="<?php _e('Update'); ?>" /></td>
			</tr>
			</table>
			<?php do_action( 'usces_action_continuation_charging_information', $continue_data, $member_id, $order_id ); ?>
		</td>
	</tr>
	</table>
</div><!-- searchBox -->
</div><!-- tablesearch -->
<table id="mainDataTable" class="new-table order-new-table">
	<thead>
	<tr>
		<th scope="col">&nbsp;</th>
		<th scope="col"><?php _e('Processing date','usces'); ?></th>
		<th scope="col"><?php _e('Transaction ID','usces'); ?></th>
		<th scope="col"><?php _e('Processing classification','usces'); ?></th>
		<th scope="col">&nbsp;</th>
	</tr>
	</thead>
<?php foreach( (array)$log_data as $log_row ):
		$log = $this->get_acting_latest_log( $log_row['log_key'] );
		if( isset($log['OperateId']) && isset($log['ResponseCd']) && 'OK' == $log['ResponseCd'] ) {
			$class = ' card-'.mb_strtolower(substr($log['OperateId'],1));
			$status_name = $this->get_operate_name( $log['OperateId'] );
			$MerchantFree1 = $log['MerchantFree1'];
			$ResponseCd = '';
		} else {
			$class = ' card-error';
			$status_name = __('Settlement error','usces');
			if( isset($log_row['log']) ) {
				$log = maybe_unserialize( $log_row['log'] );
				$MerchantFree1 = $log['MerchantFree1'];
				$ResponseCd = $log['ResponseCd'];
			} else {
				$MerchantFree1 = '9999999999';
				$ResponseCd = 'NG';
			}
		}
?>
	<tbody>
	<tr>
		<td><?php echo $num; ?></td>
		<td><?php echo $log_row['datetime']; ?></td>
		<td><?php echo $MerchantFree1; ?></td>
		<?php if( !empty($status_name) ): ?>
		<td><span id="settlement-status"><span class="acting-status<?php echo $class; ?>"><?php echo $status_name; ?></span></span></td>
		<td>
			<input type="button" id="settlement-information-<?php echo $MerchantFree1; ?>-<?php echo $num; ?>" class="button settlement-information" value="<?php _e('Settlement info','usces'); ?>">
			<input type="hidden" id="responsecd-<?php echo $MerchantFree1; ?>-<?php echo $num; ?>" value="<?php echo $ResponseCd; ?>">
		</td>
		<?php else: ?>
		<td>&nbsp;</td><td>&nbsp;</td>
		<?php endif; ?>
	</tr>
	</tbody>
	<?php $num--; ?>
<?php endforeach; ?>
<?php
	$trans_id = $usces->get_order_meta_value( 'trans_id', $order_id );
	$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id );
	if( $latest_log ):
		$class = ' card-'.mb_strtolower(substr($latest_log['OperateId'],1));
		$status_name = $this->get_operate_name( $latest_log['OperateId'] );
?>
	<tbody>
	<tr>
		<td>1</td>
		<td><?php echo $order_data['order_date']; ?></td>
		<td><?php echo $trans_id; ?></td>
		<?php if( !empty($status_name) ): ?>
		<td><span id="settlement-status"><span class="acting-status<?php echo $class; ?>"><?php echo $status_name; ?></span></span></td>
		<td><input type="button" id="settlement-information-<?php echo $trans_id; ?>-1" class="button settlement-information" value="<?php _e('Settlement info','usces'); ?>"></td>
		<?php else: ?>
		<td>&nbsp;</td><td>&nbsp;</td>
		<?php endif; ?>
	</tr>
	</tbody>
<?php endif; ?>
</table>
</div><!--datatable-->
<input name="member_id" type="hidden" id="member_id" value="<?php echo $member_id; ?>" />
<input name="order_id" type="hidden" id="order_id" value="<?php echo $order_id; ?>" />
<input name="usces_referer" type="hidden" id="usces_referer" value="<?php echo urlencode($curent_url); ?>" />
<?php wp_nonce_field( 'order_edit', 'wc_nonce' ); ?>
</div><!--usces_admin-->
</div><!--wrap-->
<?php
		$order_action = 'edit';
		$cart = array();
		$action_args = compact( 'order_action', 'order_id', 'cart' );
		$this->settlement_dialog( $order_data, $action_args );
		include( ABSPATH.'wp-admin/admin-footer.php' );
	}

	/**********************************************
	* dlseller_filter_card_update_mail
	* 継続課金会員クレジットカード変更依頼メール
	* @param  $message $member_id $order_id
	* @return str $message
	***********************************************/
	public function continue_member_card_update_mail( $message, $member_id, $order_id ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( !usces_is_membersystem_state() || 'on' != $acting_opts['quickpay'] ) {
			return $message;
		}

		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );

		if( !empty($KaiinId) && !empty($KaiinPass) ) {
			//e-SCOTT 会員照会
			$response_member = $this->escott_member_reference( $member_id, $KaiinId, $KaiinPass );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
				$expmm = substr($response_member['CardExp'], 2, 2);

				$now = date_i18n( 'Ym', current_time('timestamp', 0) );
				$member_info = $usces->get_member_info( $member_id );
				$mail_data = $usces->options['mail_data'];

				$nonsessionurl = usces_url('cartnonsession', 'return');
				$parts = parse_url($nonsessionurl);
				if( isset($parts['query']) ) {
					parse_str( $parts['query'], $query );
				}
				if( false !== strpos($nonsessionurl, '/usces-cart') ) {
					$nonsessionurl = str_replace( '/usces-cart', '/usces-member', $nonsessionurl );
				} elseif( isset($query['page_id']) && $query['page_id'] == USCES_CART_NUMBER ) {
					$nonsessionurl = str_replace( 'page_id='.USCES_CART_NUMBER, 'page_id='.USCES_MEMBER_NUMBER, $nonsessionurl );
				}
				$delim = ( false === strpos($nonsessionurl, '?') ) ? '?' : '&';

				$regd = $expyy.$expmm;
				if( $regd == $now ) {
					$flag = 'NOW';
				} elseif( $regd < $now ) {
					$flag = 'PASSED';
				} else {
					return $message;
				}

				$exp = mktime( 0, 0, 0, $expmm, 1, $expyy );
				$limit = date_i18n(__('F, Y'), $exp );
				$name = usces_localized_name( $member_info['mem_name1'], $member_info['mem_name2'], 'return' );

				$message  = __('Member ID','dlseller').' : '.$member_id."\n";
				$message .= __('Contractor name','dlseller').' : '.sprintf( _x('%s','honorific','usces'), $name )."\n\n\n";
				$message .= __("Thank you very much for using our service.",'dlseller')."\r\n\r\n";
				$message .= __("Please be sure to check this notification because it is an important contact for continued use of the service under contract.",'dlseller')."\r\n\r\n";
				$message .= __("---------------------------------------------------------",'dlseller')."\r\n";
				$message .= sprintf( __("Currently registered credit card expiration date is %s, ",'dlseller'), $limit )."\r\n";
				if( 'NOW' == $flag ) {
					$message .= __("So you keep on this you will not be able to pay next month.",'dlseller')."\r\n";
				} else {
					$message .= __("So your payment of this month is outstanding payment.",'dlseller')."\r\n";
				}
				$message .= __("---------------------------------------------------------",'dlseller')."\r\n\r\n";
				$message .= __("If you have received a new credit card, ",'dlseller')."\r\n";
				$message .= __("Please click the URL below and update the card information during this month.",'dlseller')."\r\n";
				$message .= __("Sorry for troubling you, please process it.",'dlseller')."\r\n\r\n\r\n";
				$message .= $nonsessionurl.$delim.'dlseller_card_update=login&dlseller_up_mode=1&dlseller_order_id='.$order_id."\r\n";
				$message .= __("If the card information update procedure failed, please contact us at the following email address.",'dlseller')."\r\n\r\n";
				$message .= __("Thank you.",'dlseller')."\r\n\r\n\r\n";
				$message .= $mail_data['footer']['ordermail'];
				$message  = apply_filters( 'usces_filter_continue_member_card_update_mail', $message, $member_id, $member_info );
			}
		}
		return $message;
	}

	/**********************************************
	* dlseller_action_do_continuation_charging
	* 自動継続課金処理
	* @param  $today $member_id $order_id $continue_data
	* @return -
	***********************************************/
	public function auto_continuation_charging( $today, $member_id, $order_id, $continue_data ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( !usces_is_membersystem_state() || 'on' != $acting_opts['quickpay'] ) {
			return;
		}

		if( 0 >= $continue_data['price'] ) {
			return;
		}

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		if( !$order_data || $usces->is_status( 'cancel', $order_data['order_status'] ) ) {
			return;
		}

		$payment = $usces->getPayments( $order_data['order_payment_name'] );
		if( 'acting_welcart_card' != $payment['settlement'] ) {
			return;
		}

		$acting = 'welcart_card';
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );
		$rand = usces_acting_key();

		if( !empty($KaiinId) && !empty($KaiinPass) ) {
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params_member = array();
			$params = array();

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $rand;
			$param_list['MerchantFree2'] = $payment['settlement'];
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params_member['send_url'] = $acting_opts['send_url_member'];
			$params_member['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemRefM',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員照会
			$response_member = $this->connection( $params_member );
//usces_log(print_r($response_member,true),"test.log");
			if( 'OK' == $response_member['ResponseCd'] ) {
				$params['send_url'] = $acting_opts['send_url'];
				$params['param_list'] = array_merge( $param_list,
					array(
						'OperateId' => $acting_opts['operateid_dlseller'],
						'Amount' => usces_crform( $continue_data['price'], false, false, 'return', false ),
						'PayType' => '01',
						'KaiinId' => $KaiinId,
						'KaiinPass' => $KaiinPass
					)
				);
				//e-SCOTT 決済
				$response_data = $this->connection( $params );
				$this->save_acting_history_log( $response_data, $order_id.'_'.$rand );
//usces_log(print_r($response_data,true),"test.log");
				if( 'OK' == $response_data['ResponseCd'] ) {
					//$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
					//$usces->set_order_meta_value( 'wc_trans_id', $rand, $order_id );
					$cardlast4 = substr($response_member['CardNo'], -4);
					$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
					$expmm = substr($response_member['CardExp'], 2, 2);
					$response_data['acting'] = $acting;
					$response_data['PayType'] = '01';
					$response_data['CardNo'] = $cardlast4;
					$response_data['CardExp'] = $expyy.'/'.$expmm;
					//$usces->set_order_meta_value( $acting_flg, serialize($response_data), $order_id );
					$this->save_acting_log( $response_data, $order_id.'_'.$rand );
					$this->auto_settlement_mail( $member_id, $order_id, $response_data, $continue_data );
				} else {
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)$responsecd as $cd ) {
						$response_data[$cd] = $this->response_message( $cd );
					}
					$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$response_data );
					usces_save_order_acting_error( $log );
					$this->save_acting_log( $response_data, $order_id.'_'.$rand );
					$this->auto_settlement_error_mail( $member_id, $order_id, $response_data, $continue_data );
				}
				do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_data );
			} else {
				$responsecd = explode( '|', $response_member['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$response_member[$cd] = $this->response_message( $cd );
				}
				$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$member_id, 'result'=>$response_member['ResponseCd'], 'data'=>$response_member );
				usces_save_order_acting_error( $log );
				$this->save_acting_log( $response_member, $order_id.'_'.$rand );
				$this->auto_settlement_error_mail( $member_id, $order_id, $response_member, $continue_data );
				do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_member );
			}
		} else {
			$logdata = array( 'KaiinId'=>$KaiinId, 'KaiinPass'=>$KaiinPass );
			$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$member_id, 'result'=>'MEMBER ERROR', 'data'=>$logdata );
			usces_save_order_acting_error( $log );
			$log['ResponseCd'] = 'NG';
			$log['MerchantFree1'] = $rand;
			$this->save_acting_log( $log, $order_id.'_'.$rand );
			$this->auto_settlement_error_mail( $member_id, $order_id, $logdata, $continue_data );
			do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $log );
		}
	}

	/**********************************************
	* 自動継続課金処理メール（正常）
	* @param  $member_id $order_id $response_data $continue_data
	* @return -
	***********************************************/
	function auto_settlement_mail( $member_id, $order_id, $response_data, $continue_data ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$mail_body = $this->auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data );

		if( 'on' == $acting_opts['auto_settlement_mail'] ) {
			$subject = __('Announcement of automatic continuing charging process','usces');
			$name = usces_localized_name( $order_data['order_name1'], $order_data['order_name2'], 'return' );
			$mail_data = $usces->options['mail_data'];
			$mail_header = __('We will report automated accounting process was carried out as follows.','usces')."\r\n\r\n";
			$mail_footer = __('If you have any questions, please contact us.','usces')."\r\n\r\n".$mail_data['footer']['thankyou'];
			$message = $mail_header.$mail_body.$mail_footer;
			if( isset($usces->options['put_customer_name']) && $usces->options['put_customer_name'] == 1 ) {
				$dear_name = sprintf( __('Dear %s','usces'), $name );
				$message = $dear_name."\r\n\r\n".$message;
			}
			$to_customer = array(
				'to_name' => sprintf( _x('%s','honorific','usces'), $name ),
				'to_address' => $order_data['order_email'],
				'from_name' => get_option( 'blogname' ),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => $message
			);
			usces_send_mail( $to_customer );
		}

		$ok = ( empty($this->continuation_charging_mail['OK']) ) ? 0 : $this->continuation_charging_mail['OK'];
		$this->continuation_charging_mail['OK'] = $ok + 1;
		$this->continuation_charging_mail['mail'][] = $mail_body;
	}

	/**********************************************
	* 自動継続課金処理メール（エラー）
	* @param  $member_id $order_id $response_data $continue_data
	* @return -
	***********************************************/
	function auto_settlement_error_mail( $member_id, $order_id, $response_data, $continue_data ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$mail_body = $this->auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data );

		if( 'on' == $acting_opts['auto_settlement_mail'] ) {
			$subject = __('Announcement of automatic continuing charging process','usces');
			$name = usces_localized_name( $order_data['order_name1'], $order_data['order_name2'], 'return' );
			$mail_data = $usces->options['mail_data'];
			$mail_header = __('We will reported that an error occurred in automated accounting process.','usces')."\r\n\r\n";
			$mail_footer = __('If you have any questions, please contact us.','usces')."\r\n\r\n".$mail_data['footer']['thankyou'];
			$message = $mail_header.$mail_body.$mail_footer;
			if( isset($usces->options['put_customer_name']) && $usces->options['put_customer_name'] == 1 ) {
				$dear_name = sprintf( __('Dear %s','usces'), $name );
				$message = $dear_name."\r\n\r\n".$message;
			}
			$to_customer = array(
				'to_name' => sprintf( _x('%s','honorific','usces'), $name ),
				'to_address' => $order_data['order_email'],
				'from_name' => get_option( 'blogname' ),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => $message
			);
			usces_send_mail( $to_customer );
		}

		$error = ( empty($this->continuation_charging_mail['NG']) ) ? 0 : $this->continuation_charging_mail['NG'];
		$this->continuation_charging_mail['NG'] = $error + 1;
		$this->continuation_charging_mail['mail'][] = $mail_body;
	}

	/**********************************************
	* 自動継続課金処理メール本文
	* @param  $member_id $order_id $order_data $response_data $continue_data
	* @return str $message
	***********************************************/
	function auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data ) {
		global $usces;

		$name = usces_localized_name( $order_data['order_name1'], $order_data['order_name2'], 'return' );
		$contracted_date = ( isset($continue_data['contractedday']) ) ? $continue_data['contractedday'] : '';
		$charged_date = ( isset($continue_data['chargedday']) ) ? $continue_data['chargedday'] : '';

		$message  = usces_mail_line( 2 );//--------------------
		$message .= __('Order ID','dlseller').' : '.$order_id."\r\n";
		$message .= __('Application Date','dlseller').' : '.$order_data['order_date']."\r\n";
		$message .= __('Member ID','dlseller').' : '.$member_id."\r\n";
		$message .= __('Contractor name','dlseller').' : '.sprintf( _x('%s','honorific','usces'), $name )."\r\n";
		$message .= __('Settlement amount','usces').' : '.usces_crform( $continue_data['price'], true, false, 'return' )."\r\n";
		if( isset($response_data['MerchantFree1']) ) {
			$message .= __('Transaction ID','usces').' : '.$response_data['MerchantFree1']."\r\n";
		}
		if( isset($response_data['TransactionId']) ) {
			$message .= __('Sequence number','usces').' : '.$response_data['TransactionId']."\r\n";
		}
		if( !empty($charged_date) ) {
			$message .= __('Next Withdrawal Date','dlseller').' : '.$charged_date."\r\n";
		}
		if( !empty($contracted_date) ) {
			$message .= __('Renewal Date','dlseller').' : '.$contracted_date."\r\n";
		}
		$message .= "\r\n";
		if( isset($response_data['ResponseCd']) ) {
			if( 'OK' == $response_data['ResponseCd'] ) {
				$message .= __('Result','usces').' : '.__('Normal done','usces')."\r\n";
			} else {
				$message .= __('Result','usces').' : '.__('Error','usces')."\r\n";
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$message .= $cd.' : '.$this->response_message( $cd )."\r\n";
				}
			}
		} else {
			$message .= __('Result','usces').' : '.__('Error','usces')."\r\n";
			$message .= __('Credit card is not registered.','usces')."\r\n";
		}
		$message .= usces_mail_line( 2 )."\r\n";//--------------------
		return $message;
	}

	/**********************************************
	* dlseller_action_do_continuation
	* 自動継続課金処理
	* @param  $today $todays_charging
	* @return -
	***********************************************/
	public function do_auto_continuation( $today, $todays_charging ) {
		global $usces;

		if( empty($todays_charging) ) {
			return;
		}

		$ok = ( empty($this->continuation_charging_mail['OK']) ) ? 0 : $this->continuation_charging_mail['OK'];
		$error = ( empty($this->continuation_charging_mail['NG']) ) ? 0 : $this->continuation_charging_mail['NG'];
		$admin_subject = __('Automatic Continuing Charging Process Result','usces').' '.$today;
		$admin_message = __('Report that automated accounting process has been completed.','usces')."\r\n\r\n"
			.__('Processing date','usces').' : '.date_i18n( 'Y-m-d H:i:s', current_time('timestamp') )."\r\n"
			.__('Normal done','usces').' : '.$ok."\r\n"
			.__('Abnormal done','usces').' : '.$error."\r\n\r\n";
		foreach( $this->continuation_charging_mail['mail'] as $mail ) {
			$admin_message .= $mail."\r\n";
		}
		$admin_message .= __('For details, please check on the administration panel > Continuous charge member list > Continuous charge member information.','usces')."\r\n";

		$to_admin = array(
			'to_name' => 'Shop Admin',
			'to_address' => $usces->options['order_mail'],
			'from_name' => 'Welcart Auto BCC',
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $admin_subject,
			'message' => $admin_message
		);
		usces_send_mail( $to_admin );
		unset( $this->continuation_charging_mail );
	}

	/**********************************************
	* wcad_filter_available_regular_payment_method
	* 
	* @param  $payment_method
	* @return array $payment_method
	***********************************************/
	function available_regular_payment_method( $payment_method ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( isset($acting_opts['quickpay']) && 'on' == $acting_opts['quickpay'] ) {
			$payment_method[] = 'acting_welcart_card';
		}
		return $payment_method;
	}

	/**********************************************
	* wcad_filter_admin_notices
	* 
	* @param  $msg
	* @return str $msg
	***********************************************/
	function admin_notices_autodelivery( $msg ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( ( isset($acting_opts['activate']) && 'on' == $acting_opts['activate'] ) && 
			( isset($acting_opts['card_activate']) && ( 'on' == $acting_opts['card_activate'] || 'link' == $acting_opts['card_activate'] ) ) && 
			( isset($acting_opts['quickpay']) && 'on' == $acting_opts['quickpay'] ) ) {
			$msg = '';
		} else {
			$zeus_opts = $usces->options['acting_settings']['zeus'];
			$p_flag = ( ( isset($zeus_opts['activate']) && 'on' == $zeus_opts['activate'] ) && ( isset($zeus_opts['card_activate']) && 'on' == $zeus_opts['card_activate'] ) ) ? true : false;
			$batch = ( isset($zeus_opts['batch']) ) ? $zeus_opts['batch'] : 'off';
			if( !$p_flag || 'off' == $batch ) {
				$msg .= '
				<div class="error">
				<p>'.__("In 'credit settlement Settings', please set to 'use' the quickpay of WelcartPay.",'usces').'</p>
				</div>';
			}
		}
		return $msg;
	}

	/**********************************************
	* wcad_filter_shippinglist_acting
	* 
	* @param  $acting
	* @return str $acting
	***********************************************/
	function set_shippinglist_acting( $acting ) {

		$acting = 'acting_welcart_card';
		return $acting;
	}

	/**********************************************
	* wcad_action_reg_auto_orderdata
	* 定期購入決済処理
	* @param  $args = array(
				'cart'=>$cart, 'entry'=>$entry, 'order_id'=>$new_id, 
				'member_id'=>$regular_order['reg_mem_id'], 'payments'=>$payments, 'charging_type'=>$charging_type,
				'total_amount'=>$total_price+$tax, 'reg_id'=>$reg_id, 
				);
	* @return -
	***********************************************/
	function register_auto_orderdata( $args ) {
		global $usces;
		extract($args);

		$acting_opts = $this->get_acting_settings();
		if( !usces_is_membersystem_state() || 'on' != $acting_opts['quickpay'] ) {
			return;
		}

		if( 0 >= $total_amount ) {
			return;
		}

		$acting_flg = $payments['settlement'];
		if( 'acting_welcart_card' != $payments['settlement'] ) {
			return;
		}

		$settltment_errmsg = '';
		$acting = 'welcart_card';
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );
		$rand = usces_acting_key();

		if( !empty($KaiinId) && !empty($KaiinPass) ) {
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params_member = array();
			$params = array();

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $rand;
			$param_list['MerchantFree2'] = 'acting_welcart_card';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params_member['send_url'] = $acting_opts['send_url_member'];
			$params_member['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemRefM',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員照会
			$response_member = $this->connection( $params_member );
//usces_log(print_r($response_member,true),"test.log");
			if( 'OK' == $response_member['ResponseCd'] ) {
				$params['send_url'] = $acting_opts['send_url'];
				$params['param_list'] = array_merge( $param_list,
					array(
						'OperateId' => $acting_opts['operateid'],
						'Amount' => $total_amount,
						'PayType' => '01',
						'KaiinId' => $KaiinId,
						'KaiinPass' => $KaiinPass
					)
				);
				//e-SCOTT 決済
				$response_data = $this->connection( $params );
				$this->save_acting_history_log( $response_data, $order_id.'_'.$rand );
//usces_log(print_r($response_data,true),"test.log");
				if( 'OK' == $response_data['ResponseCd'] ) {
					$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
					$usces->set_order_meta_value( 'wc_trans_id', $rand, $order_id );
					$cardlast4 = substr($response_member['CardNo'], -4);
					$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
					$expmm = substr($response_member['CardExp'], 2, 2);
					$response_data['acting'] = $acting;
					$response_data['PayType'] = '01';
					$response_data['CardNo'] = $cardlast4;
					$response_data['CardExp'] = $expyy.'/'.$expmm;
					$usces->set_order_meta_value( $acting_flg, serialize($response_data), $order_id );
				} else {
					$settltment_errmsg = __('[Regular purchase] Settlement was not completed.','autodelivery');
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)$responsecd as $cd ) {
						$response_data[$cd] = $this->response_message( $cd );
					}
					$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$response_data );
					usces_save_order_acting_error( $log );
				}
				do_action( 'usces_action_register_auto_orderdata', $args, $response_data );
			} else {
				$settltment_errmsg = __('[Regular purchase] Member information acquisition error.','autodelivery');
				$responsecd = explode( '|', $response_member['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$response_member[$cd] = $this->response_message( $cd );
				}
				$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$member_id, 'result'=>$response_member['ResponseCd'], 'data'=>$response_member );
				usces_save_order_acting_error( $log );
				do_action( 'usces_action_register_auto_orderdata', $args, $response_member );
			}
			if( '' != $settltment_errmsg ) {
				$settlement = array( "settltment_status"=>__('Failure','autodelivery'), "settltment_errmsg"=>$settltment_errmsg );
				$usces->set_order_meta_value( $acting_flg, serialize($settlement), $order_id );
				wcad_settlement_error_mail( $order_id, $settltment_errmsg );
			}
		} else {
			$logdata = array( 'KaiinId'=>$KaiinId, 'KaiinPass'=>$KaiinPass );
			$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$member_id, 'result'=>'MEMBER ERROR', 'data'=>$logdata );
			usces_save_order_acting_error( $log );
			do_action( 'usces_action_register_auto_orderdata', $args, $log );
		}
	}

	/**********************************************
	* admin_print_footer_scripts
	* JavaScript
	* @param  -
	* @return -
	* @echo   js
	***********************************************/
	public function admin_scripts() {
		global $usces;

		$admin_page = ( isset($_GET['page']) ) ? $_GET['page'] : '';
		switch( $admin_page ):
		case 'usces_settlement':
			$settlement_selected = get_option( 'usces_settlement_selected' );
			if( in_array( 'welcart', (array)$settlement_selected ) ):
				$acting_opts = $this->get_acting_settings();
?>
<script type="text/javascript">
jQuery(document).ready( function($) {
	var card_activate = "<?php echo $acting_opts['card_activate']; ?>";
	var conv_activate = "<?php echo $acting_opts['conv_activate']; ?>";
	var atodene_activate = "<?php echo $acting_opts['atodene_activate']; ?>";
	if( "on" == card_activate ) {
		$(".card_welcart").css("display", "");
		$(".card_howtopay_welcart").css("display", "");
	} else if( "link" == card_activate ) {
		$(".card_welcart").css("display", "");
		$(".card_howtopay_welcart").css("display", "none");
	} else {
		$(".card_welcart").css("display", "none");
		$(".card_howtopay_welcart").css("display", "none");
	}
	if( "on" == conv_activate ) {
		$(".conv_welcart").css("display", "");
	} else {
		$(".conv_welcart").css("display", "none");
	}
	if( "on" == atodene_activate ) {
		$(".atodene_welcart").css("display", "");
	} else {
		$(".atodene_welcart").css("display", "none");
	}
	$(document).on( "change", ".card_activate_welcart", function() {
		if( "on" == $( this ).val() ) {
			$(".card_welcart").css("display", "");
			$(".card_howtopay_welcart").css("display", "");
		} else if( "link" == $( this ).val() ) {
			$(".card_welcart").css("display", "");
			$(".card_howtopay_welcart").css("display", "none");
		} else {
			$(".card_welcart").css("display", "none");
			$(".card_howtopay_welcart").css("display", "none");
		}
	});
	$(document).on( "change", ".conv_activate_welcart", function() {
		if( "on" == $( this ).val() ) {
			$(".conv_welcart").css("display", "");
		} else {
			$(".conv_welcart").css("display", "none");
		}
	});
	$(document).on( "change", ".atodene_activate_welcart", function() {
		if( "on" == $( this ).val() ) {
			$(".atodene_welcart").css("display", "");
		} else {
			$(".atodene_welcart").css("display", "none");
		}
	});

	adminSettlementWelcartPay = {
		openFee : function( mode ) {
			$("#fee_change_field").html("");
			$("#fee_fix").val( $("#"+mode+"_fee").val() );
			$("#fee_limit_amount_fix").val( $("#"+mode+"_fee_limit_amount_fix").val() );
			$("#fee_first_amount").val( $("#"+mode+"_fee_first_amount").val() );
			$("#fee_first_fee").val( $("#"+mode+"_fee_first_fee").val() );
			$("#fee_limit_amount_change").val( $("#"+mode+"_fee_limit_amount_change").val() );
			var fee_amounts = new Array();
			var fee_fees = new Array();
			if( 0 < $("#"+mode+"_fee_amounts").val().length ) {
				fee_amounts = $("#"+mode+"_fee_amounts").val().split("|");
			}
			if( 0 < $("#"+mode+"_fee_fees").val().length ) {
				fee_fees = $("#"+mode+"_fee_fees").val().split("|");
			}
			if( 0 < fee_amounts.length ) {
				var amount = parseInt($("#fee_first_amount").val()) + 1;
				for( var i = 0; i < fee_amounts.length; i++ ) {
					html = '<tr id="row_'+i+'"><td class="cod_f"><span id="amount_'+i+'">'+amount+'</span></td><td class="cod_m"><?php _e(' - ','usces'); ?></td><td class="cod_e"><input name="fee_amounts['+i+']" type="text" class="short_str num" value="'+fee_amounts[i]+'" /></td><td class="cod_cod"><input name="fee_fees['+i+']" type="text" class="short_str num" value="'+fee_fees[i]+'" /></td></tr>';
					$("#fee_change_field").append(html);
					amount = parseInt(fee_amounts[i]) + 1;
				}
				$("#end_amount").html( amount );
			} else {
				$("#end_amount").html( parseInt($("#"+mode+"_fee_first_amount").val()) + 1 );
			}
			$("#fee_end_fee").val( $("#"+mode+"_fee_end_fee").val() );

			var fee_type = $("#"+mode+"_fee_type").val();
			if( "change" == fee_type ) {
				$("#fee_type_change").prop("checked", true);
				$("#welcartpay_fee_fix_table").css("display","none");
				$("#welcartpay_fee_change_table").css("display","");
			} else {
				$("#fee_type_fix").prop("checked", true);
				$("#welcartpay_fee_fix_table").css("display","");
				$("#welcartpay_fee_change_table").css("display","none");
			}
		},

		updateFee : function( mode ) {
			var fee_type = $("input[name='fee_type']:checked").val();
			$("#"+mode+"_fee_type").val( fee_type );
			$("#"+mode+"_fee").val( $("#fee_fix").val() );
			$("#"+mode+"_fee_limit_amount").val( $("#fee_limit_amount_"+fee_type).val() );
			$("#"+mode+"_fee_first_amount").val( $("#fee_first_amount").val() );
			$("#"+mode+"_fee_first_fee").val( $("#fee_first_fee").val() );
			var fee_amounts = "";
			var fee_fees = "";
			var sp = "";
			var fee_amounts_length = $("input[name^='fee_amounts']").length;
			for( var i = 0; i < fee_amounts_length; i++ ) {
				fee_amounts += sp + $("input[name='fee_amounts\["+i+"\]']").val();
				fee_fees += sp + $("input[name='fee_fees\["+i+"\]']").val();
				sp = "|";
			}
			$("#"+mode+"_fee_amounts").val( fee_amounts );
			$("#"+mode+"_fee_fees").val( fee_fees );
			$("#"+mode+"_fee_end_fee").val( $("#fee_end_fee").val() );
		},

		setFeeType : function( mode, closed ) {
			var fee_type = $("input[name='fee_type']:checked").val();
			if( "change" == fee_type ) {
				$("#"+mode+"_fee_type_field").html("<?php _e('Variable','usces'); ?>");
				if( !closed ) {
					$("#welcartpay_fee_fix_table").css("display","none");
					$("#welcartpay_fee_change_table").css("display","");
				}
			} else if( "fix" == fee_type ) {
				$("#"+mode+"_fee_type_field").html("<?php _e('Fixation','usces'); ?>");
				if( !closed ) {
					$("#welcartpay_fee_fix_table").css("display","");
					$("#welcartpay_fee_change_table").css("display","none");
				}
			}
		}
	};

	$("#welcartpay_fee_dialog").dialog({
		autoOpen: false,
		height: 500,
		width: 450,
		modal: true,
		open: function() {
			adminSettlementWelcartPay.openFee( $("#welcartpay_fee_mode").val() );
		},
		buttons: {
			"<?php _e('Settings'); ?>": function() {
				adminSettlementWelcartPay.updateFee( $("#welcartpay_fee_mode").val() );
			},
			"<?php _e('Close'); ?>": function() {
				$(this).dialog('close');
			}
		},
		close: function() {
			adminSettlementWelcartPay.setFeeType( $("#welcartpay_fee_mode").val(), true );
		}
	});

	$(document).on("click", "#conv_fee_setting", function() {
		$("#welcartpay_fee_mode").val( "conv" );
		$("#welcartpay_fee_dialog").dialog( "option", "title", "<?php _e('Online storage agency settlement fee setting','usces'); ?>" );
		$("#welcartpay_fee_dialog").dialog( "open" );
	});

	$(document).on("click", "#atodene_fee_setting", function() {
		$("#welcartpay_fee_mode").val( "atodene" );
		$("#welcartpay_fee_dialog").dialog( "option", "title", "<?php _e('Postpay settlement fee setting','usces'); ?>" );
		$("#welcartpay_fee_dialog").dialog( "open" );
	});

	$(document).on("click", ".fee_type", function() {
		if( "change" == $(this).val() ) {
			$("#welcartpay_fee_fix_table").css("display","none");
			$("#welcartpay_fee_change_table").css("display","");
		} else {
			$("#welcartpay_fee_fix_table").css("display","");
			$("#welcartpay_fee_change_table").css("display","none");
		}
	});

	$(document).on("change", "input[name='fee_first_amount']", function() {
		var rows = $("input[name^='fee_amounts']");
		var first_amount = $("input[name='fee_first_amount']");
		if( 0 == rows.length && $(first_amount).val() != '' ) {
			$("#end_amount").html( parseInt($(first_amount).val()) + 1 );
		} else if( 0 < rows.length && $(first_amount).val() != '' ) {
			$('#amount_0').html( parseInt($(first_amount).val()) + 1 );
		}
	});

	$(document).on("change", "#fee_limit_amount_change", function() {
		if( "change" == $("input[name='fee_type']:checked").val() ) {
			var amount = parseInt($("#end_amount").html());
			var limit = parseInt($("#fee_limit_amount_change").val());
			if( amount >= limit ) {
				alert("<?php _e('A value of the amount of upper limit is dirty.', 'usces'); ?>"+amount+' : '+limit );
			}
		}
	});

	$(document).on("change", "input[name^='fee_amounts']", function() {
		var rows = $("input[name^='fee_amounts']");
		var cnt = $(rows).length;
		var end_amount = $("#end_amount");
		var id = $(rows).index(this);
		if( id >= cnt - 1 ) {
			$(end_amount).html( parseInt($(rows).eq(id).val()) + 1 );
		} else if( id < cnt - 1 ) {
			$("#amount_"+(id+1)).html( parseInt($(rows).eq(id).val()) + 1 );
		}
	});

	$(document).on("click", "#fee_add_row", function() {
		var rows = $("input[name^='fee_amounts']");
		$(rows).unbind("change");
		var first_amount = $("input[name='fee_first_amount']");
		var first_fee = $("input[name='fee_first_fee']");
		var end_amount = $("#end_amount");
		var enf_fee = $("input[name='fee_end_fee']");
		if( 0 == rows.length ) {
			amount = ( $(first_amount).val() == '' ) ? '' : parseInt( $(first_amount).val() ) + 1;
		} else if( 0 < rows.length ) {
			amount = ( $(rows).eq(rows.length - 1).val() == '' ) ? '' : parseInt( $(rows).eq(rows.length-1).val() ) + 1;
		}
		html = '<tr id="row_'+rows.length+'"><td class="cod_f"><span id="amount_'+rows.length+'">'+amount+'</span></td><td class="cod_m"><?php _e(' - ','usces'); ?></td><td class="cod_e"><input name="fee_amounts['+rows.length+']" type="text" class="short_str num" /></td><td class="cod_cod"><input name="fee_fees['+rows.length+']" type="text" class="short_str num" /></td></tr>';
		$("#fee_change_field").append(html);
		rows = $("input[name^='fee_amounts']");
		$(rows).bind("change", function() {
			var cnt = $(rows).length - 1;
			var id = $(rows).index(this);
			if( id >= cnt ) {
				$(end_amount).html( parseInt($(rows).eq(id).val()) + 1 );
			} else if( id < cnt ) {
				$("#amount_"+(id+1)).html( parseInt($(rows).eq(id).val()) + 1 );
			}
		});
	});

	$(document).on("click", "#fee_del_row", function() {
		var rows = $("input[name^='fee_amounts']");
		//$(rows).unbind("change");
		var first_amount = $("input[name='fee_first_amount']");
		var end_amount = $("#end_amount");
		var del_id = rows.length - 1;
		if( 0 < rows.length ) {
			$("#row_"+del_id).remove();
		}
		rows = $("input[name^='fee_amounts']");
		if( 0 == rows.length && $(first_amount).val() != "" ) {
			$(end_amount).html( parseInt($(first_amount).val()) + 1 );
		} else if( 0 < rows.length && $(rows).eq(rows.length-1).val() != "" ) {
			$(end_amount).html( parseInt($(rows).eq(rows.length-1).val()) + 1 );
		}
		//$(rows).bind("change", function() {
		//	var cnt = $(rows).length - 1;
		//	var id = $(rows).index(this);
		///	if( id >= cnt && $(rows).eq(id).val() != "" ) {
		//		$(end_amount).html( parseInt($(rows).eq(id).val()) + 1 );
		//	} else if( id < cnt && $(rows).eq(id).val() != "" ) {
		//		$("#amount_"+(id+1)).html( parseInt($(rows).eq(id).val()) + 1 );
		//	}
		//});
	});

	adminSettlementWelcartPay.setFeeType( "conv", false );
	adminSettlementWelcartPay.setFeeType( "atodene", false );
});
</script>
<?php
			endif;
			break;

		case 'usces_orderlist':
		case 'usces_continue':
			$acting_flg = '';
			$dialog_title = '';

			//受注編集画面・継続課金会員詳細画面
			if( 'usces_orderlist' == $admin_page && ( isset($_GET['order_action']) && ( 'edit' == $_GET['order_action'] || 'editpost' == $_GET['order_action'] || 'newpost' == $_GET['order_action'] ) ) || 
				'usces_continue' == $admin_page && ( isset($_GET['continue_action']) && 'settlement' == $_GET['continue_action'] ) ) {
				$order_id = ( isset($_GET['order_id']) ) ? $_GET['order_id'] : '';
				if( empty($order_id) && isset($_POST['order_id']) ) $order_id = $_POST['order_id'];
				if( empty($order_id) && isset($_REQUEST['order_id']) ) $order_id = $_REQUEST['order_id'];
				if( !empty($order_id) ) {
					$order_data = $usces->get_order_data( $order_id, 'direct' );
					$payment = usces_get_payments_by_name( $order_data['order_payment_name'] );
					if( isset($payment['settlement']) ) {
						$acting_flg = $payment['settlement'];
					}
					if( isset($payment['name']) ) {
						$dialog_title = $payment['name'];
					}
				}
			}
			if( in_array( $acting_flg, $this->pay_method ) ):
?>
<script type="text/javascript">
jQuery(document).ready( function($) {
	adminOrderEdit = {
			<?php if( 'acting_welcart_card' == $acting_flg ): ?>
		getSettlementInfoCard : function() {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			var mode = ( "" != $("#error").val() ) ? "error_welcartpay_card" : "get_welcartpay_card";

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: mode,
					order_id: $("#order_id").val(),
					order_num: $("#order_num").val(),
					trans_id: $("#trans_id").val(),
					member_id: $("#member_id").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		captureSettlementCard : function( amount ) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "capture_welcartpay_card",
					order_id: $("#order_id").val(),
					order_num: $("#order_num").val(),
					trans_id: $("#trans_id").val(),
					member_id: $("#member_id").val(),
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				if( $.trim(data[0]) == "OK" ) {
					$("#settlement-status").html(data[2]);
					$("#responsecd-"+$("#trans_id").val()+"-"+$("#order_num").val()).val("");
				} else {
					$("#responsecd-"+$("#trans_id").val()+"-"+$("#order_num").val()).val(data[0]);
				}
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		changeSettlementCard : function( amount ) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "change_welcartpay_card",
					order_id: $("#order_id").val(),
					order_num: $("#order_num").val(),
					trans_id: $("#trans_id").val(),
					member_id: $("#member_id").val(),
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		deleteSettlementCard : function( amount ) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "delete_welcartpay_card",
					order_id: $("#order_id").val(),
					order_num: $("#order_num").val(),
					trans_id: $("#trans_id").val(),
					member_id: $("#member_id").val(),
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				if( $.trim(data[0]) == "OK" ) {
					$("#settlement-status").html(data[2]);
					$("#responsecd-"+$("#trans_id").val()+"-"+$("#order_num").val()).val("");
				} else {
					$("#responsecd-"+$("#trans_id").val()+"-"+$("#order_num").val()).val(data[0]);
				}
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		authSettlementCard : function( mode, amount ) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: mode+"_welcartpay_card",
					order_id: $("#order_id").val(),
					order_num: $("#order_num").val(),
					trans_id: $("#trans_id").val(),
					member_id: $("#member_id").val(),
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				if( $.trim(data[0]) == "OK" ) {
					$("#settlement-status").html(data[2]);
					$("#responsecd-"+$("#trans_id").val()+"-"+$("#order_num").val()).val("");
				} else {
					$("#responsecd-"+$("#trans_id").val()+"-"+$("#order_num").val()).val(data[0]);
				}
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		}
			<?php elseif( 'acting_welcart_conv' == $acting_flg ): ?>
		getSettlementInfoConv : function() {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "get_welcartpay_conv",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		changeSettlementConv : function( paylimit, amount ) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "change_welcartpay_conv",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					paylimit: paylimit,
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		deleteSettlementConv : function() {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "delete_welcartpay_conv",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				if( $.trim(data[0]) == "OK" ) {
					$("#settlement-response").html(data[2]);
				}
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		},

		addSettlementConv : function( paylimit, amount ) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "add_welcartpay_conv",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					paylimit: paylimit,
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				$("#settlement-response").html(data[1]);
				if( $.trim(data[0]) == "OK" ) {
					$("#settlement-response").html(data[2]);
				}
				$("#settlement-response-loading").html("");
			}).fail( function( retVal ) {
				$("#settlement-response-loading").html("");
			});
			return false;
		}
			<?php endif; ?>
	};

	$("#settlement_dialog").dialog({
		bgiframe: true,
		autoOpen: false,
		height: "auto",
		width: 800,
		resizable: true,
		modal: true,
		buttons: {
			"<?php _e('Close'); ?>": function() {
				$(this).dialog("close");
			}
		},
		open: function() {
			<?php if( 'acting_welcart_card' == $acting_flg ): ?>
			adminOrderEdit.getSettlementInfoCard();
			<?php elseif( 'acting_welcart_conv' == $acting_flg ): ?>
			adminOrderEdit.getSettlementInfoConv();
			<?php endif; ?>
		},
		close: function() {
		}
	});

	$(document).on("click", ".settlement-information", function() {
		var idname = $(this).attr("id");
		var ids = idname.split("-");
		$("#trans_id").val( ids[2] );
		$("#order_num").val( ids[3] );
		if( undefined != $("#responsecd-"+ids[2]+"-"+ids[3]) ) {
			$("#error").val( $("#responsecd-"+ids[2]+"-"+ids[3]).val() );
		} else {
			$("#error").val( "" );
		}
		$("#settlement_dialog").dialog("option", "title", "<?php echo $dialog_title; ?>");
		$("#settlement_dialog").dialog("open");
	});

			<?php if( 'acting_welcart_card' == $acting_flg ): ?>
	$(document).on("click", "#capture-settlement", function() {
		if( !confirm("<?php _e('Are you sure you want to execute sales accounting processing?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.captureSettlementCard( $("#amount_change").val() );
	});

	$(document).on("click", "#delete-settlement", function() {
		if( !confirm("<?php _e('Are you sure you want to cancellation processing?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.deleteSettlementCard( $("#amount_change").val() );
	});

	$(document).on("click", "#change-settlement", function() {
		if( $("#amount_change").val() == $("#amount").val() ) {
			return;
		}
		var amount = $("#amount_change").val();
		if( amount == "" || parseInt(amount) === 0 || !checkNum(amount) ) {
			alert("<?php _e('The spending amount format is incorrect. Please enter with numeric value.','usces'); ?>");
			return;
		}
		if( !confirm("<?php _e('Are you sure you want to change the spending amount?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.changeSettlementCard( $("#amount_change").val() );
	});

	$(document).on("click", "#auth-settlement", function() {
		var amount = $("#amount_change").val();
		if( amount == "" || parseInt(amount) === 0 || !checkNum(amount) ) {
			alert("<?php _e('The spending amount format is incorrect. Please enter with numeric value.','usces'); ?>");
			return;
		}
		if( !confirm("<?php _e('Are you sure you want to execute credit processing?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.authSettlementCard( "auth", $("#amount_change").val() );
	});

	$(document).on("click", "#gathering-settlement", function() {
		var amount = $("#amount_change").val();
		if( amount == "" || parseInt(amount) === 0 || !checkNum(amount) ) {
			alert("<?php _e('The spending amount format is incorrect. Please enter with numeric value.','usces'); ?>");
			return;
		}
		if( !confirm("<?php _e('Are you sure you want to execute credit sales processing?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.authSettlementCard( "gathering", $("#amount_change").val() );
	});

	$(document).on("click", "#reauth-settlement", function() {
		var amount = $("#amount_change").val();
		if( amount == "" || parseInt(amount) === 0 || !checkNum(amount) ) {
			alert("<?php _e('The spending amount format is incorrect. Please enter with numeric value.','usces'); ?>");
			return;
		}
		if( !confirm("<?php _e('Are you sure you want to re-authorization?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.authSettlementCard( "reauth", $("#amount_change").val() );
	});

			<?php elseif( 'acting_welcart_conv' == $acting_flg ): ?>
	$(document).on("click", "#delete-settlement", function() {
		if( !confirm("<?php _e('Are you sure you want to cancellation processing?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.deleteSettlementConv();
	});

	$(document).on("click", "#change-settlement", function() {
		if( ( $("#paylimit_change").val() == $("#paylimit").val() ) &&
			( $("#amount_change").val() == $("#amount").val() ) ) {
			return;
		}
		var paylimit = $("#paylimit_change").val();
		var amount = $("#amount_change").val();
		var today = "<?php echo $this->get_transaction_date(); ?>";
		if( paylimit.length != 8 || !checkNum(paylimit) ) {
			alert("<?php _e('The payment due format is incorrect. Please enter with 8 digit number.','usces'); ?>");
			return;
		}
		if( today > paylimit ) {
			alert("<?php _e('The payment due is incorrect. Date before today cannot be specified.','usces'); ?>");
			return;
		}
		if( amount == "" || parseInt(amount) === 0 || !checkNum(amount) ) {
			alert("<?php _e('The payment amount format is incorrect. Please enter with numeric value.','usces'); ?>");
			return;
		}
		if( !confirm("<?php _e('Are you sure you want to change payment due and payment amount?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.changeSettlementConv( $("#paylimit_change").val(), $("#amount_change").val() );
	});

	$(document).on("click", "#add-settlement", function() {
		//if( ( $("#paylimit_change").val() == $("#paylimit").val() ) &&
		//	( $("#amount_change").val() == $("#amount").val() ) ) {
		//	return;
		//}
		var paylimit = $("#paylimit_change").val();
		var amount = $("#amount_change").val();
		var today = "<?php echo $this->get_transaction_date(); ?>";
		if( paylimit.length != 8 || !checkNum(paylimit) ) {
			alert("<?php _e('The payment due format is incorrect. Please enter with 8 digit number.','usces'); ?>");
			return;
		}
		if( today > paylimit ) {
			alert("<?php _e('The payment due is incorrect. Date before today cannot be specified.','usces'); ?>");
			return;
		}
		if( amount == "" || parseInt(amount) === 0 || !checkNum(amount) ) {
			alert("<?php _e('The payment amount format is incorrect. Please enter with numeric value.','usces'); ?>");
			return;
		}
		if( !confirm("<?php _e('Are you sure you want to execute the registration processing?','usces'); ?>") ) {
			return;
		}
		adminOrderEdit.addSettlementConv( $("#paylimit_change").val(), $("#amount_change").val() );
	});

			<?php endif; ?>
			<?php if( 'usces_continue' == $admin_page ): ?>
	adminContinuation = {
		update : function() {
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "usces_admin_ajax",
					mode: "continuation_update",
					member_id: $("#member_id").val(),
					order_id: $("#order_id").val(),
					contracted_year: $("#contracted-year option:selected").val(),
					contracted_month: $("#contracted-month option:selected").val(),
					contracted_day: $("#contracted-day option:selected").val(),
					charged_year: $("#charged-year option:selected").val(),
					charged_month: $("#charged-month option:selected").val(),
					charged_day: $("#charged-day option:selected").val(),
					price: $("#price").val(),
					status: $("#dlseller-status").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function( retVal, dataType ) {
				var data = retVal.split("#usces#");
				if( $.trim(data[0]) == "OK" ) {
					adminOperation.setActionStatus( "success", "<?php _e( 'The update was completed.','usces' ); ?>" );
				} else {
					mes = ( data[1] != "" ) ? data[1] : "<?php _e( 'failure in update','usces' ); ?>";
					adminOperation.setActionStatus( "error", mes );
				}
			}).fail( function( retVal ) {
				adminOperation.setActionStatus( "error", "<?php _e( 'failure in update','usces' ); ?>" );
			});
			return false;
		}
	};

	$("#price").bind("change", function(){ usces_check_money($(this)); });
	$(document).on("click", "#continuation-update", function() {
		var status = $("#dlseller-status option:selected").val();
		if( status == "continuation" ) {
			var year = $("#charged-year option:selected").val();
			var month = $("#charged-month option:selected").val();
			var day = $("#charged-day option:selected").val();
			if( year == 0 || month == 0 || day == 0 ) {
				alert("<?php _e( 'Data have deficiency.','usces' ); ?>");
				$("#charged-year").focus();
				return;
			}

			if( $("#price").val() == "" || parseInt($("#price").val()) == 0 ) {
				alert("<?php printf( __("Input the %s",'usces'), __('Amount', 'dlseller') ); ?>");
				$("#price").focus();
				return;
			}
		}

		if( !confirm("<?php _e('Are you sure you want to update the settings?','usces'); ?>") ) {
			return;
		}
		adminContinuation.update();
	});
			<?php endif; ?>
});
</script>
<?php
			endif;
			break;
		endswitch;
	}

	/**********************************************
	* 管理画面決済処理
	* @param  -
	* @return -
	***********************************************/
	public function admin_ajax() {
		global $usces;

		switch( $_POST['mode'] ) {
		//取引参照
		case 'get_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) ) {
				die("NG#usces#");
			}
			$res = '';
			$log_data = array();
			if( $trans_id == '9999999999' ) {
				$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
				$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
				if( 'OK' == $response_member['ResponseCd'] ) {
					$order_data = $usces->get_order_data( $order_id, 'direct' );
					$total_full_price = $order_data['order_item_total_price'] - $order_data['order_usedpoint'] + $order_data['order_discount'] + $order_data['order_shipping_charge'] + $order_data['order_cod_fee'] + $order_data['order_tax'];
					$res .= '<div class="welcart-settlement-admin card-new">'.__('New','usces').'</div>';
					$res .= '<table class="welcart-settlement-admin-table">';
					$res .= '<tr><th>'.__('Spending amount','usces').'</th>
						<td><input type="text" id="amount_change" value="'.$total_full_price.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$total_full_price.'" /></td>
						</tr>';
					$res .= '</table>';
					$res .= '<div class="welcart-settlement-admin-button">';
					$res .= '<input id="auth-settlement" type="button" class="button" value="'.__('Credit','usces').'" />';//与信
					$res .= '<input id="gathering-settlement" type="button" class="button" value="'.__('Credit sales','usces').'" />';//与信売上計上
					$res .= '</div>';
				} else {
					$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';
					$res .= '<div class="welcart-settlement-admin-error">';
					$res .= '<div><span class="message">'.__('Credit card information not registered','usces').'</span></div>';//カード情報未登録
					$res .= '</div>';
				}
				die("OK#usces#".$res);
			} else {
				if( $order_num == '1' ) {
					$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
				} else {
					$log_data = $this->get_acting_log( $order_id, $order_id.'_'.$trans_id );
					$acting_data = unserialize($log_data[0]['log']);
				}
				$operateid = ( isset($acting_data['OperateId']) ) ? $acting_data['OperateId'] : $this->get_acting_first_operateid( $order_id.'_'.$trans_id );
				$acting_opts = $this->get_acting_settings();
				$TransactionDate = $this->get_transaction_date();
				$param_list = array();
				$params = array();
				$param_list['MerchantId'] = $acting_opts['merchant_id'];
				$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
				$param_list['TransactionDate'] = $TransactionDate;
				$param_list['MerchantFree1'] = $trans_id;
				$param_list['MerchantFree2'] = 'acting_welcart_card';
				$param_list['MerchantFree3'] = $this->merchantfree3;
				$param_list['TenantId'] = $acting_opts['tenant_id'];
				$params['send_url'] = $acting_opts['send_url'];
				$params['param_list'] = array_merge( $param_list,
					array(
						'OperateId' => '1Search',
						'ProcessId' => $acting_data['ProcessId'],
						'ProcessPass' => $acting_data['ProcessPass']
					)
				);
				$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
				if( 'OK' == $response_data['ResponseCd'] ) {
					$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id );
					if( isset($latest_log['OperateId']) ) {
						$class = ' card-'.mb_strtolower(substr($latest_log['OperateId'],1));
						$status_name = $this->get_operate_name( $latest_log['OperateId'] );
						$res .= '<div class="welcart-settlement-admin'.$class.'">'.$status_name.'</div>';
						$res .= '<table class="welcart-settlement-admin-table">';
						if( isset($response_data['Amount']) ) {
							$res .= '<tr><th>'.__('Spending amount','usces').'</th>
								<td><input type="text" id="amount_change" value="'.$response_data['Amount'].'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$response_data['Amount'].'" /></td>
								</tr>';
						}
						if( isset($response_data['SalesDate']) ) {
							$res .= '<tr><th>'.__('Recorded sales date','usces').'</th><td>'.$response_data['SalesDate'].'</td></tr>';
						}
						$res .= '</table>';
						$res .= '<div class="welcart-settlement-admin-button">';
						if( '1Delete' == $latest_log['OperateId'] ) {
							$res .= '<input id="reauth-settlement" type="button" class="button" value="'.__('Re-authorization','usces').'" />';//再オーソリ
						} else {
							if( '1Auth' == $operateid && '1Capture' != $latest_log['OperateId'] ) {
								$res .= '<input id="reauth-settlement" type="button" class="button" value="'.__('Re-authorization','usces').'" />';//再オーソリ
								$res .= '<input id="capture-settlement" type="button" class="button" value="'.__('Sales recorded','usces').'" />';//売上計上
							}
							if( '1Delete' != $latest_log['OperateId'] ) {
								$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
							}
							if( '1Change' != $latest_log['OperateId'] ) {
								$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change spending amount','usces').'" />';//利用額変更
							}
						}
						$res .= '</div>';
					}
				} else {
					$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';
					$res .= '<div class="welcart-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)responsecd as $cd ) {
						$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
					}
					$res .= '</div>';
					usces_log('[WelcartPay] 1Search connection NG : '.print_r($response_data,true), 'acting_transaction.log');
				}
				$res .= $this->settlement_history( $order_id.'_'.$trans_id );
				die($response_data['ResponseCd']."#usces#".$res);
			}
			break;

		//売上計上
		case 'capture_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) ) {
				die("NG#usces#");
			}
			$res = '';
			$status = '';
			$log_data = array();
			if( $order_num == '1' ) {
				$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
			} else {
				$log_data = $this->get_acting_log( $order_id, $order_id.'_'.$trans_id );
				$acting_data = unserialize($log_data[0]['log']);
			}
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_card';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
			if( 'OK' == $response_member['ResponseCd'] ) {
				$param_list['KaiinId'] = $response_member['KaiinId'];
				$param_list['KaiinPass'] = $response_member['KaiinPass'];
			}
			$params['send_url'] = $acting_opts['send_url'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '1Capture',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass'],
					'SalesDate' => $TransactionDate
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$class = ' card-'.mb_strtolower(substr($response_data['OperateId'],1));
				$status_name = $this->get_operate_name( $response_data['OperateId'] );
				$res .= '<div class="welcart-settlement-admin'.$class.'">'.$status_name.'</div>';
				$res .= '<table class="welcart-settlement-admin-table">';
				$res .= '<tr><th>'.__('Spending amount','usces').'</th>
					<td><input type="text" id="amount_change" value="'.$amount.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$amount.'" /></td>
					</tr>';
				if( isset($response_data['SalesDate']) ) {
					$res .= '<tr><th>'.__('Recorded sales date','usces').'</th><td>'.$response_data['SalesDate'].'</td></tr>';
				}
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
				$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change spending amount','usces').'" />';//利用額変更
				$res .= '</div>';
				$status = '<span class="acting-status'.$class.'">'.$status_name.'</span>';
			} else {
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 1Capture connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res."#usces#".$status);
			break;

		//取消/返品
		case 'delete_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) ) {
				die("NG#usces#");
			}
			$res = '';
			$status = '';
			$log_data = array();
			if( $order_num == '1' ) {
				$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
			} else {
				$log_data = $this->get_acting_log( $order_id, $order_id.'_'.$trans_id );
				$acting_data = unserialize($log_data[0]['log']);
			}
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_card';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
			if( 'OK' == $response_member['ResponseCd'] ) {
				$param_list['KaiinId'] = $response_member['KaiinId'];
				$param_list['KaiinPass'] = $response_member['KaiinPass'];
			}
			$params['send_url'] = $acting_opts['send_url'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '1Delete',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass']
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$class = ' card-'.mb_strtolower(substr($response_data['OperateId'],1));
				$status_name = $this->get_operate_name( $response_data['OperateId'] );
				$res .= '<div class="welcart-settlement-admin'.$class.'">'.$status_name.'</div>';
				$res .= '<table class="welcart-settlement-admin-table">';
				$res .= '<tr><th>'.__('Spending amount','usces').'</th>
					<td><input type="text" id="amount_change" value="'.$amount.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$amount.'" /></td>
					</tr>';
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				$res .= '<input id="reauth-settlement" type="button" class="button" value="'.__('Re-authorization','usces').'" />';//再オーソリ
				$res .= '</div>';
				$status = '<span class="acting-status'.$class.'">'.$status_name.'</span>';
			} else {
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 1Delete connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res."#usces#".$status);
			break;

		//利用額変更
		case 'change_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) || $amount == '' ) {
				die("NG#usces#");
			}
			$res = '';
			$log_data = array();
			if( $order_num == '1' ) {
				$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
			} else {
				$log_data = $this->get_acting_log( $order_id, $order_id.'_'.$trans_id );
				$acting_data = unserialize($log_data[0]['log']);
			}
			$operateid = ( isset($acting_data['OperateId']) ) ? $acting_data['OperateId'] : $this->get_acting_first_operateid( $order_id.'_'.$trans_id );
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_card';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
			if( 'OK' == $response_member['ResponseCd'] ) {
				$param_list['KaiinId'] = $response_member['KaiinId'];
				$param_list['KaiinPass'] = $response_member['KaiinPass'];
			}
			$params['send_url'] = $acting_opts['send_url'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '1Change',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass'],
					'Amount' => $amount
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$class = ' card-'.mb_strtolower(substr($operateid,1));
				$status_name = $this->get_operate_name( $operateid );
				$res .= '<div class="welcart-settlement-admin'.$class.'">'.$status_name.'</div>';
				$res .= '<table class="welcart-settlement-admin-table">';
				$res .= '<tr><th>'.__('Spending amount','usces').'</th>
					<td><input type="text" id="amount_change" value="'.$amount.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$amount.'" /></td>
					</tr>';
				if( isset($response_data['SalesDate']) ) {
					$res .= '<tr><th>'.__('Recorded sales date','usces').'</th><td>'.$response_data['SalesDate'].'</td></tr>';
				}
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				if( '1Gathering' != $operateid ) {
					$res .= '<input id="capture-settlement" type="button" class="button" value="'.__('Sales recorded','usces').'" />';//売上計上
				}
				$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
				$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change spending amount','usces').'" />';//利用額変更
				$res .= '</div>';
			} else {
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 1Change connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res);
			break;

		//与信
		case 'auth_welcartpay_card':
		//与信売上計上
		case 'gathering_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) || $amount == '' ) {
				die("NG#usces#");
			}
			$res = '';
			$status = '';
			$log_data = array();
			if( $trans_id == '9999999999' ) {
				$trans_id = usces_acting_key();
			} else {
				if( $order_num == '1' ) {
					$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
				} else {
					$log_data = $this->get_acting_log( $order_id, $order_id.'_'.$trans_id );
					$acting_data = unserialize($log_data[0]['log']);
				}
			}
			$operateid = ( 'auth_welcartpay_card' == $_POST['mode'] ) ? '1Auth' : '1Gathering';
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_card';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
			if( 'OK' == $response_member['ResponseCd'] ) {
				$params['send_url'] = $acting_opts['send_url'];
				$params['param_list'] = array_merge( $param_list,
					array(
						'KaiinId' => $response_member['KaiinId'],
						'KaiinPass' => $response_member['KaiinPass'],
						'OperateId' => $operateid,
						'PayType' => '01',
						'Amount' => $amount
					)
				);
				$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
				if( 'OK' == $response_data['ResponseCd'] ) {
					if( $order_num == '1' ) {
						$cardlast4 = substr($response_member['CardNo'], -4);
						$expyy = substr(date_i18n('Y', current_time('timestamp')), 0, 2).substr($response_member['CardExp'], 0, 2);
						$expmm = substr($response_member['CardExp'], 2, 2);
						$response_data['acting'] = 'welcart_card';
						$response_data['CardNo'] = $cardlast4;
						$response_data['CardExp'] = $expyy.'/'.$expmm;
						$usces->set_order_meta_value( 'acting_welcart_card', serialize($response_data), $order_id );
						$usces->set_order_meta_value( 'trans_id', $trans_id, $order_id );
						$usces->set_order_meta_value( 'wc_trans_id', $trans_id, $order_id );
					} else {
						if( $log_data ) {
							$this->update_acting_log( $response_data, $order_id.'_'.$trans_id );
						}
					}

					$class = ' card-'.mb_strtolower(substr($operateid,1));
					$status_name = $this->get_operate_name( $operateid );
					$res .= '<div class="welcart-settlement-admin'.$class.'">'.$status_name.'</div>';
					$res .= '<table class="welcart-settlement-admin-table">';
					$res .= '<tr><th>'.__('Spending amount','usces').'</th>
						<td><input type="text" id="amount_change" value="'.$amount.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$amount.'" /></td>
						</tr>';
					$res .= '</table>';
					$res .= '<div class="welcart-settlement-admin-button">';
					if( '1Gathering' != $operateid ) {
						$res .= '<input id="capture-settlement" type="button" class="button" value="'.__('Sales recorded','usces').'" />';//売上計上
					}
					$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
					$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change spending amount','usces').'" />';//利用額変更
					$res .= '</div>';
					$status = '<span class="acting-status'.$class.'">'.$status_name.'</span>';
				} else {
					$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';//エラー
					$res .= '<div class="welcart-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)$responsecd as $cd ) {
						$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
					}
					$res .= '</div>';
					usces_log('[WelcartPay] '.$operateid.' connection NG : '.print_r($response_data,true), 'acting_transaction.log');
				}
				do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
				$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
				$res .= $this->settlement_history( $order_id.'_'.$trans_id );
				die($response_data['ResponseCd']."#usces#".$res."#usces#".$status);
			} else {
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_member['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 4MemRefM connection NG : '.print_r($response_member,true), 'acting_transaction.log');
				die($response_member['ResponseCd']."#usces#".$res);
			}
			break;

		//再オーソリ
		case 'reauth_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$res = '';
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) || $amount == '' ) {
				die("NG#usces#");
			}
			$res = '';
			$status = '';
			if( $order_num == '1' ) {
				$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_card', $order_id ) );
			} else {
				$log_data = $this->get_acting_log( $order_id, $order_id.'_'.$trans_id );
				$acting_data = unserialize($log_data[0]['log']);
			}
			$operateid = ( isset($acting_data['OperateId']) ) ? $acting_data['OperateId'] : $this->get_acting_first_operateid( $order_id.'_'.$trans_id );
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_card';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
			if( 'OK' == $response_member['ResponseCd'] ) {
				$param_list['KaiinId'] = $response_member['KaiinId'];
				$param_list['KaiinPass'] = $response_member['KaiinPass'];
			}
			if( '1Gathering' == $operateid ) {
				$param_list['SalesDate'] = $TransactionDate;
			}
			$params['send_url'] = $acting_opts['send_url'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '1ReAuth',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass'],
					'Amount' => $amount
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$acting_data['TransactionId'] = $response_data['TransactionId'];
				$acting_data['TransactionDate'] = $response_data['TransactionDate'];
				$acting_data['ProcessId'] = $response_data['ProcessId'];
				$acting_data['ProcessPass'] = $response_data['ProcessPass'];
				$usces->set_order_meta_value( 'acting_welcart_card', serialize($acting_data), $order_id );

				$class = ' card-'.mb_strtolower(substr($operateid,1));
				$status_name = $this->get_operate_name( $operateid );
				$res .= '<div class="welcart-settlement-admin'.$class.'">'.$status_name.'</div>';
				$res .= '<table class="welcart-settlement-admin-table">';
				$res .= '<tr><th>'.__('Spending amount','usces').'</th>
					<td><input type="text" id="amount_change" value="'.$amount.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$amount.'" /></td>
					</tr>';
				if( isset($response_data['SalesDate']) ) {
					$res .= '<tr><th>'.__('Recorded sales date','usces').'</th><td>'.$response_data['SalesDate'].'</td></tr>';
				}
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				if( '1Gathering' != $operateid ) {
					$res .= '<input id="capture-settlement" type="button" class="button" value="'.__('Sales recorded','usces').'" />';//売上計上
				}
				$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
				$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change spending amount','usces').'" />';//利用額変更
				$res .= '</div>';
				$status = '<span class="acting-status'.$class.'">'.$status_name.'</span>';
			} else {
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 1ReAuth connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res."#usces#".$status);
			break;

		//決済エラー
		case 'error_welcartpay_card':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$order_num = ( isset($_POST['order_num']) ) ? $_POST['order_num'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			if( empty($order_id) || empty($order_num) || empty($trans_id) ) {
				die("NG#usces#");
			}
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$response_member = $this->escott_member_reference( $member_id );//e-SCOTT 会員照会
			if( 'OK' == $response_member['ResponseCd'] ) {
				$order_data = $usces->get_order_data( $order_id, 'direct' );
				$total_full_price = $order_data['order_item_total_price'] - $order_data['order_usedpoint'] + $order_data['order_discount'] + $order_data['order_shipping_charge'] + $order_data['order_cod_fee'] + $order_data['order_tax'];
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Repayment','usces').'</div>';//再決済
				$res .= '<table class="welcart-settlement-admin-table">';
				$res .= '<tr><th>'.__('Spending amount','usces').'</th>
					<td><input type="text" id="amount_change" value="'.$total_full_price.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$total_full_price.'" /></td>
					</tr>';
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				$res .= '<input id="auth-settlement" type="button" class="button" value="'.__('Credit','usces').'" />';//与信
				$res .= '<input id="gathering-settlement" type="button" class="button" value="'.__('Credit sales','usces').'" />';//与信売上計上
				$res .= '</div>';
				$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			} else {
				$res .= '<div class="welcart-settlement-admin card-error">'.__('Settlement error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$res .= '<div><span class="message">'.__('Credit card information not registered','usces').'</span></div>';//カード情報未登録
				$res .= '</div>';
			}
			die("OK#usces#".$res);
			break;

		//継続課金情報更新
		case 'continuation_update':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$res = '';
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$member_id = ( isset($_POST['member_id']) ) ? $_POST['member_id'] : '';
			$contracted_year = ( isset($_POST['contracted_year']) ) ? $_POST['contracted_year'] : '';
			$contracted_month = ( isset($_POST['contracted_month']) ) ? $_POST['contracted_month'] : '';
			$contracted_day = ( isset($_POST['contracted_day']) ) ? $_POST['contracted_day'] : '';
			$charged_year = ( isset($_POST['charged_year']) ) ? $_POST['charged_year'] : '';
			$charged_month = ( isset($_POST['charged_month']) ) ? $_POST['charged_month'] : '';
			$charged_day = ( isset($_POST['charged_day']) ) ? $_POST['charged_day'] : '';
			$price = ( isset($_POST['price']) ) ? $_POST['price'] : 0;
			$status = ( isset($_POST['status']) ) ? $_POST['status'] : '';

			if( version_compare( WCEX_DLSELLER_VERSION, '3.0-beta', '<=' ) ) {
				$continue_data = unserialize( $usces->get_member_meta_value( 'continuepay_'.$order_id, $member_id ) );
			} else {
				$continue_data = $this->get_continuation_data( $order_id, $member_id );
			}
			if( !$continue_data ) {
				die("NG#usces#");
			}

			//継続中→停止
			if( $continue_data['status'] == 'continuation' && $status == 'cancellation' ) {
				if( version_compare( WCEX_DLSELLER_VERSION, '3.0-beta', '<=' ) ) {
					$continue_data['status'] = 'cancellation';
					$usces->set_member_meta_value( 'continuepay_'.$order_id, serialize($continue_data), $member_id );
				} else {
					$this->update_continuation_data( $order_id, $member_id, $continue_data, true );
				}

			} else {
				if( !empty($contracted_year) && !empty($contracted_month) && !empty($contracted_day) ) {
					$contracted_date = ( empty($continue_data['contractedday']) ) ? dlseller_next_contracting( $order_id ) : $continue_data['contractedday'];
					if( $contracted_date ) {
						$new_contracted_date = $contracted_year.'-'.$contracted_month.'-'.$contracted_day;
						if( !$this->isdate($new_contracted_date) ) {
							die("NG#usces#".__('Next contract renewal date is incorrect.','dlseller'));
						}
					}
				} else {
					$new_contracted_date = '';
				}
				$new_charged_date = $charged_year.'-'.$charged_month.'-'.$charged_day;
				if( !$this->isdate($new_charged_date) ) {
					die("NG#usces#".__('Next settlement date is incorrect.','dlseller'));
				}
				$charged_date = ( empty($continue_data['chargedday']) ) ? dlseller_next_charging( $order_id ) : $continue_data['chargedday'];
				if( $new_charged_date < $charged_date ) {
					die("NG#usces#".sprintf(__("The next settlement date must be after %s.",'dlseller'), $charged_date));
				}
				$continue_data['contractedday'] = $new_contracted_date;
				$continue_data['chargedday'] = $new_charged_date;
				$continue_data['price'] = usces_crform( $price, false, false, 'return', false );
				$continue_data['status'] = $status;
				if( version_compare( WCEX_DLSELLER_VERSION, '3.0-beta', '<=' ) ) {
					$usces->set_member_meta_value( 'continuepay_'.$order_id, serialize($continue_data), $member_id );
				} else {
					$this->update_continuation_data( $order_id, $member_id, $continue_data );
				}
			}
			die("OK#usces#");
			break;

		//オンライン収納代行データ登録
		case 'add_welcartpay_conv':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$paylimit = ( isset($_POST['paylimit']) ) ? $_POST['paylimit'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($trans_id) || $paylimit == '' || $amount == '' ) {
				die("NG#usces#");
			}
			$res = '';
			$status = '';
			$order_data = $usces->get_order_data( $order_id, 'direct' );
			$NameKanji = urlencode( $order_data['order_name1'].$order_data['order_name2'] );
			$NameKana = ( !empty($order_data['order_name3']) ) ? urlencode( $order_data['order_name3'].$order_data['order_name4'] ) : $NameKanji;
			$TelNo = urlencode( $order_data['order_tel'] );
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_conv';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '2Add',
					'PayLimit' => $paylimit.'2359',
					'Amount' => $amount,
					'NameKanji' => $NameKanji,
					'NameKana' => $NameKana,
					'TelNo' => $TelNo,
					'ReturnURL' => urlencode( home_url('/') )
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$response_data['acting'] = 'welcart_conv';
				$response_data['PayLimit'] = $params['param_list']['PayLimit'];
				$response_data['Amount'] = $params['param_list']['Amount'];
				$usces->set_order_meta_value( 'acting_welcart_conv', serialize($response_data), $order_id );
				$FreeArea = trim($response_data['FreeArea']);
				$url = add_query_arg( array( 'code'=>$FreeArea, 'rkbn'=>2 ), $acting_opts['redirect_url_conv'] );
				$usces->set_order_meta_value( 'welcart_conv_url', $url, $order_id );

				$res .= '<div class="welcart-settlement-admin conv-noreceipt">'.__('Unpaid','usces').'</div>';//未入金
				$res .= '<table class="welcart-settlement-admin-table">';
				$res .= '<tr><th>'.__('Payment due','usces').'</th>
					<td><input type="text" id="paylimit_change" value="'.$paylimit.'" style="ime-mode:disabled" size="10" /><input type="hidden" id="paylimit" value="'.$paylimit.'" /></td>
					</tr>';
				$res .= '<tr><th>'.__('Payment amount','usces').'</th>
					<td><input type="text" id="amount_change" value="'.$amount.'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$amount.'" /></td>
					</tr>';
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
				$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change').'" />';//変更
				$res .= '</div>';
				$status = '<span class="acting-status conv-noreceipt">'.__('Unpaid','usces').'</span>';
			} else {
				$res .= '<div class="welcart-settlement-admin conv-error">'.__('Error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 2Add connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res."#usces#".$status);
			break;

		//オンライン収納代行データ変更
		case 'change_welcartpay_conv':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			$paylimit = ( isset($_POST['paylimit']) ) ? $_POST['paylimit'] : '';
			$amount = ( isset($_POST['amount']) ) ? $_POST['amount'] : '';
			if( empty($order_id) || empty($trans_id) || $paylimit == '' || $amount == '' ) {
				die("NG#usces#");
			}
			$res = '';
			$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_conv', $order_id ) );
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_conv';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '2Chg',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass'],
					'PayLimit' => $paylimit.'2359',
					'Amount' => $amount
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$acting_data['PayLimit'] = $params['param_list']['PayLimit'];
				$acting_data['Amount'] = $params['param_list']['Amount'];
				$usces->set_order_meta_value( 'acting_welcart_conv', serialize($acting_data), $order_id );
				$FreeArea = trim($response_data['FreeArea']);
				$url = add_query_arg( array( 'code'=>$FreeArea, 'rkbn'=>2 ), $acting_opts['redirect_url_conv'] );
				$usces->set_order_meta_value( 'welcart_conv_url', $url, $order_id );

				$res .= '<div class="welcart-settlement-admin conv-noreceipt">'.__('Unpaid','usces').'</div>';//未入金
				$res .= '<table class="welcart-settlement-admin-table">';
				if( isset($acting_data['PayLimit']) ) {
					$res .= '<tr><th>'.__('Payment due','usces').'</th><td>'.$acting_data['PayLimit'].'</td></tr>';
				}
				if( isset($acting_data['Amount']) ) {
					$res .= '<tr><th>'.__('Payment amount','usces').'</th><td>'.$acting_data['Amount'].'</td></tr>';
				}
				$res .= '</table>';
				$res .= '<div class="welcart-settlement-admin-button">';
				$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
				$res .= '</div>';
			} else {
				$res .= '<div class="welcart-settlement-admin conv-error">'.__('Error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 2Chg connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res);
			break;

		//オンライン収納代行データ削除
		case 'delete_welcartpay_conv':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			if( empty($order_id) || empty($trans_id) ) {
				die("NG#usces#");
			}
			$res = '';
			$status = '';
			$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_conv', $order_id ) );
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_conv';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '2Del',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass']
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				$res .= '<div class="welcart-settlement-admin conv-del">'.__('Canceled','usces').'</div>';//取消済み
				$res .= '<table class="welcart-settlement-admin-table">';
				if( isset($acting_data['PayLimit']) ) {
					$paylimit = substr($acting_data['PayLimit'],0,8);
					$res .= '<tr><th>'.__('Payment due','usces').'</th><td>'.$paylimit.'</td></tr>';
					//$res .= '<tr><th>'.__('Payment due','usces').'</th>
					//	<td><input type="text" id="paylimit_change" value="'.$paylimit.'" style="ime-mode:disabled" size="10" /><input type="hidden" id="paylimit" value="'.$paylimit.'" /></td>
					//	</tr>';
				}
				if( isset($acting_data['Amount']) ) {
					$res .= '<tr><th>'.__('Payment amount','usces').'</th><td>'.$acting_data['Amount'].'</td></tr>';
					//$res .= '<tr><th>'.__('Payment amount','usces').'</th>
					//	<td><input type="text" id="amount_change" value="'.$acting_data['Amount'].'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$acting_data['Amount'].'" /></td>
					//	</tr>';
				}
				$res .= '</table>';
				//$res .= '<div class="welcart-settlement-admin-button">';
				//$res .= '<input id="add-settlement" type="button" class="button" value="'.__('Register').'" />';
				//$res .= '</div>';
				$status = '<span class="acting-status conv-del">'.__('Canceled','usces').'</span>';
			} else {
				$res .= '<div class="welcart-settlement-admin conv-error">'.__('Error','usces').'</div>';//エラー
				$res .= '<div class="welcart-settlement-admin-error">';
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
				}
				$res .= '</div>';
				usces_log('[WelcartPay] 2Del connection NG : '.print_r($response_data,true), 'acting_transaction.log');
			}
			do_action( 'usces_action_admin_'.$_POST['mode'], $response_data, $order_id, $trans_id );
			$this->save_acting_history_log( $response_data, $order_id.'_'.$trans_id );
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res."#usces#".$status);
			break;

		//オンライン収納代行データ入金結果参照
		case 'get_welcartpay_conv':
			check_admin_referer( 'order_edit', 'wc_nonce' );
			$order_id = ( isset($_POST['order_id']) ) ? $_POST['order_id'] : '';
			$trans_id = ( isset($_POST['trans_id']) ) ? $_POST['trans_id'] : '';
			if( empty($order_id) || empty($trans_id) ) {
				die("NG#usces#");
			}
			$res = '';
			$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_conv', $order_id ) );
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $trans_id;
			$param_list['MerchantFree2'] = 'acting_welcart_conv';
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '2Ref',
					'ProcessId' => $acting_data['ProcessId'],
					'ProcessPass' => $acting_data['ProcessPass']
				)
			);
			$response_data = $this->connection( $params );
//usces_log(print_r($response_data,true),"test.log");
			if( 'OK' == $response_data['ResponseCd'] ) {
				if( isset($response_data['NyukinDate']) ) {
					$res .= '<div class="welcart-settlement-admin conv-receipted">'.__('Paid','usces').'</div>';//入金済
					$res .= '<table class="welcart-settlement-admin-table">';
					if( isset($response_data['RecvNum']) ) {
						$res .= '<tr><th>'.__('Receipt number','usces').'</th><td>'.$response_data['RecvNum'].'</td></tr>';//受付番号
					}
					if( isset($response_data['NyukinDate']) ) {
						$res .= '<tr><th>'.__('Deposit date','usces').'</th><td>'.$response_data['NyukinDate'].'</td></tr>';//入金日時
					}
					if( isset($response_data['CvsCd']) ) {
						$cvs_name = $this->get_cvs_name($response_data['CvsCd']);
						$res .= '<tr><th>'.__('Convenience store code','usces').'</th><td>'.$cvs_name.'</td></tr>';//収納機関コード
					}
					if( isset($response_data['TenantCd']) ) {
						$res .= '<tr><th>'.__('Tenant code','usces').'</th><td>'.$response_data['TenantCd'].'</td></tr>';//店舗コード
					}
					if( isset($response_data['Amount']) ) {
						$res .= '<tr><th>'.__('Payment amount','usces').'</th><td>'.$response_data['Amount'].__(usces_crcode('return'),'usces').'</td></tr>';
					}
					$res .= '</table>';
				} else {
					$paylimit = substr($acting_data['PayLimit'],0,8);
					$expiration = $this->check_paylimit( $order_id, $trans_id );
					$res .= '<div class="welcart-settlement-admin conv-noreceipt">'.__('Unpaid','usces');//未入金
					if( $expiration ) {
						$res .= __('(Expired)','usces');//（期限切れ）
					}
					$res .= '</div>';
					$res .= '<table class="welcart-settlement-admin-table">';
					$res .= '<tr><th>'.__('Payment due','usces').'</th>
						<td><input type="text" id="paylimit_change" value="'.$paylimit.'" style="ime-mode:disabled" size="10" /><input type="hidden" id="paylimit" value="'.$paylimit.'" /></td>
						</tr>';
					$res .= '<tr><th>'.__('Payment amount','usces').'</th>
						<td><input type="text" id="amount_change" value="'.$acting_data['Amount'].'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$acting_data['Amount'].'" /></td>
						</tr>';
					$res .= '</table>';
					$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id );
					if( isset($latest_log['OperateId']) ) {
						$res .= '<div class="welcart-settlement-admin-button">';
						if( '2Del' != $latest_log['OperateId'] ) {
							$res .= '<input id="delete-settlement" type="button" class="button" value="'.__('Unregister','usces').'" />';//取消
						}
						if( '2Chg' != $latest_log['OperateId'] ) {
							$res .= '<input id="change-settlement" type="button" class="button" value="'.__('Change').'" />';//変更
						}
						$res .= '</div>';
					}
				}
			} else {
				//$deleted = $this->check_deleted( $order_id.'_'.$trans_id );
				//if( $deleted && 'K12' == $response_data['ResponseCd'] ) {
				$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id );
				if( isset($latest_log['OperateId']) && '2Del' == $latest_log['OperateId'] && 'K12' == $response_data['ResponseCd'] ) {
					$paylimit = substr($acting_data['PayLimit'],0,8);
					$res .= '<div class="welcart-settlement-admin conv-del">'.__('Canceled','usces').'</div>';//取消済み
					$res .= '<table class="welcart-settlement-admin-table">';
					$res .= '<tr><th>'.__('Payment due','usces').'</th>
						<td><input type="text" id="paylimit_change" value="'.$paylimit.'" style="ime-mode:disabled" size="10" /><input type="hidden" id="paylimit" value="'.$paylimit.'" /></td>
						</tr>';
					$res .= '<tr><th>'.__('Payment amount','usces').'</th>
						<td><input type="text" id="amount_change" value="'.$acting_data['Amount'].'" style="text-align:right;ime-mode:disabled" size="10" />'.__(usces_crcode('return'),'usces').'<input type="hidden" id="amount" value="'.$acting_data['Amount'].'" /></td>
						</tr>';
					$res .= '</table>';
					$res .= '<div class="welcart-settlement-admin-button">';
					$res .= '<input id="add-settlement" type="button" class="button" value="'.__('Register').'" />';//登録
					$res .= '</div>';
				} else {
					$res .= '<div class="welcart-settlement-admin conv-error">'.__('Error','usces').'</div>';//エラー
					$res .= '<div class="welcart-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)$responsecd as $cd ) {
						$res .= '<div><span class="code">'.$cd.'</span> : <span class="message">'.$this->response_message( $cd ).'</span></div>';
					}
					$res .= '</div>';
					usces_log('[WelcartPay] 2Ref connection NG : '.print_r($response_data,true), 'acting_transaction.log');
				}
			}
			$res .= $this->settlement_history( $order_id.'_'.$trans_id );
			die($response_data['ResponseCd']."#usces#".$res);
			break;
		}
	}

	/**********************************************
	* 決済ログ出力
	* @param  $log $log_key
	* @return $res
	***********************************************/
	private function save_acting_log( $log, $log_key ) {
		global $wpdb;

		$log_table_name = $wpdb->prefix.'usces_log';
		$datetime = current_time('mysql');
		$query = $wpdb->prepare( "INSERT INTO {$log_table_name} ( `datetime`, `log`, `log_type`, `log_key` ) VALUES ( %s, %s, %s, %s )",
			$datetime,
			serialize($log),
			'acting_welcart',
			$log_key
		);
		$res = $wpdb->query( $query );
		return $res;
	}

	/**********************************************
	* 決済ログ取得
	* @param  $order_id ($log_key)
	* @return array $log_data
	***********************************************/
	private function get_acting_log( $order_id, $log_key = '' ) {
		global $wpdb;

		$log_table_name = $wpdb->prefix.'usces_log';
		if( !empty($log_key) ) {
			$query = $wpdb->prepare( "SELECT * FROM {$log_table_name} WHERE `log_type` = 'acting_welcart' AND `log_key` = %s ORDER BY datetime DESC", $log_key );
		} else {
			$query = "SELECT * FROM {$log_table_name} WHERE `log_type` = 'acting_welcart' AND `log_key` LIKE '{$order_id}_%' ORDER BY datetime DESC";
		}
		$log_data = $wpdb->get_results( $query, ARRAY_A );
		return $log_data;
	}

	/**********************************************
	* 決済ログ更新
	* @param  $log $log_key
	* @return $res
	***********************************************/
	private function update_acting_log( $log, $log_key ) {
		global $wpdb;

		$log_table_name = $wpdb->prefix.'usces_log';
		$datetime = current_time('mysql');
		$query = $wpdb->prepare( "UPDATE {$log_table_name} SET `datetime` = %s, `log` = %s WHERE `log_type` = %s AND `log_key` = %s",
			$datetime,
			serialize($log),
			'acting_welcart',
			$log_key
		);
		$res = $wpdb->query( $query );
		return $res;
	}

	/**********************************************
	* 決済履歴ログ出力
	* @param  $log $log_key
	* @return $res
	***********************************************/
	private function save_acting_history_log( $log, $log_key ) {
		global $wpdb;

		$log_table_name = $wpdb->prefix.'usces_log';
		$datetime = current_time('mysql');
		$query = $wpdb->prepare( "INSERT INTO {$log_table_name} ( `datetime`, `log`, `log_type`, `log_key` ) VALUES ( %s, %s, %s, %s )",
			$datetime,
			serialize($log),
			'acting_welcart_history',
			$log_key
		);
		$res = $wpdb->query( $query );
		return $res;
	}

	/**********************************************
	* 決済履歴ログ取得
	* @param  $log_key '[order_id]_[trans_id]'
	* @return array $log_data
	***********************************************/
	private function get_acting_history_log( $log_key ) {
		global $wpdb;

		$log_table_name = $wpdb->prefix.'usces_log';
		$query = $wpdb->prepare( "SELECT * FROM {$log_table_name} WHERE `log_type` = 'acting_welcart_history' AND `log_key` = %s ORDER BY datetime DESC", $log_key );
		$log_data = $wpdb->get_results( $query, ARRAY_A );
		return $log_data;
	}

	/**********************************************
	* 初回決済処理取得
	* @param  $log_key '[order_id]_[trans_id]'
	* @return str $operateid
	***********************************************/
	private function get_acting_first_operateid( $log_key ) {
		global $wpdb;

		$log_table_name = $wpdb->prefix.'usces_log';
		$query = $wpdb->prepare( "SELECT * FROM {$log_table_name} WHERE `log_type` = 'acting_welcart_history' AND `log_key` = %s ORDER BY datetime ASC", $log_key );
		$log_data = $wpdb->get_results( $query, ARRAY_A );
		if( $log_data ) {
			$log = unserialize( $log_data[0]['log'] );
			$operateid = ( isset($log['OperateId']) ) ? $log['OperateId'] : '';
		} else {
			$operateid = '';
		}
		return $operateid;
	}

	/**********************************************
	* 決済履歴
	* @param  $log_key '[order_id]_[trans_id]'
	* @return $html
	***********************************************/
	private function settlement_history( $log_key ) {
		global $usces;

		$html = '';
		$log_data = $this->get_acting_history_log( $log_key );
		if( $log_data ) {
			$num = count($log_data);
			$html = '<table class="settlement-history">
				<thead class="settlement-history-head">
					<tr><th></th><th>'.__('Processing date','usces').'</th><th>'.__('Sequence number','usces').'</th><th>'.__('Processing classification','usces').'</th><th>'.__('Result','usces').'</th></tr>
				</thead>
				<tbody class="settlement-history-body">';
			foreach( (array)$log_data as $data ) {
				$log = unserialize( $data['log'] );
				$class = ( $log['ResponseCd'] != 'OK' ) ? ' error' : '';
				$operate_name = ( isset($log['OperateId']) ) ? $this->get_operate_name( $log['OperateId'] ) : '';
				$html .= '<tr>
					<td class="num">'.$num.'</td>
					<td class="datetime">'.$data['datetime'].'</td>
					<td class="transactionid">'.$log['TransactionId'].'</td>
					<td class="operateid">'.$operate_name.'</td>
					<td class="responsecd'.$class.'">'.$log['ResponseCd'].'</td>
				</tr>';
				$num--;
			}
			$html .= '</tbody>
				</table>';
		}
		return $html;
	}

	/**********************************************
	* 最新処理取得
	* @param  $log_key '[order_id]_[trans_id]'
	* @return array $latest_log
	***********************************************/
	private function get_acting_latest_log( $log_key, $responsecd = 'OK' ) {

		$latest_log = array();
		$latest_status = array( '1Auth', '1Capture', '1Gathering', '1Delete', '2Add', '2Chg', '2Del', '5Auth', '5Gathering', '5Capture', '5Delete', 'receipted' );
		$primarily_status = array( '1Auth', '1Capture', '1Gathering', '2Add', '5Auth', '5Gathering', '5Capture', 'receipted' );//取消以外
		$reauth_status = array( '1ReAuth' );//再オーソリ
		$log_data = $this->get_acting_history_log( $log_key );
		if( $log_data ) {
			if( $responsecd == 'OK' ) {
				$reauth = false;
				foreach( (array)$log_data as $data ) {
					$log = unserialize( $data['log'] );
					if( isset($log['ResponseCd']) ) {
						if( $log['ResponseCd'] == 'OK' && in_array( $log['OperateId'], $reauth_status ) ) {
							$reauth = true;
						} else {
							if( $reauth ) {
								if( $log['ResponseCd'] == 'OK' && in_array( $log['OperateId'], $primarily_status ) ) {
									$latest_log = $log;
									break;
								}
							} else {
								if( $log['ResponseCd'] == 'OK' && in_array( $log['OperateId'], $latest_status ) ) {
									$latest_log = $log;
									break;
								}
							}
						}
					}
				}
			} else {
				$latest_log = unserialize( $log_data[0]['log'] );
			}
		}
		return $latest_log;
	}

	/**********************************************
	* 最新処理ステータス取得
	* @param  $member_id $order_id
	* @return str $status
	***********************************************/
	public function get_latest_status( $member_id, $order_id ) {
		global $usces;

		$status = '';
		$log_data = $this->get_acting_log( $order_id );
		if( 0 < count($log_data) ) {
			$acting_data = unserialize($log_data[0]['log']);
			$trans_id = ( isset($acting_data['MerchantFree1']) ) ? $acting_data['MerchantFree1'] : '';
		} else {
			$trans_id = $usces->get_order_meta_value( 'trans_id', $order_id );
		}
		if( $trans_id ) {
			$latest_log = $this->get_acting_latest_log( $order_id.'_'.$trans_id, 'ALL' );
			$status = ( isset($latest_log['ResponseCd']) ) ? $latest_log['ResponseCd'] : 'NG';
		}
		return $status;
	}

	/**********************************************
	* 処理区分名称取得
	* @param  $log_key '[order_id]_[trans_id]'
	* @return str $status_name
	***********************************************/
	private function get_acting_status_name( $log_key ) {

		$status_name = '';
		$log_data = $this->get_acting_history_log( $log_key );
		if( $log_data ) {
			$log = unserialize( $log_data[0]['log'] );
			if( isset($log['OperateId']) ) {
				$status_name = $this->get_operate_name( $log['OperateId'] );
			}
		}
		return $status_name;
	}

	/**********************************************
	* 期限切れチェック
	* @param  $order_id $trans_id
	* @return boolean
	***********************************************/
	private function check_paylimit( $order_id, $trans_id ) {
		global $usces;

		$expiration = false;
		$receipted = false;
		$log_data = $this->get_acting_history_log( $order_id.'_'.$trans_id );
		if( $log_data ) {
			foreach( (array)$log_data as $data ) {
				$log = unserialize( $data['log'] );
				if( isset($log['OperateId']) && 'receipted' == $log['OperateId'] ) {
					$receipted = true;
					break;
				}
			}
		}
		if( $receipted ) {
			return false;
		}
		$today = date_i18n( 'YmdHi', current_time('timestamp') );
		$acting_data = maybe_unserialize( $usces->get_order_meta_value( 'acting_welcart_conv', $order_id ) );
		if( $today > $acting_data['PayLimit'] ) {
			$expiration = true;
		}
		return $expiration;
	}

	/**********************************************
	* 削除済みチェック
	* @param  $log_key '[order_id]_[trans_id]'
	* @return boolean
	***********************************************/
	private function check_deleted( $log_key ) {
		global $usces;

		$deleted = false;
		$log_data = $this->get_acting_history_log( $log_key );
		if( $log_data ) {
			foreach( (array)$log_data as $data ) {
				$log = unserialize( $data['log'] );
				if( isset($log['OperateId']) && '2Del' == $log['OperateId'] ) {
					$deleted = true;
					break;
				}
			}
		}
		return $deleted;
	}

	/**********************************************
	* 継続課金会員データ取得
	* @param  $order_id ($log_key)
	* @return array $log_data
	***********************************************/
	private function get_continuation_data( $order_id, $member_id ) {
		global $wpdb;

		$continuation_table_name = $wpdb->prefix.'usces_continuation';
		$query = $wpdb->prepare( "SELECT 
			`con_acting` AS `acting`, 
			`con_order_price` AS `order_price`, 
			`con_price` AS `price`, 
			`con_next_charging` AS `chargedday`, 
			`con_next_contracting` AS `contractedday`, 
			`con_startdate` AS `startdate`, 
			`con_status` AS `status` 
			FROM {$continuation_table_name} 
			WHERE con_order_id = %d AND con_member_id = %d", 
			$order_id, $member_id
		);
		$data = $wpdb->get_row( $query, ARRAY_A );
		return $data;
	}

	/**********************************************
	* 継続課金会員データ更新
	* @param  $log $log_key
	* @return $res
	***********************************************/
	private function update_continuation_data( $order_id, $member_id, $data, $stop = false ) {
		global $wpdb;

		$continuation_table_name = $wpdb->prefix.'usces_continuation';
		if( $stop ) {
			$query = $wpdb->prepare( "UPDATE {$continuation_table_name} SET 
				`con_status` = 'cancellation' 
				WHERE `con_order_id` = %d AND `con_member_id` = %d", 
				$order_id, $member_id 
			);
		} else {
			$query = $wpdb->prepare( "UPDATE {$continuation_table_name} SET 
				`con_price` = %f, 
				`con_next_charging` = %s, 
				`con_next_contracting` = %s, 
				`con_status` = %s 
				WHERE `con_order_id` = %d AND `con_member_id` = %d", 
				$data['price'], 
				$data['chargedday'], 
				$data['contractedday'], 
				$data['status'], 
				$order_id, $member_id 
			);
		}
		$res = $wpdb->query( $query );
		return $res;
	}

	/**********************************************
	* 日付チェック
	* @param  $date
	* @return boolean
	***********************************************/
	private function isdate( $date ) {

		if( empty($date) ) {
			return false;
		}
		try {
			new DateTime( $date );
			list( $year, $month, $day ) = explode( '-', $date );
			$res = checkdate( (int)$month, (int)$day, (int)$year );
			return $res;
		} catch( Exception $e ) {
			return false;
		}
	}

	/**********************************************
	* 決済オプション取得
	* @param  -
	* @return array $acting_settings
	***********************************************/
	private function get_acting_settings() {
		global $usces;

		$acting_settings = ( isset($usces->options['acting_settings']['welcart']) ) ? $usces->options['acting_settings']['welcart'] : array();
		return $acting_settings;
	}

	/**********************************************
	* 処理日付生成
	* @param  -
	* @return date 'YYYYMMDD'
	***********************************************/
	private function get_transaction_date() {

		$transactiondate = date_i18n( 'Ymd', current_time('timestamp') );
		return $transactiondate;
	}

	/**********************************************
	* e-SCOTT 会員ID取得
	* @param  $member_id
	* @return str $wcpay_member_id
	***********************************************/
	public function get_quick_kaiin_id( $member_id ) {
		global $usces;

		if( empty($member_id) ) {
			return false;
		}

		$wcpay_member_id = $usces->get_member_meta_value( 'wcpay_member_id', $member_id );
		return $wcpay_member_id;
	}

	/**********************************************
	* e-SCOTT 会員パスワード取得
	* @param  $member_id
	* @return str $wcpay_member_passwd
	***********************************************/
	public function get_quick_pass( $member_id ) {
		global $usces;

		if( empty($member_id) ) {
			return false;
		}

		$wcpay_member_passwd = $usces->get_member_meta_value( 'wcpay_member_passwd', $member_id );
		return $wcpay_member_passwd;
	}

	/**********************************************
	* e-SCOTT 会員ID生成
	* @param  $member_id
	* @return str KaiinId
	***********************************************/
	public function make_kaiin_id( $member_id ) {

		$digit = 11 - strlen( $member_id );
		$num = str_repeat( "9", $digit );
		$id = sprintf( '%0'.$digit.'d', mt_rand( 1, (int)$num ) );
		return 'w'.$member_id.'i'.$id;
	}

	/**********************************************
	* e-SCOTT 会員パスワード生成
	* @param  -
	* @return str KaiinPass
	***********************************************/
	public function make_kaiin_pass() {

		$passwd = sprintf( '%012d', mt_rand() );
		return $passwd;
	}

	/**********************************************
	* e-SCOTT 会員情報登録・更新
	* @param  ($param_list)
	* @return array $response_member
	***********************************************/
	public function escott_member_process( $param_list = array() ) {
		global $usces;

		$member = $usces->get_member();
		$acting_opts = $this->get_acting_settings();
		$params = array();
		$params['send_url'] = $acting_opts['send_url_member'];

		$response_member = array( 'ResponseCd'=>'' );
		$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
		$KaiinPass = $this->get_quick_pass( $member['ID'] );

		if( empty( $KaiinId ) || empty( $KaiinPass ) ) {
			$KaiinId = $this->make_kaiin_id( $member['ID'] );
			$KaiinPass = $this->make_kaiin_pass();
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemAdd',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass,
					'CardNo' => trim($_POST['cardno']),
					'CardExp' => substr($_POST['expyy'],2).$_POST['expmm']
				)
			);
			if( 'on' == $acting_opts['seccd'] ) {
				$params['param_list']['SecCd'] = trim($_POST['seccd']);
			}
			//e-SCOTT 新規会員登録
			$response_member = $this->connection( $params );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$usces->set_member_meta_value( 'wcpay_member_id', $KaiinId, $member['ID'] );
				$usces->set_member_meta_value( 'wcpay_member_passwd', $KaiinPass, $member['ID'] );
				$response_member['KaiinId'] = $KaiinId;
				$response_member['KaiinPass'] = $KaiinPass;
			}

		} else {
			if( isset($_POST['cardno']) && '8888888888888888' != $_POST['cardno'] ) {
				$params['param_list'] = array_merge( $param_list,
					array(
						'OperateId' => '4MemChg',
						'KaiinId' => $KaiinId,
						'KaiinPass' => $KaiinPass,
						'CardNo' => trim($_POST['cardno']),
						'CardExp' => substr($_POST['expyy'],2).$_POST['expmm']
					)
				);
				if( 'on' == $acting_opts['seccd'] ) {
					$params['param_list']['SecCd'] = trim($_POST['seccd']);
				}
				//e-SCOTT 会員更新
				$response_member = $this->connection( $params );
				if( 'OK' == $response_member['ResponseCd'] ) {
					$response_member['KaiinId'] = $KaiinId;
					$response_member['KaiinPass'] = $KaiinPass;
				}

			} else {
				$response_member['ResponseCd'] = 'OK';
				$response_member['KaiinId'] = $KaiinId;
				$response_member['KaiinPass'] = $KaiinPass;
			}
		}
		return $response_member;
	}

	/**********************************************
	* e-SCOTT 会員情報登録
	* @param  $member_id
	* @return array $response_member
	***********************************************/
	public function escott_member_register( $member_id ) {
		global $usces;

		$response_member = array( 'ResponseCd'=>'' );
		$acting_opts = $this->get_acting_settings();
		$TransactionDate = $this->get_transaction_date();
		$param_list = array();
		$params = array();

		$KaiinId = $this->make_kaiin_id( $member_id );
		$KaiinPass = $this->make_kaiin_pass();

		//共通部
		$param_list['MerchantId'] = $acting_opts['merchant_id'];
		$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
		$param_list['TransactionDate'] = $TransactionDate;
		$param_list['MerchantFree3'] = $this->merchantfree3;
		$param_list['TenantId'] = $acting_opts['tenant_id'];
		$params['send_url'] = $acting_opts['send_url_member'];
		$params['param_list'] = array_merge( $param_list,
			array(
				'OperateId' => '4MemAdd',
				'KaiinId' => $KaiinId,
				'KaiinPass' => $KaiinPass,
				'CardNo' => trim($_POST['cardno']),
				'CardExp' => substr($_POST['expyy'],2).$_POST['expmm']
			)
		);
		if( 'on' == $acting_opts['seccd'] ) {
			$params['param_list']['SecCd'] = trim($_POST['seccd']);
		}
		//e-SCOTT 新規会員登録
		$response_member = $this->connection( $params );
		if( 'OK' == $response_member['ResponseCd'] ) {
			$usces->set_member_meta_value( 'wcpay_member_id', $KaiinId, $member_id );
			$usces->set_member_meta_value( 'wcpay_member_passwd', $KaiinPass, $member_id );
			$response_member['KaiinId'] = $KaiinId;
			$response_member['KaiinPass'] = $KaiinPass;
		}
		return $response_member;
	}

	/**********************************************
	* e-SCOTT 会員情報更新
	* @param  $member_id
	* @return array $response_member
	***********************************************/
	public function escott_member_update( $member_id ) {
		global $usces;

		$response_member = array( 'ResponseCd'=>'' );
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );

		if( $KaiinId && $KaiinPass ) {
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();

			if( !empty($_POST['cardno']) ) {
				$param_list['CardNo'] = trim($_POST['cardno']);
			}
			if( 'on' == $acting_opts['seccd'] && !empty($_POST['seccd']) ) {
				$param_list['SecCd'] = trim($_POST['seccd']);
			}
			if( !empty($_POST['expyy']) && !empty($_POST['expmm']) ) {
				$param_list['CardExp'] = substr($_POST['expyy'],2).$_POST['expmm'];
			}

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_member'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemChg',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員更新
			$response_member = $this->connection( $params );
			if( 'OK' != $response_member['ResponseCd'] ) {
				usces_log('[WelcartPay] 4MemChg NG : '.print_r($response_member,true), 'acting_transaction.log');
			}
		}
		return $response_member;
	}

	/**********************************************
	* e-SCOTT 会員情報削除
	* @param  $member_id
	* @return array $response_member
	***********************************************/
	public function escott_member_delete( $member_id ) {
		global $usces;

		$response_member = array( 'ResponseCd'=>'' );
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );

		if( $KaiinId && $KaiinPass ) {
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_member'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemInval',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員無効
			$response_member = $this->connection( $params );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$params['param_list'] = array_merge( $param_list,
					array(
						'OperateId' => '4MemDel',
						'KaiinId' => $KaiinId,
						'KaiinPass' => $KaiinPass
					)
				);
				//e-SCOTT 会員削除
				$response_member = array( 'ResponseCd'=>'' );
				$response_member = $this->connection( $params );
				if( 'OK' == $response_member['ResponseCd'] ) {
					$usces->del_member_meta( 'wcpay_member_id', $member_id );
					$usces->del_member_meta( 'wcpay_member_passwd', $member_id );
				} else {
					usces_log('[WelcartPay] 4MemDel NG : '.print_r($response_member,true), 'acting_transaction.log');
				}
			} else {
				usces_log('[WelcartPay] 4MemInval NG : '.print_r($response_member,true), 'acting_transaction.log');
			}
		}
		return $response_member;
	}

	/**********************************************
	* e-SCOTT 会員情報照会
	* @param  $member_id ($KaiinId) ($KaiinPass)
	* @return array $response_member
	***********************************************/
	public function escott_member_reference( $member_id, $KaiinId = '', $KaiinPass = '' ) {

		$response_member = array( 'ResponseCd'=>'' );
		if( empty($KaiinId) ) {
			$KaiinId = $this->get_quick_kaiin_id( $member_id );
		}
		if( empty($KaiinPass) ) {
			$KaiinPass = $this->get_quick_pass( $member_id );
		}

		if( $KaiinId && $KaiinPass ) {
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_member'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemRefM',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員照会
			$response_member = $this->connection( $params );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$response_member['KaiinId'] = $KaiinId;
				$response_member['KaiinPass'] = $KaiinPass;
			}
		}
		return $response_member;
	}

	/**********************************************
	* 処理区分名称
	* @param  $OperateId
	* @return $operate_name
	***********************************************/
	private function get_operate_name( $OperateId ) {

		$operate_name = '';
		switch( $OperateId ) {
		case '1Check'://カードチェック
			$operate_name = __('Card check','usces');
			break;
		case '1Auth'://与信
			$operate_name = __('Credit','usces');
			break;
		case '1Capture'://売上計上
			$operate_name = __('Sales recorded','usces');
			break;
		case '1Gathering'://与信売上計上
			$operate_name = __('Credit sales','usces');
			break;
		case '1Change'://利用額変更
			$operate_name = __('Change spending amount','usces');
			break;
		case '1Delete'://取消
			$operate_name = __('Unregister','usces');
			break;
		case '1Search'://取引参照
			$operate_name = __('Transaction reference','usces');
			break;
		case '1ReAuth'://再オーソリ
			$operate_name = __('Re-authorization','usces');
			break;
		case '2Add'://登録
			$operate_name = __('Register');
			break;
		case '2Chg'://変更
			$operate_name = __('Change');
			break;
		case '2Del'://削除
			$operate_name = __('Unregister','usces');
			break;
		case '5Auth'://外貨与信
			$operate_name = __('Foreign currency credit','usces');
			break;
		case '5Gathering'://外貨与信売上確定
			$operate_name = __('Foreign currency credit sales confirmed','usces');
			break;
		case '5Capture'://外貨売上確定
			$operate_name = __('Foreign currency sales fixed','usces');
			break;
		case '5Delete'://外貨取消
			$operate_name = __('Foreign currency cancellation','usces');
			break;
		case '5OpeUnInval'://外貨取引保留解除
			$operate_name = __('Withdrawal of foreign currency transactions','usces');
			break;
		case 'receipted'://入金
			$operate_name = __('Payment','usces');
			break;
		case 'expiration'://期限切れ
			$operate_name = __('Expired','usces');
			break;
		}
		return $operate_name;
	}

	/**********************************************
	* 収納機関名称
	* @param  $CvsCd
	* @return $cvs_name
	***********************************************/
	private function get_cvs_name( $CvsCd ) {

		$cvs_name = '';
		switch( trim($CvsCd) ) {
		case 'LSN':
			$cvs_name = 'ローソン';
			break;
		case 'FAM':
			$cvs_name = 'ファミリーマート';
			break;
		case 'SAK':
			$cvs_name = 'サンクス';
			break;
		case 'CCK':
			$cvs_name = 'サークルK';
			break;
		case 'ATM':
			$cvs_name = 'Pay-easy（ATM）';
			break;
		case 'ONL':
			$cvs_name = 'Pay-easy（オンライン）';
			break;
		case 'LNK':
			$cvs_name = 'Pay-easy（情報リンク）';
			break;
		case 'SEV':
			$cvs_name = 'セブンイレブン';
			break;
		case 'MNS':
			$cvs_name = 'ミニストップ';
			break;
		case 'DAY':
			$cvs_name = 'デイリーヤマザキ';
			break;
		case 'EBK':
			$cvs_name = '楽天銀行';
			break;
		case 'JNB':
			$cvs_name = 'ジャパンネット銀行';
			break;
		case 'EDY':
			$cvs_name = 'Edy';
			break;
		case 'SUI':
			$cvs_name = 'Suica';
			break;
		case 'FFF':
			$cvs_name = 'スリーエフ';
			break;
		case 'JIB':
			$cvs_name = 'じぶん銀行';
			break;
		case 'SNB':
			$cvs_name = '住信SBIネット銀行';
			break;
		case 'SCM':
			$cvs_name = 'セイコーマート';
			break;
		}
		return $cvs_name;
	}

	/**********************************************
	* 手数料名称
	* @param  $fee_type
	* @return str $fee_name
	***********************************************/
	private function get_fee_name( $fee_type ) {

		$fee_name = '';
		if( 'fix' == $fee_type ) {
			$fee_name = __('Fixation','usces');
		} elseif( 'change' == $fee_type ) {
			$fee_name = __('Variable','usces');
		}
		return $fee_name;
	}

	/**********************************************
	* ATODENE CSV出力・CSVアップロード
	* @param  $order_action
	* @return -
	***********************************************/
	public function output_atodene_csv( $order_action ) {

		switch( $order_action ) {
		case 'download_atodene_register':
			$this->download_atodene_register();
			break;
		case 'download_atodene_update':
			$this->download_atodene_update();
			break;
		case 'download_atodene_report':
			$this->download_atodene_report();
			break;
		case 'upload_atodene_results':
			if( isset($_GET['atodene_upfile']) && !WCUtils::is_blank($_GET['atodene_upfile']) ) {
				$res = $this->upload_atodene_results();
				$_GET['usces_status'] = ( isset($res['status']) ) ? $res['status'] : '';
				$_GET['usces_message'] = ( isset($res['message']) ) ? $res['message'] : '';
			}
			break;
		}
	}

	/**********************************************
	* ATODENE 取引登録CSV出力
	* @param  
	* @return -
	***********************************************/
	public function download_atodene_register() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$filename = mb_convert_encoding(__('ATODENE_transaction_','usces'), 'SJIS', 'UTF-8').date_i18n( 'YmdHis', current_time('timestamp') ).".csv";

		$line = '"ご購入店受注番号","購入者注文日","会社名","部署名","氏名","氏名（フリガナ）","郵便番号","住所","電話番号","メールアドレス","配送先会社名","配送先部署名","配送先氏名","配送先氏名（フリガナ）","配送先郵便番号","配送先住所","配送先電話番号","請求書送付方法","予備領域1","予備領域2","予備領域3","顧客請求総額（税込）","明細名（商品名）","単価（税込）","数量"'."\r\n";

		$ids = $_GET['listcheck'];
		foreach( (array)$ids as $order_id ) {
			$order_data = $usces->get_order_data( $order_id, 'direct' );
			$payment = $usces->getPayments( $order_data['order_payment_name'] );
			if( 'acting_welcart_atodene' != $payment['settlement'] ) {
				continue;
			}

			$delivery = unserialize($order_data['order_delivery']);
			$cart = usces_get_ordercartdata( $order_id );

			$order_date = substr( $order_data['order_date'], 0, 10 );
			$date = str_replace( '-', '/', $order_date );

			$company = $usces->get_order_meta_value( 'cscs_company', $order_id );
			$order_name = $order_data['order_name1'].$order_data['order_name2'];
			$order_kana = mb_convert_kana($order_data['order_name3'], 'ak', 'UTF-8').mb_convert_kana($order_data['order_name4'], 'ak', 'UTF-8');
			$order_zip = str_replace("ー", "", mb_convert_kana($order_data['order_zip'], 'a', 'UTF-8'));
			$order_post = str_replace("-", "", $order_zip);
			$order_address = $order_data['order_pref'].$order_data['order_address1'];
			if( !empty($order_data['order_address2']) ) $order_address .= mb_convert_kana($order_data['order_address2'], 'ak', 'UTF-8');
			if( !empty($order_data['order_address3']) ) $order_address .= mb_convert_kana($order_data['order_address3'], 'ak', 'UTF-8');
			$order_tel = $order_data['order_tel'];
			$email = $order_data['order_email'];

			$shipto_company = $usces->get_order_meta_value( 'csde_company', $order_id );
			$shipto_name = $delivery['name1'].$delivery['name2'];
			$shipto_kana = mb_convert_kana($delivery['name3'], 'ak', 'UTF-8').mb_convert_kana($delivery['name4'], 'ak', 'UTF-8');
			$shipto_zip = str_replace("ー", "", mb_convert_kana($delivery['zipcode'], 'a', 'UTF-8'));
			$shipto_post = str_replace("-", "", $delivery['zipcode']);
			$shipto_address = $delivery['pref'].$delivery['address1'];
			if( !empty($delivery['address2']) ) $shipto_address .= mb_convert_kana($delivery['address2'], 'ak', 'UTF-8');
			if( !empty($delivery['address3']) ) $shipto_address .= mb_convert_kana($delivery['address3'], 'ak', 'UTF-8');
			$shipto_tel = $delivery['tel'];

			$amount = $order_data['order_item_total_price'] - $order_data['order_usedpoint'] + $order_data['order_discount'] + $order_data['order_shipping_charge'] + $order_data['order_cod_fee'] + $order_data['order_tax'];

			$line .= '"'.$order_id.'",'.
				'"'.$date.'",'.
				'"'.$company.'","",'.
				'"'.$order_name.'",'.
				'"'.$order_kana.'",'.
				'"'.$order_post.'",'.
				'"'.$order_address.'",'.
				'"'.$order_tel.'",'.
				'"'.$email.'",'.
				'"'.$shipto_company.'","",'.
				'"'.$shipto_name.'",'.
				'"'.$shipto_kana.'",'.
				'"'.$shipto_post.'",'.
				'"'.$shipto_address.'",'.
				'"'.$shipto_tel.'",'.
				'"'.$acting_opts['atodene_billing_method'].'","","","",'.
				'"'.$amount.'",';

			$row = 1;
			foreach( $cart as $cart_row ) {
				if( 1 < $row ) {
					$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				}
				$line .= '"'.$cart_row['item_name'].'",';
				$line .= '"'.usces_crform( $cart_row['price'], false, false, 'return', false ).'",';
				$line .= '"'.$cart_row['quantity'].'"'."\r\n";
				$row++;
			}

			if( $order_data['order_discount'] != 0 ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.apply_filters( 'usces_confirm_discount_label', __('Discount', 'usces'), $order_id ).'",';
				$line .= '"'.usces_crform( $cart_row['order_discount'], false, false, 'return', false ).'","1"'."\r\n";
			}

			if( usces_is_tax_display() && 'products' == usces_get_tax_target() && 'exclude' == usces_get_tax_mode() ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.usces_tax_label( $order_data, 'return' ).'",';
				$line .= '"'.usces_tax( $order_data, 'return' ).'","1"'."\r\n";
			}

			if( usces_is_member_system() && usces_is_member_system_point() && 0 == usces_point_coverage() && $order_data['order_usedpoint'] != 0 ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.__('use of points','usces').'",';
				$line .= '"'.number_format($order_data['order_usedpoint']).'","1"'."\r\n";
			}

			if ( 0 < $order_data['order_shipping_charge'] ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.__('Shipping','usces').'",';
				$line .= '"'.usces_crform( $order_data['order_shipping_charge'], false, false, 'return', false ).'","1"'."\r\n";
			}

			if ( 0 < $order_data['order_cod_fee'] ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.apply_filters( 'usces_filter_cod_label', __('COD fee', 'usces') ).'",';
				$line .= '"'.usces_crform( $order_data['order_cod_fee'], false, false, 'return', false ).'","1"'."\r\n";
			}

			if( usces_is_tax_display() && 'all' == usces_get_tax_target() && 'exclude' == usces_get_tax_mode() ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.usces_tax_label( $order_data, 'return' ).'",';
				$line .= '"'.usces_tax( $order_data, 'return' ).'","1"'."\r\n";
			}

			if( usces_is_member_system() && usces_is_member_system_point() && 1 == usces_point_coverage() && $order_data['order_usedpoint'] != 0 ) {
				$line .= '"","","","","","","","","","","","","","","","","","","","","","",';
				$line .= '"'.__('use of points','usces').'",';
				$line .= '"'.number_format($order_data['order_usedpoint']).'","1"'."\r\n";
			}
		}

		header("Content-Type: application/octet-stream");
		header("Content-disposition: attachment; filename=\"$filename\"");
		mb_http_output('pass');
		print(mb_convert_encoding($line, "SJIS-win", "UTF-8"));
		exit();
	}

	/**********************************************
	* ATODENE 取引一括変更・キャンセルCSV出力
	* @param  
	* @return -
	***********************************************/
	public function download_atodene_update() {
		global $usces;
		exit();
	}

	/**********************************************
	* ATODENE 出荷報告登録CSV出力
	* @param  
	* @return -
	***********************************************/
	public function download_atodene_report() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$filename = mb_convert_encoding(__('ATODENE_shippingreport_','usces'), 'SJIS', 'UTF-8').date_i18n( 'YmdHis', current_time('timestamp') ).".csv";

		$line = '"運送会社名","配送伝票番号","購入者注文日","お問合せ番号","ご購入店受注番号","氏名","予備項目","配送先氏名","配送先住所","顧客請求金額（税込）","請求書送付方法","審査結果"'."\r\n";

		$ids = $_GET['listcheck'];
		foreach( (array)$ids as $order_id ) {
			$order_data = $usces->get_order_data( $order_id, 'direct' );
			$payment = $usces->getPayments( $order_data['order_payment_name'] );
			if( 'acting_welcart_atodene' != $payment['settlement'] ) {
				continue;
			}

			$delivery_company = $usces->get_order_meta_value( 'delivery_company', $order_id );
			$tracking_number = $usces->get_order_meta_value( apply_filters( 'usces_filter_tracking_meta_key', 'tracking_number' ), $order_id );
			$atodene_number = $usces->get_order_meta_value( 'atodene_number', $order_id );

			$line .= '"'.$delivery_company.'",'.
				'"'.$tracking_number.'","",'.
				'"'.$atodene_number.'",'.
				'"'.$order_id.'","","","","","","","",""'."\r\n";
		}

		header("Content-Type: application/octet-stream");
		header("Content-disposition: attachment; filename=\"$filename\"");
		mb_http_output('pass');
		print(mb_convert_encoding($line, "SJIS-win", "UTF-8"));
		exit();
	}

	/**********************************************
	* ATODENE 与信審査結果CSV取込
	* @param  
	* @return -
	***********************************************/
	public function upload_atodene_results() {
		global $usces, $wpdb;

		//check_admin_referer( 'order_list', 'wc_nonce' );

		$res = array();
		$path = WP_CONTENT_DIR.'/uploads/';

		if( isset($_GET['atodene_upfile']) && !WCUtils::is_blank($_GET['atodene_upfile']) && isset($_GET['order_action']) && $_GET['order_action'] == 'upload_atodene_results' ) {
			$file_name = urldecode($_GET['atodene_upfile']);
			$decode_filename = base64_decode($file_name);
			if( !file_exists($path.$file_name) ) {
				$res['status'] = 'error';
				$res['message'] = __('CSV file does not exist.', 'usces').esc_html($decode_filename);
				return( $res );
			}
		}

		$wpdb->query( 'SET SQL_BIG_SELECTS=1' );
		set_time_limit( 3600 );

		define( 'COL_ORDER_ID', 0 );//ご購入店受注番号
		define( 'COL_ATODINE_NUMBER', 1 );//お問合せ番号
		define( 'COL_NAME', 2 );//氏名
		define( 'COL_AMOUNT', 3 );//顧客請求金額（税込）
		define( 'COL_BILLING_METHOD', 4 );//請求書送付方法(別送/同梱)
		define( 'COL_RESULTS', 5 );//与信審査結果(OK/NG/保留/審査中)

		if( !( $fpo = fopen( $path.$file_name, "r" ) ) ) {
			$res['status'] = 'error';
			$res['message'] = __('A file does not open.', 'usces').esc_html($decode_filename);
			return $res;
		}

		$orglines = array();
		$sp = ',';

		$fname_parts = explode( '.', $decode_filename );
		if( 'csv' !== end($fname_parts) ) {
			$res['status'] = 'error';
			$res['message'] = __('This file is not in the CSV file.', 'usces').esc_html($decode_filename);
			return $res;
		}

		$buf = '';
		while( !feof($fpo) ) {
			$temp = fgets( $fpo, 10240 );
			if( 0 == strlen($temp) ) continue;
			$orglines[] = str_replace( '"', '', $temp );
		}
		fclose($fpo);

		foreach( $orglines as $sjisline ) {
			$line = mb_convert_encoding( $sjisline, 'UTF-8', 'SJIS' );
			list( $order_id, $atodene_number, $name, $amount, $billing_method, $atodene_results ) = explode( $sp, $line );
			$order_data = $usces->get_order_data( $order_id, 'direct' );
			if( $order_data ) {
				if( 'OK' == trim($atodene_results) ) {
					$res = usces_change_order_receipt( (int)$order_id, 'receipted' );
				}
				if( !empty($atodene_number) ) {
					$usces->set_order_meta_value( 'atodene_number', trim($atodene_number), (int)$order_id );
				}
				if( !empty($atodene_results) ) {
					$usces->set_order_meta_value( 'atodene_results', trim($atodene_results), (int)$order_id );
				}
			}
		}
		unlink( $path.$file_name );

		return $res;
	}

	/**********************************************
	* ATODENE アクションボタン
	* @param  -
	* @return -
	***********************************************/
	public function action_atodene_button() {
?>
				<input type="button" id="dl_atodene_register_csv" class="searchbutton" value="<?php _e('ATODENE transaction registration CSV output','usces'); ?>" />
				<!--<input type="button" id="dl_atodene_update_csv" class="searchbutton" value="<?php _e('ATODENE transaction batch change and cancel CSV output','usces'); ?>" />-->
				<input type="button" id="up_atodene_results_csv" class="searchbutton" value="<?php _e('ATODENE credit review result CSV upload','usces'); ?>" />
				<input type="button" id="dl_atodene_report_csv" class="searchbutton" value="<?php _e('ATODENE shipping report registration CSV output','usces'); ?>" />
<?php
	}

	/**********************************************
	* ATODENE CSVアップロード
	* @param  $order_action
	* @return -
	***********************************************/
	function atodene_upload() {
		global $usces;

		if( isset($_POST['page']) && $_POST['page'] == 'atodene_results_csv' && isset($_POST['action']) && $_POST['action'] == 'atodene_upload' ) {

			$path = WP_CONTENT_DIR.'/uploads/';
			$workfile = $_FILES["atodene_upcsv"]["tmp_name"];
			if( !is_uploaded_file( $workfile ) ) {
				$message = __('The file was not uploaded.','usces');
				wp_redirect( add_query_arg( array( 'page'=>'usces_orderlist', 'usces_status'=>'error', 'usces_message'=>urlencode($message), 'order_action'=>'atodene_upload' ), USCES_ADMIN_URL ) );
				exit();
			}

			list( $fname, $fext ) = explode( '.', $_FILES["atodene_upcsv"]["name"], 2 );
			if( $fext != 'csv' ) {
				$message =  __('The file is not supported.','usces').$fname.'.'.$fext;
				wp_redirect( add_query_arg( array( 'page'=>'usces_orderlist', 'usces_status'=>'error', 'usces_message'=>urlencode($message), 'order_action'=>'atodene_upload' ), USCES_ADMIN_URL ) );
				exit();
			}

			$new_filename = base64_encode( $fname.'_'.time().'.'.$fext );
			if( !move_uploaded_file( $_FILES['atodene_upcsv']['tmp_name'], $path.$new_filename ) ) {
				$message = __('The file was not stored.','usces').$_FILES["atodene_upcsv"]["name"];
				wp_redirect( add_query_arg( array( 'page'=>'usces_orderlist', 'usces_status'=>'error', 'usces_message'=>urlencode($message), 'order_action'=>'atodene_upload' ), USCES_ADMIN_URL ) );
				exit();
			}

			wp_redirect( add_query_arg( array( 'page'=>'usces_orderlist', 'usces_status'=>'none', 'usces_message'=>'', 'order_action'=>'upload_atodene_results', 'atodene_upfile'=>urlencode($new_filename), 'wc_nonce'=>wp_create_nonce('order_list') ), USCES_ADMIN_URL ) );
			exit();
		}
	}

	/**********************************************
	* ATODENE CSVアップロードダイアログ
	* @param  -
	* @return -
	***********************************************/
	public function order_list_footer() {

		$html = '
		<div id="atodene_upload_dialog" class="upload_dialog">
			<p>'.__("Upload the prescribed CSV file and import credit screening results.<br />Please choose a file, and press 'Start of capture'.",'usces').'</p>
			<form action="'.USCES_ADMIN_URL.'" method="post" enctype="multipart/form-data" name="atodene_upform" id="atodene_upform">
				<fieldset>
					<p><input name="atodene_upcsv" type="file" class="filename" /></p>
				</fieldset>
				<p><input name="atodene_uploadcsv" type="submit" class="button" value="'.__('Start of capture','usces').'" /></p>
				<input name="page" type="hidden" value="atodene_results_csv" />
				<input name="action" type="hidden" value="atodene_upload" />
			</form>
		</div>';
		echo $html;
	}

	/**********************************************
	* ATODENE CSVダウンロードダイアログ
	* @param  -
	* @return -
	***********************************************/
	public function order_list_page_js() {

		$html = '
		$(document).on( "click", "#dl_atodene_register_csv", function() {
			if( $("input[name*=\'listcheck\']:checked").length == 0 ) {
				alert("'.__('Choose the data.','usces').'");
				$("#orderlistaction").val("");
				return false;
			}
			var listcheck = "";
			$("input[name*=\'listcheck\']").each( function(i) {
				if( $(this).attr("checked") ) {
					listcheck += "&listcheck["+i+"]="+$(this).val();
				}
			});
			location.href = "'.USCES_ADMIN_URL.'?page=usces_orderlist&order_action=download_atodene_register"+listcheck+"&noheader=true&nonce='.wp_create_nonce('csv_nonce').'";
		});';
/*
		$(document).on( "click", "#dl_atodene_update_csv", function() {
			if( $("input[name*=\'listcheck\']:checked").length == 0 ) {
				alert("'.__('Choose the data.','usces').'");
				$("#orderlistaction").val("");
				return false;
			}
			var listcheck = "";
			$("input[name*=\'listcheck\']").each( function(i) {
				if( $(this).attr("checked") ) {
					listcheck += "&listcheck["+i+"]="+$(this).val();
				}
			});
			location.href = "'.USCES_ADMIN_URL.'?page=usces_orderlist&order_action=download_atodene_update"+listcheck+"&noheader=true&nonce='.wp_create_nonce('csv_nonce').'";
		});
*/
		$html .= '
		$(document).on( "click", "#dl_atodene_report_csv", function() {
			if( $("input[name*=\'listcheck\']:checked").length == 0 ) {
				alert("'.__('Choose the data.','usces').'");
				$("#orderlistaction").val("");
				return false;
			}
			var listcheck = "";
			$("input[name*=\'listcheck\']").each( function(i) {
				if( $(this).attr("checked") ) {
					listcheck += "&listcheck["+i+"]="+$(this).val();
				}
			});
			location.href = "'.USCES_ADMIN_URL.'?page=usces_orderlist&order_action=download_atodene_report"+listcheck+"&noheader=true&nonce='.wp_create_nonce('csv_nonce').'";
		});

		$(document).on( "click", "#up_atodene_results_csv", function() {
			$("#atodene_upload_dialog").dialog({
				bgiframe: true,
				autoOpen: false,
				title: "'.__('Credit Review Result CSV Capture','usces').'",
				height: 350,
				width: 550,
				modal: true,
				buttons: {
					"'.__('Close').'": function() {
						$(this).dialog("close");
					}
				},
				close: function() {
				}
			}).dialog( "open" );
		});';

		return $html;
	}

	/**********************************************
	* ATODENE アクションステータス
	* @param  -
	* @return -
	***********************************************/
	public function order_list_action_status( $status ) {
		if( isset($_GET['order_action']) && ( 'atodene_upload' == $_GET['order_action'] || 'upload_atodene_results' == $_GET['order_action'] ) && isset( $_GET['usces_status'] ) && !empty( $_GET['usces_status'] ) ) {
			$status = $_GET['usces_status'];
		}
		return $status;
	}
	public function order_list_action_message( $message ) {
		if( isset($_GET['order_action']) && ( 'atodene_upload' == $_GET['order_action'] || 'upload_atodene_results' == $_GET['order_action'] ) && isset( $_GET['usces_message'] ) && !empty( $_GET['usces_message'] ) ) {
			$message = urldecode($_GET['usces_message']);
		}
		return $message;
	}

	/**********************************************
	* usces_filter_cod_label
	* 手数料ラベル
	* @param  $label
	* @return str $label
	***********************************************/
	public function set_fee_label( $label ) {
		global $usces;

		if( is_admin() ) {
			$order_id = ( isset($_REQUEST['order_id']) ) ? $_REQUEST['order_id'] : '';
			if( !empty($order_id) ) {
				$order_data = $usces->get_order_data( $order_id, 'direct' );
				$payment = usces_get_payments_by_name( $order_data['order_payment_name'] );
				if( 'acting_welcart_conv' == $payment['settlement'] || 'acting_welcart_atodene' == $payment['settlement'] ) {
					$label = $payment['name'].__('Fee','usces');
				}
			//} else {
			//	$label = __('Fee','usces');
			}
		} else {
			$usces_entries = $usces->cart->get_entry();
			$payment = $usces->getPayments( $usces_entries['order']['payment_name'] );
			if( 'acting_welcart_conv' == $payment['settlement'] || 'acting_welcart_atodene' == $payment['settlement'] ) {
				$label = $payment['name'].__('Fee','usces');
			}
		}
		return $label;
	}

	/**********************************************
	* usces_filter_member_history_cod_label
	* 手数料ラベル
	* @param  $label $order_id
	* @return str $label
	***********************************************/
	public function set_member_history_fee_label( $label, $order_id ) {
		global $usces;

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$payment = usces_get_payments_by_name( $order_data['order_payment_name'] );
		if( 'acting_welcart_conv' == $payment['settlement'] || 'acting_welcart_atodene' == $payment['settlement'] ) {
			$label = $payment['name'].__('Fee','usces');
		}
		return $label;
	}

	/**********************************************
	* usces_fiter_the_payment_method
	* 支払方法
	* @param  $payments
	* @return array $payments
	***********************************************/
	public function payment_method( $payments ) {
		global $usces;

		$conv_exclusion = false;
		$atodene_exclusion = false;

		if( usces_have_regular_order() ) {
			$conv_exclusion = true;

		} elseif( usces_have_continue_charge() ) {
			$conv_exclusion = true;
			$atodene_exclusion = true;

		} else {
			$acting_opts = $this->get_acting_settings();
			if( 'on' == $acting_opts['atodene_byitem'] ) {//商品ごとの可否が有効
				$cart = $usces->cart->get_cart();
				foreach( $cart as $cart_row ) {
					$atodene_propriety = get_post_meta( $cart_row['post_id'], 'atodene_propriety', true );
					if( 1 == (int)$atodene_propriety ) {
						$atodene_exclusion = true;
						break;
					}
				}
			}
		}

		if( $conv_exclusion ) {
			foreach( $payments as $key => $payment ) {
				if( 'acting_welcart_conv' == $payment['settlement'] ) {
					unset( $payments[$key] );
				}
			}
		}
		if( $atodene_exclusion ) {
			foreach( $payments as $key => $payment ) {
				if( 'acting_welcart_atodene' == $payment['settlement'] ) {
					unset( $payments[$key] );
				}
			}
		}

		return $payments;
	}

	/**********************************************
	* usces_filter_set_cart_fees_cod
	* 決済手数料
	* @param  $cod_fee $usces_entries $total_items_price $use_point $discount $shipping_charge $amount_by_cod
	* @return float $cod_fee
	***********************************************/
	public function add_fee( $cod_fee, $usces_entries, $total_items_price, $use_point, $discount, $shipping_charge, $amount_by_cod ) {
		global $usces;

		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		if( 'acting_welcart_conv' != $payment['settlement'] && 'acting_welcart_atodene' != $payment['settlement'] ) {
			return $cod_fee;
		}

		$acting_opts = $this->get_acting_settings();
		$acting = explode( '_', $payment['settlement'] );
		$fee = 0;
		if( 'fix' == $acting_opts[$acting[2].'_fee_type'] ) {
			$fee = (int)$acting_opts[$acting[2].'_fee'];
		} else {
			$materials = array(
				'total_items_price' => $usces_entries['order']['total_items_price'],
				'discount' => $usces_entries['order']['discount'],
				'shipping_charge' => $usces_entries['order']['shipping_charge'],
				'cod_fee' => $usces_entries['order']['cod_fee']
			);
			$items_price = $total_items_price - $discount;
			$price = $items_price + $usces->getTax( $items_price, $materials );
			if( $price <= (int)$acting_opts[$acting[2].'_fee_first_amount'] ) {
				$fee = $acting_opts[$acting[2].'_fee_first_fee'];
			} elseif( isset($acting_opts[$acting[2].'_fee_amounts']) && !empty($acting_opts[$acting[2].'_fee_amounts']) ) {
				$last = count( $acting_opts[$acting[2].'_fee_amounts'] ) - 1;
				if( $price > $acting_opts[$acting[2].'_fee_amounts'][$last] ) {
					$fee = $acting_opts[$acting[2].'_fee_end_fee'];
				} else {
					foreach( $acting_opts[$acting[2].'_fee_amounts'] as $key => $value ) {
						if( $price <= $value ) {
							$fee = $acting_opts[$acting[2].'_fee_fees'][$key];
							break;
						}
					}
				}
			} else {
				$fee = $acting_opts[$acting[2].'_fee_end_fee'];
			}
		}
		return $cod_fee + $fee;
	}

	/**********************************************
	* usces_filter_delivery_check usces_filter_point_check_last
	* 決済手数料チェック
	* @param  $mes
	* @return str $mes
	***********************************************/
	public function check_fee_limit( $mes ) {
		global $usces;

		$member = $usces->get_member();
		$usces->set_cart_fees( $member, array() );
		$usces_entries = $usces->cart->get_entry();
		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		if( 'acting_welcart_conv' != $payment['settlement'] && 'acting_welcart_atodene' != $payment['settlement'] ) {
			return $mes;
		}

		if( 2 == $usces_entries['delivery']['delivery_flag'] ) {
			$mes .= sprintf( __("If you specify multiple shipping address, you cannot use '%s' payment method.",'usces'), $usces_entries['order']['payment_name'] );
			return $mes;
		}

		$acting_opts = $this->get_acting_settings();
		$fee_limit_amount = 0;
		switch( $payment['settlement'] ) {
		case 'acting_welcart_conv':
			if( 'fix' == $acting_opts['conv_fee_type'] ) {
				$fee_limit_amount = $acting_opts['conv_fee_limit_amount'];
			}
			break;

		case 'acting_welcart_atodene':
			if( 'fix' == $acting_opts['atodene_fee_type'] ) {
				$fee_limit_amount = $acting_opts['atodene_fee_first_amount'];
			}
			break;
		}

		if( 0 < $fee_limit_amount && $usces_entries['order']['total_full_price'] > $fee_limit_amount ) {
			$mes .= sprintf( __("It exceeds the maximum amount of '%1$s' (total amount %2$s).", 'usces'), $usces_entries['order']['payment_name'], usces_crform( $fee_limit_amount, true, false, 'return', true ) );
		}

		return $mes;
	}

	/**********************************************
	* usces_item_master_second_section
	* 後払い決済の可否
	* @param  $second_section $post_id
	* @return html $second_section
	***********************************************/
	public function edit_item_atodene_byitem( $second_section, $post_id ) {
		global $usces;

		$division = $usces->getItemDivision( $post_id );
		$charging_type = $usces->getItemChargingType( $post_id );
		$acting_opts = $this->get_acting_settings();
		if( 'shipped' == $division && 'continue' != $charging_type && 'on' == $acting_opts['atodene_byitem'] ) {//商品ごとの可否が有効
			$atodene_propriety = get_post_meta( $post_id, 'atodene_propriety', true );
			$checked = ( 1 == (int)$atodene_propriety ) ? array( '', ' checked="checked"' ) : array( ' checked="checked"', '' );
			$second_section .= '
			<tr>
				<th>'.__('Atobarai Propriety','usces').'</th>
				<td>
					<label for="atodene_propriety_0"><input name="atodene_propriety" id="atodene_propriety_0" type="radio" value="0"'.$checked[0].'>'.__('available','usces').'</label>
					<label for="atodene_propriety_1"><input name="atodene_propriety" id="atodene_propriety_1" type="radio" value="1"'.$checked[1].'>'.__('not available','usces').'</label>
				</td>
			</tr>';
		}
		return $second_section;
	}

	/**********************************************
	* usces_action_save_product
	* 後払い決済の可否更新
	* @param  $post_id $post
	* @return -
	***********************************************/
	public function save_item_atodene_byitem( $post_id, $post ) {

		if( isset($_POST['atodene_propriety']) ) {
			update_post_meta( $post_id, 'atodene_propriety', $_POST['atodene_propriety'] );
		}
	}

	/**********************************************
	* usces_filter_nonacting_settlements
	* 
	* @param  $cod_fee $entries $total_items_price $use_point $discount $shipping_charge $amount_by_cod
	* @return float $cod_fee
	***********************************************/
	public function nonacting_settlements( $nonacting_settlements ) {

		if( !in_array( 'acting_welcart_atodene', $nonacting_settlements ) ) {
			$nonacting_settlements[] = 'acting_welcart_atodene';
		}
		return $nonacting_settlements;
	}

	/**********************************************
	* wcad_filter_the_payment_method_restriction
	* 
	* @param  $payments_restriction $value
	* @return array $payments_restriction
	***********************************************/
	function payment_method_restriction_atodene( $payments_restriction, $value ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( usces_have_regular_order() ) {
			$atodene_exclusion = false;
			if( 'on' == $acting_opts['atodene_byitem'] ) {//商品ごとの可否が有効
				$cart = $usces->cart->get_cart();
				foreach( $cart as $cart_row ) {
					$atodene_propriety = get_post_meta( $cart_row['post_id'], 'atodene_propriety', true );
					if( 1 == (int)$atodene_propriety ) {
						$atodene_exclusion = true;
						break;
					}
				}
			}
			if( !$atodene_exclusion ) {
				$payments = usces_get_system_option( 'usces_payment_method', 'settlement' );
				$payments_restriction[] = $payments['acting_welcart_atodene'];
				foreach( (array)$payments_restriction as $key => $value ) {
					$sort[$key] = $value['sort'];
				}
				array_multisort( $sort, SORT_ASC, $payments_restriction );
			}
		}
		return $payments_restriction;
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
		
			case 'acting_welcart_card':
				$response_data['acting'] = 'welcart_card';
				$response_data['acting_return'] = 1;
				$response_data['result'] = 1;
				$response_data['nonce'] = wp_create_nonce( 'welcart_transaction' );
				wp_redirect( add_query_arg( $response_data, USCES_CART_URL ) );
				exit;
				break;
				
			case 'acting_welcart_conv':
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

	/**********************************************
	* ソケット通信接続
	* @param  $params
	* @return array $response_data
	***********************************************/
	public function connection( $params ) {

		$gc = new SLNConnection();
		$gc->set_connection_url( $params['send_url'] );
		$gc->set_connection_timeout( 60 );
		$response_list = $gc->send_request( $params['param_list'] );

		if( !empty($response_list) ) {
			$resdata = explode( "\r\n\r\n", $response_list );
			parse_str( $resdata[1], $response_data );
			if( !array_key_exists( 'ResponseCd', $response_data ) ) {
				$response_data['ResponseCd'] = 'NG';
			}

		} else {
			$response_data['ResponseCd'] = 'NG';
		}
		return $response_data;
	}
}

/**************************************************************************************/
//クラス定義 : SLNConnection
if( !class_exists('SLNConnection') ) {
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
			if( empty($param_list) === false ) {
				// 送信先情報の準備
				$url = parse_url( $this->connection_url );

				// HTTPデータ生成
				$http_data = "";
				reset( $param_list );
				while( list($key, $value) = each($param_list) ) {
					$http_data .= ( ($http_data !== "") ? "&" : "" ).$key."=".$value;
				}

				// HTTPヘッダ生成
				$http_header = "POST ".$url['path']." HTTP/1.1"."\r\n".
				"Host: ".$url['host']."\r\n".
				"User-Agent: SLN_PAYMENT_CLIENT_PG_PHP_VERSION_1_0"."\r\n".
				"Content-Type: application/x-www-form-urlencoded"."\r\n".
				"Content-Length: ".strlen($http_data)."\r\n".
				"Connection: close";

				// POSTデータ生成
				$http_post = $http_header."\r\n\r\n".$http_data;

				// 送信処理
				$errno = 0;
				$errstr = "";
				$hm = array();
				$context = stream_context_create(
					array(
						'ssl' => array( 'capture_session_meta' => true )
					)
				);

				// ソケット通信接続
				$fp = @stream_socket_client( 'tlsv1.2://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );
				if( $fp === false ) {
					usces_log('[WelcartPay] e-SCOTT send error : '.__('TLS 1.2 connection failed.','usces'), 'acting_transaction.log');//TLS1.2接続に失敗しました
					$fp = @stream_socket_client( 'ssl://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );
					if( $fp === false ) {
						usces_log('[WelcartPay] e-SCOTT send error : '.__('SSL connection failed.','usces'), 'acting_transaction.log');//SSL接続に失敗しました
						return $rValue;
					}
				}

				if( $fp !== false ) {
					// 接続後タイムアウト設定
					$result = socket_set_timeout( $fp, $this->connection_timeout );
					if( $result === true ) {
						// データ送信
						fwrite( $fp, $http_post );
						// 応答受信
						$response_data = "";
						while( !feof($fp) ) {
							$response_data .= fgets( $fp, 4096 );
						}

						// ソケット通信情報を取得
						$hm = stream_get_meta_data( $fp );
						// ソケット通信切断
						$result = fclose( $fp );
						if( $result === true ) {
							if( $hm['timed_out'] !== true ) {
								// レスポンスデータ生成
								$rValue = $response_data;
							} else {
								// エラー： タイムアウト発生
								usces_log('[WelcartPay] e-SCOTT send error : '.__('Timeout occurred during communication.','usces'), 'acting_transaction.log');//通信中にタイムアウトが発生しました
							}
						} else {
							// エラー： ソケット通信切断失敗
							usces_log('[WelcartPay] e-SCOTT send error : '.__('Failed to disconnect from SLN.','usces'), 'acting_transaction.log');//SLNとの切断に失敗しました
						}
					} else {
						// エラー： タイムアウト設定失敗 
						usces_log('[WelcartPay] e-SCOTT send error : '.__('Timeout setting failed.','usces'), 'acting_transaction.log');//タイムアウト設定に失敗しました
					}
				}
			} else {
				// エラー： パラメータ不整合
				usces_log('[WelcartPay] e-SCOTT send error : '.__('Invalid request parameter specification.','usces'), 'acting_transaction.log');//リクエストパラメータの指定が正しくありません
			}
			return $rValue;
		}
	}
}
