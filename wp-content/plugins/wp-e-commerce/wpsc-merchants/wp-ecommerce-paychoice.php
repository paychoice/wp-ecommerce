<?php
/**
 * WP-E-Commerce Payment Module for the Paychoice
 * 
 * Please note, this module REQUIRES the following:
 *   - PHP 5+
 *   - CURL (http://au.php.net/curl)
 *   - SimpleXML (http://au.php.net/simplexml)
 * 
 * @see http://www.paychoice.com.au
 * @author Paychoice Pty Ltd (http://support.paychoice.com.au)
 * @copyright 2010 PayChoice Pty Ltd
 */

$nzshpcrt_gateways[$num]['name'] = 'PayChoice';
$nzshpcrt_gateways[$num]['internalname'] = 'paychoice';
$nzshpcrt_gateways[$num]['function'] = 'gateway_paychoice';
$nzshpcrt_gateways[$num]['form'] = "form_paychoice";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_paychoice";

if(in_array('paychoice',(array)get_option('custom_gateway_options'))) 
{
	$curryear = date('Y');
	
	//generate year options
	for($i=0; $i < 10; $i++){
		$years .= "<option value='".$curryear."'>".$curryear."</option>\r\n";
		$curryear++;
	}
 
	$gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = "
		<tr>
			<td>Card Name: *</td>
			<td>
				<input type='text' class='text intra-field-label' value='' name='paymentCardName' />
			</td>
		</tr>
		<tr>
			<td>Card Number: *</td>
			<td>
				<input type='text' class='text intra-field-label' value='' name='paymentCardNumber' />
			</td>
		</tr>
		<tr>
			<td>Type: *</td>
			<td>
				<select class='wpsc_ccBox' name='paymentCardType'>
					<option value='Visa'>Visa</option>
					<option value='MasterCard'>MasterCard</option>
					<option value='DinersClub'>Diners</option>
					<option value='AmericanExpress'>American Express</option>
				</select> 
			</td>
		</tr>
		<tr>
			<td>Expiry: *</td>
			<td>
				<select class='wpsc_ccBox' name='expiryMonth'>
					<option value='01'>01</option>
					<option value='02'>02</option>
					<option value='03'>03</option>
					<option value='04'>04</option>
					<option value='05'>05</option>						
					<option value='06'>06</option>						
					<option value='07'>07</option>					
					<option value='08'>08</option>						
					<option value='09'>09</option>						
					<option value='10'>10</option>						
					<option value='11'>11</option>																			
					<option value='12'>12</option>																			
				</select> 
				/
				<select class='wpsc_ccBox' name='expiryYear'>
					".$years."
				</select>
			</td>
		</tr>
		<tr>
			<td>CVV: *</td>
			<td>
				<input type='text' class='text intra-field-label' size='4' value='' maxlength='4' name='paymentCardCSC' />
			</td>
		</tr>";
}

if(!function_exists('gateway_paychoice')) 
{
	function gateway_paychoice($seperator, $sessionid) 
	{
		global $wpdb, $wpsc_cart;
		
		$debug = false;
			
		$amount = number_format($wpsc_cart->total_price,2, '.', '');
		$currency = "AUD";
				
		$cardNumber = str_replace(array('-',' '), '', $_POST['paymentCardNumber']);
		$cardType = $_POST['paymentCardType'];
		$cardCSC = str_replace(' ', '', $_POST['paymentCardCSC']);
		$expiryMonth = $_POST['expiryMonth'];
		$expiryYear = substr($_POST['expiryYear'], 2);		
		
		if (isset($_POST['paymentCardName']) && strlen($_POST['paymentCardName']))
		{
			$cardName = $_POST['paymentCardName'];
		}
		
		$environment = get_option('paychoice_useSandbox', '1') == '1' ? 'sandbox' : 'secure';
		$endPoint = "https://{$environment}.paychoice.com.au/services/v2/rest/PaymentService.svc/ProcessPayment/CreditCard";
		$username = get_option('paychoice_user'); 
		$password = get_option('paychoice_password'); 

		$requestXml = "
<CreditCardPayment>
	<Amount>{$amount}</Amount>
	<CurrencyCode>{$currency}</CurrencyCode>
	<MerchantReferenceNumber>{$sessionid}</MerchantReferenceNumber>
	<CreditCard>
		<CardName>{$cardName}</CardName>
		<CardNumber>{$cardNumber}</CardNumber>
		<CreditCardType>{$cardType}</CreditCardType>
		<Cvv>{$cardCSC}</Cvv>
		<ExpiryMonth>{$expiryMonth}</ExpiryMonth>
		<ExpiryYear>{$expiryYear}</ExpiryYear>
	</CreditCard>
</CreditCardPayment>";
		
		// Initialise CURL and set base options
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/xml; charset=utf-8'));
		
		// Setup CURL params for this request
		curl_setopt($curl, CURLOPT_URL, $endPoint);
		curl_setopt($curl, CURLOPT_USERPWD, $username.':'.$password);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $requestXml);
		
		// Run CURL
		$response = curl_exec($curl);
		$error = curl_error($curl);
		$responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		if (isset($debug) && $debug === true)
		{
			echo "<pre>{$responseCode} - {$error}<br />{$response}<pre><hr />";
		}
		
		$approvedStatus = false;
				
		// Check for CURL errors
		if (isset($error) && strlen($error))
		{
			$errorMessage = "Transaction Error: Could not successfully communicate with Payment Processor. ({$error}).";
		}
		else if (isset($responseCode) && strlen($responseCode) && $responseCode != '200')
		{
			$errorMessage = "Transaction Error: Could not successfully communicate with Payment Processor. (Response code {$responseCode}).";
		}
	
		// Make sure the API returned something
		if (!isset($response) || strlen($response) < 1)
		{
			$errorMessage = "Transaction Error: Payment Processor did not return a valid response.";
		}
		else
		{		
			// Parse the XML
			$xml = simplexml_load_string($response);
			// Convert the result from a SimpleXMLObject into an array
			$xml = (array)$xml;			
			
			// Check for a valid response code			
			if (!isset($xml['StatusCode']) || strlen($xml['StatusCode']) < 1)
			{
				$errorMessage = "Transaction Error: Payment Processor did not return a valid response code.";
			}
			else
			{			
				// Validate the response - the only successful code is 0
				$approvedStatus = ((int)$xml['StatusCode'] === 0) ? true : false;
					
				// Set an error message if the transaction failed
				if ($approvedStatus === false)
				{
					$errorMessage = "Transaction Declined: {$xml['ErrorDescription']}.";
				}
			}
		}
		
		// Set an error and redirect if something went wrong
		if ($approvedStatus === false)
		{
			$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '5' WHERE `sessionid`=".$sessionid;
			$wpdb->query($sql);
			$transact_url = get_option('checkout_url');
			$_SESSION['WpscGatewayErrorMessage'] = "Sorry your transaction did not go through successfully, please try again.<br /><br />{$errorMessage}";
						
			header("Location: ".$transact_url);
			exit();
		}
		
		// Successful transaction!
		$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '2' WHERE `sessionid`=".$sessionid;
 		$wpdb->query($sql);
 		$transact_url = get_option('transact_url');
 		unset($_SESSION['WpscGatewayErrorMessage']);
 		
 		header("Location: ".$transact_url.$seperator."sessionid=".$sessionid);
 		exit();
	}
		
	/**
	 * WP ECommerce Admin Form submission
	 *
	 * @return boolean true when gateway updates; otherwise an error
	 */		
	function submit_paychoice()
	{
		if($_POST['paychoice_user'] != null)
		{
			update_option('paychoice_user', $_POST['paychoice_user']);
		}
		if($_POST['paychoice_password'] != null)
		{
			update_option('paychoice_password', $_POST['paychoice_password']);
		}
		if($_POST['paychoice_useSandbox'] != null)
		{
			update_option('paychoice_useSandbox', $_POST['paychoice_useSandbox']);
		}
		
		foreach((array)$_POST['paychoice_form'] as $form => $value)
		{
			update_option(('paychoice_form_'.$form), $value);
		}
		
		return true;
	}
	
	/**
	 * WP ECommerce Admin Form
	 *
	 * @return string HTML Form
	 */
	function form_paychoice()
	{
		$paychoice_useSandbox = get_option('paychoice_useSandbox', '1');
		if ($paychoice_useSandbox == '1') {
			$paychoice_sandbox = "checked='checked'";
		} else {
			$paychoice_live = "checked='checked'";
		}
		
		$output = "
			<tr>
				<td>
					User Name
				</td>
				<td>
					<input type='text' size='40' value='".get_option('paychoice_user')."' name='paychoice_user' />
				</td>
			</tr>
			<tr>
				<td>
					Password
				</td>
				<td>
					<input type='text' size='20' value='".get_option('paychoice_password')."' name='paychoice_password' />
				</td>
			</tr>
			<tr>
				<td>
					Test Mode
				</td>
				<td>
					<input type='radio' value='1' name='paychoice_useSandbox' id='paychoice_sandbox' ".$paychoice_sandbox." /> <label for='paychoice_sandbox'>".TXT_WPSC_YES."</label> &nbsp;
					<input type='radio' value='0' name='paychoice_useSandbox' id='paychoice_live' ".$paychoice_live." /> <label for='paychoice_live'>".TXT_WPSC_NO."</label>
				</td>
			</tr>";
					
		return $output;
	}

}