<?php
class WlcOrderList
{
	var $table;			//テーブル名
	var $rows;			//データ
	var $action;		//アクション
	var $startRow;		//表示開始行番号
	var $maxRow;		//最大表示行数
	var $currentPage;	//現在のページNo
	var $firstPage;		//最初のページNo
	var $previousPage;	//前のページNo
	var $nextPage;		//次のページNo
	var $lastPage;		//最終ページNo
	var $naviMaxButton;	//ページネーション・ナビのボタンの数
	var $dataTableNavigation;	//ナヴィゲーションhtmlコード
	var $arr_period;	//表示データ期間
	var $arr_search;	//サーチ条件
	var $searchSql;		//簡易絞込みSQL
	var $searchSkuSql;	//SKU絞り込み
	var $searchSwitchStatus;	//サーチ表示スイッチ
	var $columns;		//データカラム
	var $sortColumn;	//現在ソート中のフィールド
	var $sortOldColumn;
	var $sortSwitchs;	//各フィールド毎の昇順降順スイッチ
	var $userHeaderNames;	//ユーザー指定のヘッダ名
	var $action_status, $action_message;
	var $pageLimit;		//ページ制限
	var $management_status;	//処理ステータス
	var $selectSql;
	var $joinTableSql;
	var $cscs_meta;
	var $csod_meta;
	var $currentPageIds;
	var $period;

	//Constructor
	function __construct(){
		global $wpdb;

		$this->cscs_meta = usces_has_custom_field_meta('customer');
		$this->csod_meta = usces_has_custom_field_meta('order');

		$this->listOption = get_option( 'usces_orderlist_option' );

		$this->table = $wpdb->prefix . "usces_order";
		$this->set_column();
		$this->rows = array();

		$this->maxRow = $this->listOption['max_row'];
		$this->naviMaxButton = 11;
		$this->firstPage = 1;
		$this->pageLimit = 'on';
		$this->action_status = 'none';
		$this->action_message = '';
//		$this->selectSql = '';
//		$this->joinTableSql = '';
		$orderPeriod = isset($_COOKIE['orderPeriod']) ? $_COOKIE['orderPeriod'] : '';
		if( empty($orderPeriod) ){
			$this->period = array( 'period' => 0, 'start' => '', 'end' => '' );
		}else{
			parse_str($orderPeriod,$this->period);
		}

		$this->SetParamByQuery();

		$arr_period = array(__('This month', 'usces'), __('Last month', 'usces'), __('The past one week', 'usces'), __('Last 30 days', 'usces'), __('Last 90days', 'usces'), __('All', 'usces'));
		$this->arr_period = apply_filters( 'usces_filter_order_list_arr_period', $arr_period, $this );

		$management_status = array(
			'duringorder' => __('temporaly out of stock', 'usces'),
			'cancel' => __('Cancel', 'usces'),
			'completion' => __('It has sent it out.', 'usces'),
			'estimate' => __('An estimate', 'usces'),
			'adminorder' => __('Management of Note', 'usces'),
			'continuation' => __('Continuation', 'usces'),
			'termination' => __('Termination', 'usces')
			);
		$this->management_status = apply_filters( 'usces_filter_management_status', $management_status, $this );

		$wpdb->query( 'SET SQL_BIG_SELECTS=1' );
//		$this->SetSelects();
//		$this->SetJoinTables();
	}

	function set_column(){
	
		$arr_column = array();
		$arr_column['admin_memo'] = __('Administrator Note', 'usces');
		$arr_column['ID'] = __('ID', 'usces');
		$arr_column['deco_id'] = __('Order number', 'usces');
		$arr_column['order_date'] = __('Order date', 'usces');
		
		$arr_column['order_modified'] = apply_filters('usces_filter_admin_modified_label', __('shpping date', 'usces') );
		$arr_column['estimate_status'] = __('Order type', 'usces');
		$arr_column['process_status'] = __('Processing status', 'usces');
		$arr_column['tracking_number'] = __('Tracking number', 'usces');
		$arr_column['payment_name'] = __('payment method', 'usces');
		$arr_column['wc_trans_id'] = __('Transaction ID', 'usces');
		$arr_column['receipt_status'] = __('transfer statement', 'usces');
		$arr_column['item_total_price'] = __('total items', 'usces').'('.__(usces_crcode( 'return' ), 'usces').')';
		$arr_column['getpoint'] = __('granted points', 'usces');
		$arr_column['usedpoint'] = __('Used points', 'usces');
		$arr_column['discount'] = __('Discount', 'usces').'('.__(usces_crcode( 'return' ), 'usces').')';
		$arr_column['shipping_charge'] = __('Shipping', 'usces').'('.__(usces_crcode( 'return' ), 'usces').')';
		$arr_column['cod_fee'] = __('Fee', 'usces').'('.__(usces_crcode( 'return' ), 'usces').')';
		$arr_column['tax'] = __('Tax', 'usces').'('.__(usces_crcode( 'return' ), 'usces').')';
		$arr_column['total_price'] = __('Total Amount', 'usces').'('.__(usces_crcode( 'return' ), 'usces').')';
		$arr_column['deli_method'] = __('shipping option', 'usces');
		$arr_column['deli_name'] = __('Destination name', 'usces');
		$arr_column['deli_time'] = __('delivery time', 'usces');
		$arr_column['deli_date'] = __('Delivery date', 'usces');
		$arr_column['delidue_date'] = __('Shipping date', 'usces');

		$arr_column['mem_id'] = __('membership number', 'usces');
		$arr_column['name1'] = __('Last Name', 'usces');
		$arr_column['name2'] = __('First Name', 'usces');
		$arr_column['name3'] = __('Last Furigana', 'usces');
		$arr_column['name4'] = __('First Furigana', 'usces');
		$arr_column['zipcode'] = __('Zip', 'usces');
		$arr_column['country'] = __('Country', 'usces');
		$arr_column['pref'] = __('Province', 'usces');
		$arr_column['address1'] = __('city', 'usces');
		$arr_column['address2'] = __('numbers', 'usces');
		$arr_column['address3'] = __('building name', 'usces');
		$arr_column['tel'] = __('Phone number', 'usces');
		$arr_column['fax'] = __('FAX number', 'usces');
		$arr_column['email'] = __('e-mail', 'usces');
		$arr_column['note'] = __('Notes', 'usces');

		foreach ( (array)$this->cscs_meta as $key => $value ){
		//	$key = str_replace(array('-', '.', ' ', ','), '_', $key);
			$cscs_key = 'cscs_' . $key;
			$cscs_name = $value['name'];
			$arr_column[$cscs_key] = $cscs_name;
		}
		
		foreach ( (array)$this->csod_meta as $key => $value ){
		//	$key = str_replace(array('-', '.', ' ', ','), '_', $key);
			$csod_key = 'csod_' . $key;
			$csod_name = $value['name'];
			$arr_column[$csod_key] = $csod_name;
		}
	
		$arr_column = apply_filters( 'usces_filter_orderlist_column', $arr_column, $this );
		$this->columns = $arr_column;
	}
	
	function get_column(){
		return $this->columns;
	}
	
//	function SetSelects()
//	{
//		global $wpdb;
//		
//		$status_sql = '';
//		foreach( $this->management_status as $status_key => $status_name ) {
//			$status_sql .= $wpdb->prepare(" WHEN LOCATE(%s, order_status) > 0 THEN %s", $status_key, $status_name );
//		}
//
//		$select = array(
//			"ID", 
//			"meta.meta_value AS deco_id", 
//			"DATE_FORMAT(order_date, '%Y-%m-%d %H:%i') AS date", 
//			"mem_id", 
//			"CONCAT(order_name1, ' ', order_name2) AS name", 
//			"order_pref AS pref", 
//			"order_delivery_method AS delivery_method", 
//			"(order_item_total_price - order_usedpoint + order_discount + order_shipping_charge + order_cod_fee + order_tax) AS total_price", 
//			"order_payment_name AS payment_name", 
//			"CASE WHEN LOCATE('noreceipt', order_status) > 0 THEN '".__('unpaid', 'usces')."' 
//				 WHEN LOCATE('receipted', order_status) > 0 THEN '".__('payment confirmed', 'usces')."' 
//				 WHEN LOCATE('pending', order_status) > 0 THEN '".__('Pending', 'usces')."' 
//				 ELSE '&nbsp;' 
//			END AS receipt_status", 
//			"CASE {$status_sql} 
//				 ELSE '".__('new order', 'usces')."' 
//			END AS order_status", 
//			"order_modified"
//		);
//		$this->selectSql = apply_filters( 'usces_filter_order_list_sql_select', $select, $status_sql, $this );
//	}

//	function SetJoinTables()
//	{
//		global $wpdb;
//		$meta_table = $wpdb->prefix.'usces_order_meta';
//		$ordercart_table = $wpdb->prefix.'usces_ordercart';
//		$ordercartmeta_table = $wpdb->prefix.'usces_ordercart_meta';
//		$join_table = array(
//			" LEFT JOIN {$meta_table} AS meta ON ID = meta.order_id AND meta.meta_key = 'dec_order_id'"." \n",
//			" LEFT JOIN {$ordercart_table} AS cart ON ID = cart.order_id"." \n"
//		);
//		$this->joinTableSql = apply_filters( 'usces_filter_order_list_sql_jointable', $join_table, $meta_table, $this );
//	}

	function MakeTable()
	{
		$this->SetParam();

		switch ($this->action){

			case 'searchIn':
				$this->SearchIn();
				$res = $this->GetRows();
				break;

			case 'searchOut':
				$this->SearchOut();
				$res = $this->GetRows();
				break;

			case 'returnList':
			case 'changeSort':
			case 'changePage':
				$res = $this->GetRows();
				break;

			case 'collective_receipt_status':
				check_admin_referer( 'order_list', 'wc_nonce' );
				usces_all_change_order_reciept($this);
				$res = $this->GetRows();
				break;

			case 'collective_estimate_status':
			case 'collective_process_status':
				check_admin_referer( 'order_list', 'wc_nonce' );
				usces_all_change_order_status($this);
				$res = $this->GetRows();
				break;

			case 'collective_delete':
				check_admin_referer( 'order_list', 'wc_nonce' );
				usces_all_delete_order_data($this);
				$this->SetTotalRow();
				$res = $this->GetRows();
				break;

			case 'refresh':
			default:
				$this->SetDefaultParam();
				$res = $this->GetRows();
				break;
		}

		$this->SetNavi();
		$this->SetHeaders();
		$this->SetSESSION();

		if($res){

			return TRUE;

		}else{
			return FALSE;
		}
	}

	//DefaultParam
	function SetDefaultParam()
	{
		unset($_SESSION[$this->table]);
		$this->startRow = 0;
		$this->currentPage = 1;
		if(isset($_SESSION[$this->table]['arr_search'])){
			$this->arr_search = $_SESSION[$this->table]['arr_search'];
		}else{
			$arr_search = array('period'=>array('',''), 'order_column'=>array('',''), 'order_word'=>array('',''), 'order_word_term'=>array('contain','contain'), 'order_term'=>'AND', 'product_column'=>array('',''), 'product_word'=>array('',''), 'product_word_term'=>array('contain','contain'), 'option_word'=>array('',''), 'product_term'=>'AND' );
			$this->arr_search = apply_filters( 'usces_filter_order_list_arr_search', $arr_search, $this );
		}

		$this->searchWhere =  '';
		$this->searchHaving =  '';
		$this->sortColumn = 'ID';
		foreach($this->columns as $key => $value ){
			$this->sortSwitchs[$key] = 'DESC';
		}
	
		$this->SetTotalRow();
	}

	function SetParam()
	{
		$this->startRow = ($this->currentPage-1) * $this->maxRow;
	}

	function SetParamByQuery()
	{
		if(isset($_REQUEST['changePage'])){

			$this->action = 'changePage';
			$this->currentPage = (int)$_REQUEST['changePage'];
			$this->sortColumn = $_SESSION[$this->table]['sortColumn'];
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->searchWhere = $_SESSION[$this->table]['searchWhere'];
			$this->searchHaving = $_SESSION[$this->table]['searchHaving'];
			$this->arr_search = $_SESSION[$this->table]['arr_search'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];
			$this->selectedRow = $_SESSION[$this->table]['selectedRow'];

		}else if(isset($_REQUEST['returnList'])){
		
			$this->action = 'returnList';
			$this->currentPage = $_SESSION[$this->table]['currentPage'];
			$this->sortColumn = $_SESSION[$this->table]['sortColumn'];
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->searchWhere = $_SESSION[$this->table]['searchWhere'];
			$this->searchHaving = $_SESSION[$this->table]['searchHaving'];
			$this->arr_search = $_SESSION[$this->table]['arr_search'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];
			$this->selectedRow = $_SESSION[$this->table]['selectedRow'];
			
		}else if(isset($_REQUEST['changeSort'])){

			$this->action = 'changeSort';
			$this->sortOldColumn = $this->sortColumn;
			$this->sortColumn = str_replace('`', '', $_REQUEST['changeSort']);
			$this->sortColumn = str_replace(',', '', $this->sortColumn);
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->sortSwitchs[$this->sortColumn] = ('ASC' == $_REQUEST['switch']) ? 'ASC' : 'DESC';
			$this->currentPage = $_SESSION[$this->table]['currentPage'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->searchWhere = $_SESSION[$this->table]['searchWhere'];
			$this->searchHaving = $_SESSION[$this->table]['searchHaving'];
			$this->arr_search = $_SESSION[$this->table]['arr_search'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];
			$this->selectedRow = $_SESSION[$this->table]['selectedRow'];

		} else if(isset($_REQUEST['searchIn'])){

			$this->action = 'searchIn';
			$this->arr_search['order_column'][0] = !WCUtils::is_blank($_REQUEST['search']['order_column'][0]) ? str_replace('`', '', $_REQUEST['search']['order_column'][0]) : '';
			$this->arr_search['order_column'][1] = !WCUtils::is_blank($_REQUEST['search']['order_column'][1]) ? str_replace('`', '', $_REQUEST['search']['order_column'][1]) : '';
			$this->arr_search['order_word'][0] = !WCUtils::is_blank($_REQUEST['search']['order_word'][0]) ? trim($_REQUEST['search']['order_word'][0]) : '';
			$this->arr_search['order_word'][1] = !WCUtils::is_blank($_REQUEST['search']['order_word'][1]) ? trim($_REQUEST['search']['order_word'][1]) : '';
			$this->arr_search['order_word_term'][0] = isset($_REQUEST['search']['order_word_term'][0]) ? $_REQUEST['search']['order_word_term'][0] : 'contain';
			$this->arr_search['order_word_term'][1] = isset($_REQUEST['search']['order_word_term'][1]) ? $_REQUEST['search']['order_word_term'][1] : 'contain';
			if( WCUtils::is_blank($_REQUEST['search']['order_column'][0]) ){
				$this->arr_search['order_column'][1] = '';
				$this->arr_search['order_word'][0] = '';
				$this->arr_search['order_word'][1] = '';
				$this->arr_search['order_word_term'][0] = 'contain';
				$this->arr_search['order_word_term'][1] = 'contain';
			}
			$this->arr_search['order_term'] = $_REQUEST['search']['order_term'];
			$this->arr_search['product_column'][0] = !WCUtils::is_blank($_REQUEST['search']['product_column'][0]) ? str_replace('`', '', $_REQUEST['search']['product_column'][0]) : '';
			$this->arr_search['product_column'][1] = !WCUtils::is_blank($_REQUEST['search']['product_column'][1]) ? str_replace('`', '', $_REQUEST['search']['product_column'][1]) : '';
			$this->arr_search['product_word'][0] = !WCUtils::is_blank($_REQUEST['search']['product_word'][0]) ? trim($_REQUEST['search']['product_word'][0]) : '';
			$this->arr_search['product_word'][1] = !WCUtils::is_blank($_REQUEST['search']['product_word'][1]) ? trim($_REQUEST['search']['product_word'][1]) : '';
			$this->arr_search['product_word_term'][0] = isset($_REQUEST['search']['product_word_term'][0]) ? $_REQUEST['search']['product_word_term'][0] : 'contain';
			$this->arr_search['product_word_term'][1] = isset($_REQUEST['search']['product_word_term'][1]) ? $_REQUEST['search']['product_word_term'][1] : 'contain';
			$this->arr_search['option_word'][0] = (isset($_REQUEST['search']['option_word'][0]) && !WCUtils::is_blank($_REQUEST['search']['option_word'][0])) ? trim($_REQUEST['search']['option_word'][0]) : '';
			$this->arr_search['option_word'][1] = (isset($_REQUEST['search']['option_word'][1]) && !WCUtils::is_blank($_REQUEST['search']['option_word'][1])) ? trim($_REQUEST['search']['option_word'][1]) : '';
			if( WCUtils::is_blank($_REQUEST['search']['product_column'][0]) ){
				$this->arr_search['product_column'][1] = '';
				$this->arr_search['product_word'][0] = '';
				$this->arr_search['product_word'][1] = '';
				$this->arr_search['product_word_term'][0] = 'contain';
				$this->arr_search['product_word_term'][1] = 'contain';
				$this->arr_search['option_word'][0] = '';
				$this->arr_search['option_word'][1] = '';
			}
			$this->arr_search['product_term'] = $_REQUEST['search']['product_term'];
//			$this->arr_search['period'] = isset($_REQUEST['search']['period']) ? intval($_REQUEST['search']['period']) : 0;
			$this->currentPage = 1;
			$this->sortColumn = $_SESSION[$this->table]['sortColumn'];
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];

		}else if(isset($_REQUEST['searchOut'])){

			$this->action = 'searchOut';
			$this->arr_search['column'] = '';
			$this->arr_search['word'] = '';
			$this->arr_search['order_column'][0] = '';
			$this->arr_search['order_column'][1] = '';
			$this->arr_search['order_word'][0] = '';
			$this->arr_search['order_word'][1] = '';
			$this->arr_search['order_word_term'][0] = 'contain';
			$this->arr_search['order_word_term'][1] = 'contain';
			$this->arr_search['order_term'] = 'AND';
			$this->arr_search['product_column'][0] = '';
			$this->arr_search['product_column'][1] = '';
			$this->arr_search['product_word'][0] = '';
			$this->arr_search['product_word'][1] = '';
			$this->arr_search['product_word_term'][0] = 'contain';
			$this->arr_search['product_word_term'][1] = 'contain';
			$this->arr_search['option_word'][0] = '';
			$this->arr_search['option_word'][1] = '';
			$this->arr_search['product_term'] = 'AND';
//			$this->arr_search['period'] = $_SESSION[$this->table]['arr_search']['period'];
			$this->currentPage = 1;
			$this->sortColumn = $_SESSION[$this->table]['sortColumn'];
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];

		}else if(isset($_REQUEST['refresh'])){

			$this->action = 'refresh';
			$this->currentPage = $_SESSION[$this->table]['currentPage'];
			$this->sortColumn = $_SESSION[$this->table]['sortColumn'];
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->searchWhere = $_SESSION[$this->table]['searchWhere'];
			$this->searchHaving = $_SESSION[$this->table]['searchHaving'];
			$this->arr_search = $_SESSION[$this->table]['arr_search'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];
			$this->selectedRow = $_SESSION[$this->table]['selectedRow'];

		}else if(isset($_REQUEST['collective'])){

			$this->action = 'collective_' . str_replace(',', '', $_POST['allchange']['column']);
			$this->currentPage = $_SESSION[$this->table]['currentPage'];
			$this->sortColumn = $_SESSION[$this->table]['sortColumn'];
			$this->sortSwitchs = $_SESSION[$this->table]['sortSwitchs'];
			$this->userHeaderNames = $_SESSION[$this->table]['userHeaderNames'];
			$this->searchWhere = $_SESSION[$this->table]['searchWhere'];
			$this->searchHaving = $_SESSION[$this->table]['searchHaving'];
			$this->arr_search = $_SESSION[$this->table]['arr_search'];
			$this->totalRow = $_SESSION[$this->table]['totalRow'];
			$this->selectedRow = $_SESSION[$this->table]['selectedRow'];

		}else{

			$this->action = 'default';
		}
	}

	//GetRows
	function GetRows(){
		global $wpdb;

		$order_table = $wpdb->prefix.'usces_order';
		$order_meta_table = $wpdb->prefix.'usces_order_meta';
		$ordercart_table = $wpdb->prefix.'usces_ordercart';
		$ordercart_meta_table = $wpdb->prefix.'usces_ordercart_meta';
		
		$where = $this->GetWhere();
		$having = $this->GetHaving();

		$join = "";
		$cscs = "";
		$csod = "";

		$tracking_key = apply_filters( 'usces_filter_tracking_meta_key', 'tracking_number');
		$join .= "LEFT JOIN {$order_meta_table} AS deco ON ord.ID = deco.order_id AND deco.meta_key = 'dec_order_id' " . "\n";
		$join .= "LEFT JOIN {$order_meta_table} AS trans ON ord.ID = trans.order_id AND trans.meta_key = 'wc_trans_id' " . "\n";
		$join .= "LEFT JOIN {$order_meta_table} AS country ON ord.ID = country.order_id AND country.meta_key = 'customer_country' " . "\n";
		$join .= "LEFT JOIN {$order_meta_table} AS memo ON ord.ID = memo.order_id AND memo.meta_key = 'order_memo' " . "\n";
		$join .= $wpdb->prepare("LEFT JOIN {$order_meta_table} AS trac ON ord.ID = trac.order_id AND trac.meta_key = %s ", $tracking_key ) . "\n";

		foreach($this->columns as $key => $value ){
			if( 'cscs_' === substr($key, 0, 5) ){
				$join .= $wpdb->prepare(" LEFT JOIN {$order_meta_table} AS `p{$key}` ON ord.ID = `p{$key}`.order_id AND `p{$key}`.meta_key = %s ", $key ) . "\n";
				$cscs .= ', `p' . $key . '`.meta_value AS `' . $key . "`\n";
			}
		}
		
		foreach($this->columns as $key => $value ){
			if( 'csod_' === substr($key, 0, 5) ){
				$join .= $wpdb->prepare(" LEFT JOIN {$order_meta_table} AS `p{$key}` ON ord.ID = `p{$key}`.order_id AND `p{$key}`.meta_key = %s ", $key ) . "\n";
				$csod .= ', `p' . $key . '`.meta_value AS `' . $key . "`\n";
			}
		}
		
		if( $where ){
			
			$join .= " LEFT JOIN {$ordercart_table} AS cart ON ord.ID = cart.order_id " . "\n";
			$csod .= ', cart.item_code , cart.item_name , cart.sku_code , cart.sku_name ' . "\n";
			
			$join .= " LEFT JOIN {$ordercart_meta_table} AS itemopt ON cart.cart_id = itemopt.cart_id AND itemopt.meta_type = 'option' " . "\n";
			$csod .= ', itemopt.meta_key, itemopt.meta_value ' . "\n";
		}
		$join = apply_filters( 'usces_filter_orderlist_sql_jointable', $join, $tracking_key, $this );
		
		$group = ' GROUP BY `ID` ';
		$switch = ( 'ASC' == $this->sortSwitchs[$this->sortColumn] ) ? 'ASC' : 'DESC';

		$order = ' ORDER BY `' . esc_sql($this->sortColumn) . '` ' . $switch;
		$order = apply_filters( 'usces_filter_orderlist_sql_order', $order, $this->sortColumn, $switch, $this );

		$query = $wpdb->prepare("SELECT 
			memo.meta_value AS admin_memo, 
			ord.ID AS ID, 
			deco.meta_value AS deco_id,
			DATE_FORMAT(ord.order_date, %s) AS order_date,
			ord.order_modified AS order_modified,
			ord.order_status AS estimate_status,
			ord.order_status AS process_status,
			trac.meta_value AS tracking_number,
			ord.order_payment_name AS payment_name,
			trans.meta_value AS wc_trans_id,
			ord.order_status AS receipt_status,
			ord.order_item_total_price AS item_total_price,
			ord.order_getpoint AS getpoint,
			ord.order_usedpoint AS usedpoint,
			ord.order_discount AS discount,
			ord.order_shipping_charge AS shipping_charge,
			ord.order_cod_fee AS cod_fee,
			ord.order_tax AS tax,
			(ord.order_item_total_price - ord.order_usedpoint + ord.order_discount + ord.order_shipping_charge + ord.order_cod_fee + ord.order_tax) AS total_price,
			ord.order_delivery_method AS deli_method,
			ord.order_delivery AS deli_name,
			ord.order_delivery_time AS deli_time,
			ord.order_delivery_date AS deli_date,
			ord.order_delidue_date AS delidue_date,
			ord.mem_id AS mem_id, 
			ord.order_name1 AS name1, 
			ord.order_name2 AS name2, 
			ord.order_name3 AS name3, 
			ord.order_name4 AS name4, 
			ord.order_zip AS zipcode, 
			country.meta_value AS country, 
			ord.order_pref AS pref, 
			ord.order_address1 AS address1, 
			ord.order_address2 AS address2, 
			ord.order_address3 AS address3, 
			ord.order_tel AS tel, 
			ord.order_fax AS fax, 
			ord.order_email AS email, 
			ord.order_note AS note 
			{$cscs}
			{$csod}
			FROM {$this->table} AS ord "
			, '%Y-%m-%d %H:%i' );
		$query = apply_filters( 'usces_filter_orderlist_sql_select', $query, $cscs, $csod, $this );

		$query .= $join . $where . "\n" . $group . "\n" . $having . "\n" . $order;
		//$query .= $join . $group . $having . $order;
//		usces_p($query);
		$rows = $wpdb->get_results($query, ARRAY_A);
//		usces_p($rows);
		$this->selectedRow = count($rows);
		if($this->pageLimit == 'on') {
			$this->rows = array_slice($rows, $this->startRow, $this->maxRow);
			$this->currentPageIds = array();
			foreach($this->rows as $row){
				$this->currentPageIds[] = $row['ID'];
			}
		}else{
			$this->rows = $rows;
		}







//		global $wpdb;
//		$where = $this->GetWhere();
//		$order = ' ORDER BY `' . esc_sql($this->sortColumn) . '` ' . esc_sql($this->sortSwitchs[$this->sortColumn]);
//		$order = apply_filters( 'usces_filter_order_list_get_orderby', $order, $this );
//
//		$select = '';
//		foreach( $this->selectSql as $value ) {
//			$select .= $value.", ";
//		}
//		$select = rtrim( $select, ", " );
//		$select .= ", item_name, item_code";
//		$query = apply_filters( 'usces_filter_order_list_select', $select, $this);
//		$join_table = '';
//		foreach( $this->joinTableSql as $value ) {
//			$join_table .= $value;
//		}
//		$query = "SELECT ".$select." \n"."FROM {$this->table} "."\n".$join_table.$where."\n".$order;
//		$query = apply_filters( 'usces_filter_order_list_get_rows', $query, $this);
////usces_p($query);
//		$wpdb->show_errors();
//
//		$rows = $wpdb->get_results($query, ARRAY_A);
//		$this->selectedRow = count($rows);
//		if($this->pageLimit == 'off') {
//			$this->rows = (array)$rows;
//		} else {
//			$this->rows = array_slice((array)$rows, $this->startRow, $this->maxRow);
//		}

		return $this->rows;
	}

	function SetTotalRow()
	{
		global $wpdb;
		$query = "SELECT COUNT(ID) AS ct FROM {$this->table}".apply_filters( 'usces_filter_orderlist_sql_where', '', $this );
		$query = apply_filters( 'usces_filter_orderlist_set_total_row', $query, $this );
		$res = $wpdb->get_var( $query );
		$this->totalRow = $res;
	}

	function GetHaving(){
		global $wpdb;
	
		$lastmonth_s = date( 'Y-m-d H:i:s', mktime( 0, 0, 0, (current_time('n')-1),1,current_time('Y') ));
		$lastmonth_e = date( 'Y-m-d H:i:s', mktime( 23, 59, 59, current_time('n'),0,current_time('Y') ));
		$thismonth = date( 'Y-m-d H:i:s', mktime( 0, 0, 0, current_time('n'),1,current_time('Y') ));

		$query = '';
		if( 1 == $this->period['period'] ){
		
			$query = $wpdb->prepare(" order_date >= %s ", $thismonth );

		}elseif( 2 == $this->period['period'] ){
		
				$query = $wpdb->prepare(" order_date >= %s AND order_date <= %s ", $lastmonth_s, $lastmonth_e );

		}elseif( 3 == $this->period['period'] ){
		
			$start = $this->period['start'].' 00:00:00';
			$end = $this->period['end'].'23:59:59';
			if( !empty($this->period['start']) && !empty($this->period['end']) ){
			
				$query = $wpdb->prepare(" order_date >= %s AND order_date <= %s ", $start, $end );
			
			}elseif( empty($this->period['start']) && !empty($this->period['end']) ){
			
				$query = $wpdb->prepare(" order_date <= %s ", $end );
			
			}elseif( !empty($this->period['start']) && empty($this->period['end']) ){
			
				$query = $wpdb->prepare(" order_date >= %s ", $start );
			
			}
		
		}

		$str = '';

		if( !WCUtils::is_blank($this->searchHaving) ){
			if( !WCUtils::is_blank($query) ){
				$str .= ' HAVING ' . $this->searchHaving . ' AND ' . $query;
			}else{
				$str .= ' HAVING ' . $this->searchHaving;
			}
		}else{
			if( !WCUtils::is_blank($query) ){
				$str .= ' HAVING ' . $query;
			}
		}
		$str = apply_filters( 'usces_filter_orderlist_sql_having', $str, $query, $this->searchHaving, $this->period, $this );
		return $str;

	}

	
	function GetWhere(){
		$str = '';
		if(!WCUtils::is_blank($this->searchWhere)){
			$str .= ' WHERE ' . $this->searchWhere;
		}
		$str = apply_filters( 'usces_filter_orderlist_sql_where', $str, $this->searchWhere, $this );
		return $str;
	}

	function SearchIn(){
		global $wpdb;

		$this->searchWhere = '';
		$this->searchHaving = '';
		
		if( !empty($this->arr_search['order_column'][0]) && !WCUtils::is_blank($this->arr_search['order_word'][0]) ){
			switch( $this->arr_search['order_word_term'][0] ){
				case 'notcontain':
					$wordterm0 = ' NOT LIKE %s';
					$word0 = "%".$this->arr_search['order_word'][0]."%";
					break;
				case 'equal':
					$wordterm0 = ' = %s';
					$word0 = $this->arr_search['order_word'][0];
					break;
				case 'morethan':
					$wordterm0 = ' > %d';
					$word0 = $this->arr_search['order_word'][0];
					break;
				case 'lessthan':
					$wordterm0 = ' < %d';
					$word0 = $this->arr_search['order_word'][0];
					break;
				case 'contain':
				default:
					$wordterm0 = ' LIKE %s';
					$word0 = "%".$this->arr_search['order_word'][0]."%";
					break;
			}

			switch( $this->arr_search['order_word_term'][1] ){
				case 'notcontain':
					$wordterm1 = ' NOT LIKE %s';
					$word1 = "%".$this->arr_search['order_word'][1]."%";
					break;
				case 'equal':
					$wordterm1 = ' = %s';
					$word1 = $this->arr_search['order_word'][1];
					break;
				case 'morethan':
					$wordterm1 = ' > %d';
					$word1 = $this->arr_search['order_word'][1];
					break;
				case 'lessthan':
					$wordterm1 = ' < %d';
					$word1 = $this->arr_search['order_word'][1];
					break;
				case 'contain':
				default:
					$wordterm1 = ' LIKE %s';
					$word1 = "%".$this->arr_search['order_word'][1]."%";
					break;
			}

			$this->searchHaving .= ' ( ';
			
			if( 'estimate_status' == $this->arr_search['order_column'][0] && 'frontorder' == $this->arr_search['order_word'][0] ){
				//$this->searchHaving .= $wpdb->prepare('( ' . esc_sql($this->arr_search['order_column'][0]) . ' NOT LIKE %s AND ' . esc_sql($this->arr_search['order_column'][0]) . ' NOT LIKE %s )', "%adminorder%", "%estimate%");
				$search_estimate_status = apply_filters( 'usces_filter_orderlist_search_estimate_status', array( "%adminorder%", "%estimate%" ) );
				$and = '';
				$this->searchHaving .= '( ';
				foreach( $search_estimate_status as $status ) {
					$this->searchHaving .= $and.esc_sql($this->arr_search['order_column'][0])." NOT LIKE '".$status."'";
					$and = ' AND ';
				}
				$this->searchHaving .= ' ) ';
			
			}elseif( 'process_status' == $this->arr_search['order_column'][0] && 'neworder' == $this->arr_search['order_word'][0] ){
				//$this->searchHaving .= $wpdb->prepare('( ' . esc_sql($this->arr_search['order_column'][0]) . ' NOT LIKE %s AND ' . esc_sql($this->arr_search['order_column'][0]) . ' NOT LIKE %s AND ' . esc_sql($this->arr_search['order_column'][0]) . ' NOT LIKE %s )', "%duringorder%", "%cancel%", "%completion%");
				$search_process_status = apply_filters( 'usces_filter_orderlist_search_process_status', array( "%duringorder%", "%cancel%", "%completion%" ) );
				$and = '';
				$this->searchHaving .= '( ';
				foreach( $search_process_status as $status ) {
					$this->searchHaving .= $and.esc_sql($this->arr_search['order_column'][0])." NOT LIKE '".$status."'";
					$and = ' AND ';
				}
				$this->searchHaving .= ' ) ';
			
			}elseif( 'deli_method' == $this->arr_search['order_column'][0] ){
				$this->searchHaving .= $wpdb->prepare( esc_sql($this->arr_search['order_column'][0]) . ' = %d', $this->arr_search['order_word'][0] );

			}elseif( 'cscs_' == substr($this->arr_search['order_column'][0], 0, 5) || 'csod_' == substr($this->arr_search['order_column'][0], 0, 5) ){
				$this->searchHaving .= $wpdb->prepare('`p' . esc_sql($this->arr_search['order_column'][0]) . '`.meta_value' . $wordterm0, $word0);

			}else{
				$this->searchHaving .= $wpdb->prepare( esc_sql($this->arr_search['order_column'][0]) . $wordterm0, $word0);
			}
			
			if( !empty($this->arr_search['order_column'][1]) && !WCUtils::is_blank($this->arr_search['order_word'][1]) ){

				$this->searchHaving .= ' ' . $this->arr_search['order_term'] . ' ';
				if( 'estimate_status' == $this->arr_search['order_column'][1] && 'frontorder' == $this->arr_search['order_word'][1] ){
					//$this->searchHaving .= $wpdb->prepare('( ' . esc_sql($this->arr_search['order_column'][1]) . ' NOT LIKE %s AND ' . esc_sql($this->arr_search['order_column'][1]) . ' NOT LIKE %s )', "%adminorder%", "%estimate%");
					$search_estimate_status = apply_filters( 'usces_filter_orderlist_search_estimate_status', array( "%adminorder%", "%estimate%" ) );
					$and = '';
					$this->searchHaving .= '( ';
					foreach( $search_estimate_status as $status ) {
						$this->searchHaving .= $and.esc_sql($this->arr_search['order_column'][1])." NOT LIKE '".$status."'";
						$and = ' AND ';
					}
					$this->searchHaving .= ' ) ';
				
				}elseif( 'process_status' == $this->arr_search['order_column'][1] && 'neworder' == $this->arr_search['order_word'][1] ){
					//$this->searchHaving .= $wpdb->prepare('( ' . esc_sql($this->arr_search['order_column'][1]) . ' NOT LIKE %s AND ' . esc_sql($this->arr_search['order_column'][1]) . ' NOT LIKE %s AND ' . esc_sql($this->arr_search['order_column'][1]) . ' NOT LIKE %s )', "%duringorder%", "%cancel%", "%completion%");
					$search_process_status = apply_filters( 'usces_filter_orderlist_search_process_status', array( "%duringorder%", "%cancel%", "%completion%" ) );
					$and = '';
					$this->searchHaving .= '( ';
					foreach( $search_process_status as $status ) {
						$this->searchHaving .= $and.esc_sql($this->arr_search['order_column'][1])." NOT LIKE '".$status."'";
						$and = ' AND ';
					}
					$this->searchHaving .= ' ) ';
				
				}elseif( 'deli_method' == $this->arr_search['order_column'][1] ){
					$this->searchHaving .= $wpdb->prepare( esc_sql($this->arr_search['order_column'][1]) . ' = %d', $this->arr_search['order_word'][1] );

				}elseif( 'cscs_' == substr($this->arr_search['order_column'][1], 0, 5) || 'csod_' == substr($this->arr_search['order_column'][1], 0, 5) ){
					$this->searchHaving .= $wpdb->prepare('`p' . esc_sql($this->arr_search['order_column'][1]) . '`.meta_value' . $wordterm1, $word1);

				}else{
					$this->searchHaving .= $wpdb->prepare( esc_sql($this->arr_search['order_column'][1]) . $wordterm1, $word1);
				}
			
			}
			
			
			$this->searchHaving .= ' ) ';
		}
		
		if( !empty($this->arr_search['product_column'][0]) && !WCUtils::is_blank($this->arr_search['product_word'][0]) ){

			switch( $this->arr_search['product_word_term'][0] ){
				case 'notcontain':
					$prowordterm0 = ' NOT LIKE %s';
					$proword0 = "%".$this->arr_search['product_word'][0]."%";
					break;
				case 'equal':
					$prowordterm0 = ' = %s';
					$proword0 = $this->arr_search['product_word'][0];
					break;
				case 'morethan':
					$prowordterm0 = ' > %d';
					$proword0 = $this->arr_search['product_word'][0];
					break;
				case 'lessthan':
					$prowordterm0 = ' < %d';
					$proword0 = $this->arr_search['product_word'][0];
					break;
				case 'contain':
				default:
					$prowordterm0 = ' LIKE %s';
					$proword0 = "%".$this->arr_search['product_word'][0]."%";
					break;
			}

			switch( $this->arr_search['product_word_term'][1] ){
				case 'notcontain':
					$prowordterm1 = ' NOT LIKE %s';
					$proword1 = "%".$this->arr_search['product_word'][1]."%";
					break;
				case 'equal':
					$prowordterm1 = ' = %s';
					$proword1 = $this->arr_search['product_word'][1];
					break;
				case 'morethan':
					$prowordterm1 = ' > %d';
					$proword1 = $this->arr_search['product_word'][1];
					break;
				case 'lessthan':
					$prowordterm1 = ' < %d';
					$proword1 = $this->arr_search['product_word'][1];
					break;
				case 'contain':
				default:
					$prowordterm1 = ' LIKE %s';
					$proword1 = "%".$this->arr_search['product_word'][1]."%";
					break;
			}

			$this->searchWhere .= ' ( ';
			
			if( 'item_option' == $this->arr_search['product_column'][0] ){
			
				$this->searchWhere .= $wpdb->prepare( '( itemopt.meta_key LIKE %s AND itemopt.meta_value LIKE %s )' , "%".$this->arr_search['product_word'][0]."%" , "%".$this->arr_search['option_word'][0]."%");
		
			}else{
				$this->searchWhere .= $wpdb->prepare( esc_sql($this->arr_search['product_column'][0]) . $prowordterm0, $proword0);
			}
			
		
			if( !empty($this->arr_search['product_column'][1]) && !WCUtils::is_blank($this->arr_search['product_word'][1]) ){

				$this->searchWhere .= ' ' . $this->arr_search['product_term'] . ' ';
				if( 'item_option' == $this->arr_search['product_column'][1] ){
				
					$this->searchWhere .= $wpdb->prepare( '( itemopt.meta_key LIKE %s AND itemopt.meta_value LIKE %s )' , "%".$this->arr_search['product_word'][1]."%" , "%".$this->arr_search['option_word'][1]."%");
			
				}else{
					$this->searchWhere .= $wpdb->prepare( esc_sql($this->arr_search['product_column'][1]) . $prowordterm1, $proword1);
				}

			}
			$this->searchWhere .= ' ) ';
		}


//		switch ($this->arr_search['column']) {
//			case 'ID':
//				$column = 'ID';
//				$this->searchSql = $wpdb->prepare('`' . $column . '` = %d', $this->arr_search['word']['ID']);
//				break;
//			case 'deco_id':
//				$column = 'deco_id';
//				$this->searchSql = $wpdb->prepare('`' . $column . '` LIKE %s', "%" . $this->arr_search['word']['deco_id'] . "%");
//				break;
//			case 'date':
//				$column = 'date';
//				$this->searchSql = $wpdb->prepare('`' . $column . '` LIKE %s', "%" . $this->arr_search['word']['date'] . "%");
//				break;
//			case 'mem_id':
//				$column = 'mem_id';
//				$this->searchSql = $wpdb->prepare('`' . $column . '` = %d', $this->arr_search['word']['mem_id']);
//				break;
//			case 'name':
//				$column = 'name';
//				$this->searchSql = $wpdb->prepare('`' . $column . '` LIKE %s', "%" . $this->arr_search['word']['name'] . "%");
//				break;
//			case 'order_modified':
//				$column = 'order_modified';
//				$this->searchSql = $wpdb->prepare('`' . $column . '` LIKE %s', "%" . $this->arr_search['word']['order_modified'] . "%");
//				break;
//			case 'pref':
//				$column = 'pref';
//				$this->searchSql = $wpdb->prepare('`' . $column . "` = %s", $this->arr_search['word']['pref']);
//				break;
//			case 'delivery_method':
//				$column = 'delivery_method';
//				$this->searchSql = $wpdb->prepare('`' . $column . "` = %s", $this->arr_search['word']['delivery_method']);
//				break;
//			case 'payment_name':
//				$column = 'payment_name';
//				$this->searchSql = $wpdb->prepare('`' . $column . "` = %s", $this->arr_search['word']['payment_name']);
//				break;
//			case 'receipt_status':
//				$column = 'receipt_status';
//				$this->searchSql = $wpdb->prepare('`' . $column . "` = %s", $this->arr_search['word']['receipt_status']);
//				break;
//			case 'order_status':
//				$column = 'order_status';
//				$this->searchSql = $wpdb->prepare('`' . $column . "` = %s", $this->arr_search['word']['order_status']);
//				break;
//		}
//		switch ($this->arr_search['sku']) {
//			case 'item_code':
//				$column = 'item_code';
//				$this->searchSkuSql = $wpdb->prepare('`' . $column . '` LIKE %s', "%" . $this->arr_search['skuword']['item_code'] . "%");
//				break;
//			case 'item_name':
//				$column = 'item_name';
//				$this->searchSkuSql = $wpdb->prepare('`' . $column . '` LIKE %s', "%" . $this->arr_search['skuword']['item_name'] . "%");
//				break;
//		}
	}

	function SearchOut(){
		$this->searchWhere = '';
		$this->searchHaving = '';
	}

	function SetNavi()
	{
		$this->lastPage = ceil($this->selectedRow / $this->maxRow);
		$this->previousPage = ($this->currentPage - 1 == 0) ? 1 : $this->currentPage - 1;
		$this->nextPage = ($this->currentPage + 1 > $this->lastPage) ? $this->lastPage : $this->currentPage + 1;

		for($i=0; $i<$this->naviMaxButton; $i++){
			if($i > $this->lastPage-1) break;
			if($this->lastPage <= $this->naviMaxButton) {
				$box[] = $i+1;
			}else{
				if($this->currentPage <= 6) {
					$label = $i + 1;
					$box[] = $label;
				}else{
					$label = $i + 1 + $this->currentPage - 6;
					$box[] = $label;
					if($label == $this->lastPage) break;
				}
			}
		}

		$html = '';
		$html .= '<ul class="clearfix">'."\n";
		$html .= '<li class="rowsnum">' . $this->selectedRow . ' / ' . $this->totalRow . ' ' . __('cases', 'usces') . '</li>' . "\n";
		if(($this->currentPage == 1) || ($this->selectedRow == 0)){
			$html .= '<li class="navigationStr">first&lt;&lt;</li>' . "\n";
			$html .= '<li class="navigationStr">prev&lt;</li>'."\n";
		}else{
			$html .= '<li class="navigationStr"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=1">first&lt;&lt;</a></li>' . "\n";
			$html .= '<li class="navigationStr"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=' . $this->previousPage . '">prev&lt;</a></li>'."\n";
		}
		if($this->selectedRow > 0) {
			for($i=0; $i<count($box); $i++){
				if($box[$i] == $this->currentPage){
					$html .= '<li class="navigationButtonSelected"><span>' . $box[$i] . '</span></li>'."\n";
				}else{
					$html .= '<li class="navigationButton"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=' . $box[$i] . '">' . $box[$i] . '</a></li>'."\n";
				}
			}
		}
//		if(($this->currentPage == $this->lastPage) || ($this->selectedRow == 0)){
//			$html .= '<li class="navigationStr">&gt;next</li>'."\n";
//			$html .= '<li class="navigationStr">&gt;&gt;last</li>'."\n";
//		}else{
//			$html .= '<li class="navigationStr"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=' . $this->nextPage . '">&gt;next</a></li>'."\n";
//			$html .= '<li class="navigationStr"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=' . $this->lastPage . '">&gt;&gt;last</a></li>'."\n";
//		}
//		if($this->searchSwitchStatus == 'OFF'){
//			$html .= '<li class="rowsnum"><a style="cursor:pointer;" id="searchVisiLink">' . __('Show the Operation field', 'usces') . '</a>'."\n";
//		}else{
//			$html .= '<li class="rowsnum"><a style="cursor:pointer;" id="searchVisiLink">' . __('Hide the Operation field', 'usces') . '</a>'."\n";
//		}
//
//		$html .= '<li class="refresh"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&refresh">' . __('updates it to latest information', 'usces') . '</a></li>' . "\n";

		if(($this->currentPage == $this->lastPage) || ($this->selectedRow == 0)){
			$html .= '<li class="navigationStr">&gt;next</li>'."\n";
			$html .= '<li class="navigationStr">&gt;&gt;last</li>'."\n";
		}else{
			$html .= '<li class="navigationStr"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=' . $this->nextPage . '">&gt;next</a></li>'."\n";
			$html .= '<li class="navigationStr"><a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changePage=' . $this->lastPage . '">&gt;&gt;last</a></li>'."\n";
		}
		$html .= '</ul>'."\n";

		$this->dataTableNavigation = $html;
	}

	function SetSESSION()
	{
		$_SESSION[$this->table]['startRow'] = $this->startRow;		//表示開始行番号
		$_SESSION[$this->table]['sortColumn'] = $this->sortColumn;	//現在ソート中のフィールド
		$_SESSION[$this->table]['totalRow'] = $this->totalRow;		//全行数
		$_SESSION[$this->table]['selectedRow'] = $this->selectedRow;	//絞り込まれた行数
		$_SESSION[$this->table]['currentPage'] = $this->currentPage;	//現在のページNo
		$_SESSION[$this->table]['previousPage'] = $this->previousPage;	//前のページNo
		$_SESSION[$this->table]['nextPage'] = $this->nextPage;		//次のページNo
		$_SESSION[$this->table]['lastPage'] = $this->lastPage;		//最終ページNo
		$_SESSION[$this->table]['userHeaderNames'] = $this->userHeaderNames;//全てのフィールド
		$_SESSION[$this->table]['headers'] = $this->headers;//表示するヘッダ文字列
		$_SESSION[$this->table]['sortSwitchs'] = $this->sortSwitchs;	//各フィールド毎の昇順降順スイッチ
		$_SESSION[$this->table]['dataTableNavigation'] = $this->dataTableNavigation;
		$_SESSION[$this->table]['searchWhere'] = $this->searchWhere;
		$_SESSION[$this->table]['searchHaving'] = $this->searchHaving;
		$_SESSION[$this->table]['arr_search'] = $this->arr_search;
		if($this->pageLimit == 'on') {
			$_SESSION[$this->table]['currentPageIds'] = $this->currentPageIds;
		}
		do_action( 'usces_action_order_list_set_session', $this );
	}

	function SetHeaders()
	{
		foreach ($this->columns as $key => $value){
			if( 'admin_memo' == $key )
				continue;
				
			if($key == $this->sortColumn){
				if($this->sortSwitchs[$key] == 'ASC'){
					$str = __('[ASC]', 'usces');
					$switch = 'DESC';
				}else{
					$str = __('[DESC]', 'usces');
					$switch = 'ASC';
				}
				$this->headers[$key] = '<a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changeSort=' . $key . '&switch=' . $switch . '"><span class="sortcolumn">' . $value . ' ' . $str . '</span></a>';
			}else{
				$switch = $this->sortSwitchs[$key];
				$this->headers[$key] = '<a href="' . site_url() . '/wp-admin/admin.php?page=usces_orderlist&changeSort=' . $key . '&switch=' . $switch . '"><span>' . $value . '</span></a>';
			}
		}
		//$this->headers = array_keys($this->columns);
	}

	function GetSearchs()
	{
		return $this->arr_search;
	}

	function GetListheaders()
	{
		return $this->headers;
	}

	function GetDataTableNavigation()
	{
		return $this->dataTableNavigation;
	}

	function set_action_status($status, $message)
	{
		$this->action_status = $status;
		$this->action_message = $message;
	}

	function get_action_status()
	{
		return $this->action_status;
	}

	function get_action_message()
	{
		return $this->action_message;
	}
}


?>