<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechLinkGenerator
{
	public static function create_payment_link($doc, $customer_id, $isp_id)
	{
		global $LMS;

		$userinfo = $LMS->GetCustomer($customer_id);

		if ($doc == 'balance') {
			$balance = $LMS->GetCustomerBalanceList($customer_id);
			$amount = -$balance['balance'];
			$paymentDue = new DateTime('@' . time());
			$invoiceNumber = 'saldo-' . $paymentDue->format('Ymd');
		} else {
			$doc_content = $LMS->GetInvoiceContent($doc);
			$paymentDue = new DateTime('@' . ($doc_content['pdate'] == '' ? time() : $doc_content['pdate']));
			$invoiceNumber = docnumber($doc);
			$amount = $doc_content['value'];
		}

		$nrb = bankaccount($userinfo['id'], null);

		$providerCode = $isp_id;
		$externalId = $userinfo['id'];
		$clientName = $userinfo['name'];
		$clientSurname = $userinfo['lastname'];
		$email = '';
		if($userinfo['emails']){
			$emails = $userinfo['emails'];
			$emails = array_reverse($emails);
			$email = array_pop($emails)['email'];
		}
		$account = $nrb;
		$paymentDue = $paymentDue->format('Ymd');

		$data = $providerCode .
			$externalId .
			$invoiceNumber .
			$clientName .
			$clientSurname .
			$email .
			$amount .
			$account .
			$paymentDue;

		error_log($data);

		$privateKey = ConfigHelper::getConfig('billtech.private_key');
		$signature = '';
		openssl_sign($data, $signature, $privateKey, 'SHA256');
		$signature = urlencode(base64_encode($signature));

		return ConfigHelper::getConfig('billtech.payment_url') .
			'?providerCode=' . $providerCode .
			'&externalId=' . $externalId .
			'&clientName=' . $clientName .
			'&clientSurname=' . $clientSurname .
			'&email=' . $email .
			'&account=' . $account .
			'&invoiceNumber=' . $invoiceNumber .
			'&amount=' . $amount .
			'&paymentDue=' . $paymentDue .
			'&signature=' . $signature;
	}
}