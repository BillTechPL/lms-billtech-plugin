<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechLinkGenerator
{
	public static function createPaymentLink($doc, $customer_id)
	{
		global $LMS;
		$isp_id = ConfigHelper::getConfig('billtech.isp_id');
		$userinfo = $LMS->GetCustomer($customer_id);

		if ($doc == 'balance') {
			$balance = $LMS->GetCustomerBalanceList($customer_id);
			$amount = -$balance['balance'];
			$paymentDue = new DateTime('@' . time());
			$invoiceNumber = 'saldo-' . $paymentDue->format('Ymd');
		} else {
			$doc_content = $LMS->GetInvoiceContent($doc);
			$paymentDue = new DateTime('@' . ($doc_content['pdate'] == '' ? time() : $doc_content['pdate']));
			$invoiceNumber = docnumber($doc_content['number']);
			$amount = $doc_content['value'];
		}

		$amount = str_replace(',', '.', $amount);

		$nrb = bankaccount($customer_id, null);

		$providerCode = $isp_id;
		$externalId = $userinfo['id'];
		$clientName = $userinfo['name'];
		$clientSurname = $userinfo['lastname'];
		$email = '';
		if ($userinfo['emails']) {
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
		if (!is_null($privateKey)) {
			if ($privateKey[0] == DIRECTORY_SEPARATOR && is_readable($privateKey)) {
				$privateKey = file_get_contents($privateKey);
			} else {
				$path = ConfigHelper::getConfig('directories.sys_dir') . DIRECTORY_SEPARATOR . $privateKey;
				if (is_readable($path)) {
					$privateKey = file_get_contents($path);
				}
			}
		}
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
			'&signature=' . $signature .
			'&utm_content=' . $providerCode .
			'&utm_cource=isp';
	}
}