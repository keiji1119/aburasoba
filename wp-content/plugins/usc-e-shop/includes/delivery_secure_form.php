<?php
if(isset($this))
	$usces = &$this;

$payments = usces_get_system_option( 'usces_payment_method', 'sort' );
$payments = apply_filters( 'usces_filter_available_payment_method', $payments );
foreach ( (array)$payments as $id => $array ) {
	if( !empty( $array['settlement'] ) ){

		switch( $array['settlement'] ){
			case 'acting_zeus_card':
				$paymod_id = 'zeus';

				if( 'on' != $usces->options['acting_settings'][$paymod_id]['card_activate'] 
					|| 'on' != $usces->options['acting_settings'][$paymod_id]['activate'] )
					continue;

				$cnum1 = isset( $_POST['cnum1'] ) ? esc_html($_POST['cnum1']) : '';
				$cnum2 = isset( $_POST['cnum2'] ) ? esc_html($_POST['cnum2']) : '';
				$cnum3 = isset( $_POST['cnum3'] ) ? esc_html($_POST['cnum3']) : '';
				$cnum4 = isset( $_POST['cnum4'] ) ? esc_html($_POST['cnum4']) : '';
				$securecode = isset( $_POST['securecode'] ) ? esc_html($_POST['securecode']) : '';
				$expyy = isset( $_POST['expyy'] ) ? esc_html($_POST['expyy']) : '';
				$expmm = isset( $_POST['expmm'] ) ? esc_html($_POST['expmm']) : '';
				$username = isset( $_POST['username_card'] ) ? esc_html($_POST['username_card']) : '';
				$howpay = isset( $_POST['howpay'] ) ? esc_html($_POST['howpay']) : '1';
				$cbrand = isset( $_POST['cbrand'] ) ? esc_html($_POST['cbrand']) : '';
				$div = isset( $_POST['div'] ) ? esc_html($_POST['div']) : '';

				$html .= '<input type="hidden" name="acting" value="zeus">'."\n";
				$html .= '<table class="customer_form" id="' . $paymod_id . '">'."\n";

				$pcid = NULL;
				$partofcard = NULL;
				if( $usces->is_member_logged_in() ){
					$member = $usces->get_member();
					if( !isset($_GET['re-enter']) ) {
						$pcid = $usces->get_member_meta_value( 'zeus_pcid', $member['ID'] );
						$partofcard = $usces->get_member_meta_value( 'zeus_partofcard', $member['ID'] );
					}
				}
				//if( 2 == $usces->options['acting_settings'][$paymod_id]['security'] && 'on' == $usces->options['acting_settings'][$paymod_id]['quickcharge'] && $pcid != NULL ){
				if( 'on' == $usces->options['acting_settings'][$paymod_id]['quickcharge'] && $pcid != NULL && $partofcard != NULL ){
					$html .= '<input name="cnum1" type="hidden" value="8888888888888882" />
					<input name="expyy" type="hidden" value="2010" />
					<input name="expmm" type="hidden" value="01" />
					<input name="username_card" type="hidden" value="QUICKCHARGE" />';
					$html .= '<tr>
					<th scope="row">'.__('The last four digits of your card number', 'usces').'</th>
					<td colspan="2"><p>' . $usces->get_member_meta_value( 'zeus_partofcard', $member['ID'] ) . ' (<a href="' . add_query_arg( array('page'=>'member_update_settlement', 're-enter'=>1), USCES_MEMBER_URL ) . '">'.__('Change of card information, click here', 'usces').'</a>)</p></td>
					</tr>';
					if( 1 == $usces->options['acting_settings'][$paymod_id]['security'] ){
						$html .= '<th scope="row">'.__('security code', 'usces').'</th>
						<td colspan="2"><input name="securecode" type="text" size="6" value="' . esc_attr($securecode) . '" />'.__('(Single-byte numbers only)', 'usces').'</td>
						</tr>';
					}

				}else{
					if( isset($_GET['page'] ) and 'member_update_settlement' == $_GET['page'] ) {
						$html .= '<tr>
						<th scope="row">'.__('The last four digits of your card number', 'usces').'</th>
						<td colspan="2"><p>' . $usces->get_member_meta_value( 'zeus_partofcard', $member['ID'] ). '</p></td>
						</tr>';
						$label = __('Card number after the change', 'usces').'<div style="font-size: 0.7em;color: inherit;font-weight: normal;">'.__('(If you do not want to change, Please in the blank)', 'usces').'</div>';
					} else {
						$label = __('card number', 'usces');
					}
					$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __('(Single-byte numbers only)','usces').'<div class="attention">'.__('* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.','usces').'</div>' );
					$html .= '<tr>
						<th scope="row">'.$label.'<input name="acting" type="hidden" value="zeus" /></th>
						<td colspan="2"><input name="cnum1" type="text" size="16" value="' . esc_attr($cnum1) . '" />'.$cardno_attention.'</td>
						</tr>';
					if( 1 == $usces->options['acting_settings'][$paymod_id]['security'] ){
						$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __('(Single-byte numbers only)','usces') );
						$html .= '<tr>
						<th scope="row">'.__('security code', 'usces').'</th>
						<td colspan="2"><input name="securecode" type="text" size="6" value="' . esc_attr($securecode) . '" />'.$seccd_attention.'</td>
						</tr>';
					}
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
					for($i=0; $i<10; $i++){
						$year = date('Y') - 1 + $i;
						$html .= '<option value="' . $year . '"' . (($year == $expyy) ? ' selected="selected"' : '') . '>' . $year . '</option>';
					}
					$html .= '
						</select>'.__('year', 'usces').'</td>
						</tr>
						<tr>
						<th scope="row">'.__('Card name', 'usces').'</th>
						<td colspan="2"><input name="username_card" id="username_card" type="text" size="30" value="' . esc_attr($username) . '" />'.__('(Alphabetic characters)', 'usces').'</td>
						</tr>';
				}

				$html_howpay = '';
				if( 'on' == $usces->options['acting_settings'][$paymod_id]['howpay'] ){

				$html_howpay .= '
					<tr>
					<th scope="row">'.__('payment method', 'usces').'</th>
					<td><input name="offer[howpay]" type="radio" value="1" id="howdiv1"' . (('1' === $howpay) ? ' checked' : '') . ' /><label for="howdiv1">'.__('Single payment', 'usces').'</label></td>
					<td><input name="offer[howpay]" type="radio" value="0" id="howdiv2"' . (('0' === $howpay) ? ' checked' : '') . ' /><label for="howdiv2">'.__('Payment in installments', 'usces').'</label></td>
					</tr>
					<tr id="cbrand_zeus">
					<th scope="row">'.__('Card brand', 'usces').'</th>
					<td colspan="2">
					<select name="offer[cbrand]">
						<option value=""' . ((WCUtils::is_blank($cbrand)) ? ' selected="selected"' : '') . '>--------</option>
						<option value="1"' . (('1' === $cbrand) ? ' selected="selected"' : '') . '>JCB</option>
						<option value="1"' . (('1' === $cbrand) ? ' selected="selected"' : '') . '>VISA</option>
						<option value="1"' . (('1' === $cbrand) ? ' selected="selected"' : '') . '>MASTER</option>
						<option value="2"' . (('2' === $cbrand) ? ' selected="selected"' : '') . '>DINERS</option>
						<option value="1"' . (('1' === $cbrand) ? ' selected="selected"' : '') . '>AMEX</option>
					</select>
					</td>
					</tr>
					<tr id="div_zeus">
					<th scope="row">'.__('Number of payments', 'usces').'</th>
					<td colspan="2">
					<select name="offer[div_1]" id="brand1">
						<option value="01"' . (('01' === $cbrand) ? ' selected="selected"' : '') . '>'.__('Single payment', 'usces').'</option>
						<option value="99"' . (('99' === $cbrand) ? ' selected="selected"' : '') . '>'.__('Libor Funding pay', 'usces').'</option>
						<option value="03"' . (('03' === $cbrand) ? ' selected="selected"' : '') . '>3'.__('-time payment', 'usces').'</option>
						<option value="05"' . (('05' === $cbrand) ? ' selected="selected"' : '') . '>5'.__('-time payment', 'usces').'</option>
						<option value="06"' . (('06' === $cbrand) ? ' selected="selected"' : '') . '>6'.__('-time payment', 'usces').'</option>
						<option value="10"' . (('10' === $cbrand) ? ' selected="selected"' : '') . '>10'.__('-time payment', 'usces').'</option>
						<option value="12"' . (('12' === $cbrand) ? ' selected="selected"' : '') . '>12'.__('-time payment', 'usces').'</option>
						<option value="15"' . (('15' === $cbrand) ? ' selected="selected"' : '') . '>15'.__('-time payment', 'usces').'</option>
						<option value="18"' . (('18' === $cbrand) ? ' selected="selected"' : '') . '>18'.__('-time payment', 'usces').'</option>
						<option value="20"' . (('20' === $cbrand) ? ' selected="selected"' : '') . '>20'.__('-time payment', 'usces').'</option>
						<option value="24"' . (('24' === $cbrand) ? ' selected="selected"' : '') . '>24'.__('-time payment', 'usces').'</option>
					</select>
					<select name="offer[div_2]" id="brand2">
						<option value="01"' . (('01' === $cbrand) ? ' selected="selected"' : '') . '>'.__('Single payment', 'usces').'</option>
						<option value="99"' . (('99' === $cbrand) ? ' selected="selected"' : '') . '>'.__('Libor Funding pay', 'usces').'</option>
					</select>
					<select name="offer[div_3]" id="brand3">
						<option value="01"' . (('01' === $cbrand) ? ' selected="selected"' : '') . '>'.__('Single payment only', 'usces').'</option>
					</select>
					</td>
					</tr>
					';
				}
				$html .= apply_filters( 'usces_filter_delivery_secure_form_howpay', $html_howpay );
				$html .= '
				</table>';
				break;

			case 'acting_zeus_conv':
				$paymod_id = 'zeus';

				if( 'on' != $usces->options['acting_settings'][$paymod_id]['conv_activate'] 
					|| 'on' != $usces->options['acting_settings'][$paymod_id]['activate'] )
					continue;

				$pay_cvs = isset( $_POST['pay_cvs'] ) ? esc_html($_POST['pay_cvs']) : '';
				$entry = $usces->cart->get_entry();
				$username = isset( $_POST['username_conv'] ) ? esc_html($_POST['username_conv']) : $entry['customer']['name3'].$entry['customer']['name4'];

				$html .= '
				<table class="customer_form" id="' . $paymod_id . '_conv">
					<tr>
					<th scope="row">'.__('Convenience store for payment', 'usces').'</th>
					<td colspan="2">
					<select name="offer[pay_cvs]" id="pay_cvs_zeus">';
				foreach( (array)$usces->options['acting_settings'][$paymod_id]['pay_cvs'] as $pay_cvs_code ) {
					$selected = ( $pay_cvs_code == $pay_cvs ) ? ' selected="selected"' : '';
					$html .= '
					<option value="'.$pay_cvs_code.'"'.$selected.'>'.usces_get_conv_name( $pay_cvs_code ).'</option>';
				}
				$html .= '
					</select>
					</td>
					</tr>
					<tr>
					<th scope="row"><em>'.__('*', 'usces').'</em>'.__('Full name', 'usces').'</th>
					<td colspan="2"><input name="username_conv" id="username_conv" type="text" size="30" value="'.esc_attr($username).'" />'.__('(full-width Kana)', 'usces').'</td>
					</tr>
				</table>';
				break;

			case 'acting_remise_card':
				$paymod_id = 'remise';
				$have_continue_charge = usces_have_continue_charge();
				$have_regular_order = usces_have_regular_order();

				if( 'on' != $usces->options['acting_settings'][$paymod_id]['card_activate'] 
					|| 'on' != $usces->options['acting_settings'][$paymod_id]['howpay'] 
					|| 'on' != $usces->options['acting_settings'][$paymod_id]['activate']
					|| ( $have_continue_charge || $have_regular_order ) ){
					continue;
				}

				$div = isset( $_POST['div'] ) ? esc_html($_POST['div']) : '0';

				$html .= '
				<table class="customer_form" id="' . $paymod_id . '">
					<tr>
					<th scope="row">'.__('payment method', 'usces').'</th>
					<td colspan="2">
					<select name="offer[div]" id="div_remise">
						<option value="0"' . (('0' === $div) ? ' selected="selected"' : '') . '>'.__('Single payment', 'usces').'</option>
						<option value="1"' . (('1' === $div) ? ' selected="selected"' : '') . '>2'.__('-time payment', 'usces').'</option>
						<option value="2"' . (('2' === $div) ? ' selected="selected"' : '') . '>'.__('Libor Funding pay', 'usces').'</option>
					</select>
					</td>
					</tr>
				</table>';
				break;
		}
		$html .= apply_filters( 'usces_filter_delivery_secure_form_loop', '', $array );
	}
}
$html = apply_filters( 'usces_filter_delivery_secure_form', $html, $array );
$html .= wp_nonce_field( 'wc_delivery_secure_nonce', 'wc_nonce', false, false );
