<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */


use GuzzleHttp\Exception\ClientException;

class BillTechLinkApiService
{
	/**
	 * @param $cashId
	 * @param float $amount
	 * @return GeneratedBilltechLink
	 * @throws Exception
	 */
	public static function generatePaymentLink($cashId, $amount = null)
	{
		global $LMS;
		$isp_id = ConfigHelper::getConfig('billtech.isp_id');
		$client = BillTechApiClient::getClient();

		$cashInfo = self::getCashInfo($cashId);

		if (!$cashInfo) {
			throw new Exception("Could not load customer " . $cashInfo['customerid']);
		}

		$paymentDue = (new DateTime('@' . time()))->format('Y-m-d');
		$title = $cashInfo['comment'];

		if ($cashInfo['pdate']) {
			$paymentDue = (new DateTime('@' . $cashInfo['pdate']))->format('Y-m-d');
		}

		try {
			$response = $client->post('/api/payments', [
				'json' => [
					'providerCode' => ConfigHelper::getConfig('billtech.isp_id'),
					'payments' => [
						[
							'userId' => $cashInfo['customerid'],
							'amount' => isset($amount) ? $amount : -$cashInfo['value'],
							'nrb' => bankaccount($cashInfo['customerid'], null),
							'paymentDue' => $paymentDue,
							'title' => $title
						]
					]
				]
			]);
		} catch (ClientException $e) {
			$response = $e->GetResponse();
			if ($response) {
				self::handleBadResponse($response);
			} else {
				throw $e;
			}
		}

		if ($response->getStatusCode() != 201) {
			self::handleBadResponse($response);
		}

		$json = json_decode($response->getBody());
		$result = $json[0];
		$baseLink = $result->link;
		$result->link = $baseLink .
			'?email=' . $cashInfo['email'] .
			'&name=' . $cashInfo['name'] .
			'&surname=' . $cashInfo['lastname'] .
			'&utm_content=' . urlencode($isp_id) .
			'&utm_source=isp';

		return $result;
	}

	/** @throws Exception
	 * @var string $token
	 * @var string $resolution
	 */
	public static function cancelPaymentLink($token, $resolution = "CANCELLED")
	{
		$client = BillTechApiClient::getClient();
		$response = $client->post('/api/payments/' . $token . '/cancel', [
			"json" => [
				"resolution" => $resolution
			]
		]);

		if ($response->getStatusCode() != 202) {
			throw new Exception("/api/payments/" . $token . "/cancel returned code " . $response->getStatusCode() . "\n" . $response->getBody());
		}
	}

	/**
	 * @param $cashId
	 * @return array|bool
	 */
	public static function getCashInfo($cashId)
	{
		global $DB;
		$cashInfo = $DB->GetRow("select ca.customerid, ca.value, ca.comment, ca.docid, cu.lastname, cu.name, d.cdate, d.paytime, cc.contact as email from cash ca
										left join customers cu on ca.customerid = cu.id 
										left join documents d on d.id = ca.docid
										left join customercontacts cc on cu.id = cc.customerid and cc.type = ?
										where ca.id = ?", array(CONTACT_EMAIL, $cashId));
		if (!$cashInfo) {
			throw new Exception("Could not fetch cash info cashid: " . $cashId);
		}
		$cashInfo['pdate'] = $cashInfo['cdate'] + ($cashInfo['paytime'] * 86400);
		return $cashInfo;
	}

	/**
	 * @param $response
	 * @throws Exception
	 */
	public static function handleBadResponse($response)
	{
		$message = "/api/payments returned code " . $response->getStatusCode() . "\n" . $response->getBody();
		echo $message;
		throw new Exception($message);
	}
}

class GeneratedBilltechLink
{
	public $link;
	public $token;
}