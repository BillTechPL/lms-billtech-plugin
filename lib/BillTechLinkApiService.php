<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */


use GuzzleHttp\Exception\ClientException;

class BillTechLinkApiService
{
	const BASE_PATH = '/pay/v1/payments';

	/**
	 * @param $linkDataList
	 * @param array $config
	 * @return GeneratedBilltechLink[]
	 * @throws Exception
	 */
	public static function generatePaymentLinks($linkDataList)
	{
		$isp_id = ConfigHelper::getConfig('billtech.isp_id');
		$client = BillTechApiClientFactory::getClient();

		$cashInfos = array();
		$paymentLinkRequests = array();

		foreach ($linkDataList as $linkData) {
			array_push($cashInfos, $cashInfo = self::getCashInfo($linkData['cashId']));
			array_push($paymentLinkRequests, self::createPaymentLinkRequest($cashInfo, $linkData['amount']));
		}

		try {
			$response = $client->post(self::BASE_PATH, [
				'json' => [
					'providerCode' => ConfigHelper::getConfig('billtech.isp_id'),
					'payments' => $paymentLinkRequests
				]
			]);
		} catch (ClientException $e) {
			$response = $e->GetResponse();
			if ($response) {
				self::handleBadResponse($response, self::BASE_PATH);
			} else {
				throw $e;
			}
		}

		if ($response->getStatusCode() != 201) {
			self::handleBadResponse($response, self::BASE_PATH);
		}

		$json = json_decode($response->getBody());

		$result = array();

		foreach ($json as $idx => $link) {
			$cashInfo = $cashInfos[$idx];
			$link->link = $link->link .
				'?email=' . urlencode($cashInfo['email']) .
				'&name=' . urlencode($cashInfo['name']) .
				'&surname=' . urlencode($cashInfo['lastname']) .
				'&utm_content=' . urlencode($isp_id) .
				'&utm_source=isp';
			array_push($result, $link);
		}


		return $result;
	}

	/** @throws Exception
	 * @var string $token
	 * @var string $resolution
	 */
	public static function cancelPaymentLink($token, $resolution = "CANCELLED")
	{
		$client = BillTechApiClientFactory::getClient();
		$path = self::BASE_PATH . '/' . $token . '/cancel';
		$response = $client->post($path, [
			"json" => [
				"resolution" => $resolution
			]
		]);

		if ($response->getStatusCode() != 204) {
			throw new Exception($path . " returned code " . $response->getStatusCode() . "\n" . $response->getBody());
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
	public static function handleBadResponse($response, $path)
	{
		$message = $path . " returned code " . $response->getStatusCode() . "\n" . $response->getBody();
		echo $message;
		throw new Exception($message);
	}

	/**
	 * @param $cashInfo
	 * @param $amount
	 * @return array
	 * @throws Exception
	 */
	private static function createPaymentLinkRequest($cashInfo, $amount)
	{
		$paymentDue = (new DateTime('@' . time()))->format('Y-m-d');
		$title = $cashInfo['comment'];

		if ($cashInfo['pdate']) {
			$paymentDue = (new DateTime('@' . $cashInfo['pdate']))->format('Y-m-d');
		}

		$request = array(
			'userId' => $cashInfo['customerid'],
			'amount' => isset($amount) ? $amount : -$cashInfo['value'],
			'nrb' => bankaccount($cashInfo['customerid'], null),
			'paymentDue' => $paymentDue,
			'title' => self::getTitle($title)
		);

		if (ConfigHelper::checkConfig("billtech.produce_short_links")) {
			$request = array_merge_recursive($request, array(
				'name' => self::getNameOrSurname($cashInfo['name']),
				'surname' => self::getNameOrSurname($cashInfo['lastname']),
				'email' => $cashInfo['email'],
			));
		}

		return $request;
	}

	/**
	 * @param string $title
	 * @return string
	 */
	private static function getTitle($title)
	{
		return substr(preg_replace("/[^ A-Za-z0-9#&_\-',.\\/\x{00c0}-\x{02c0}]/u", " ", $title), 0, 105);
	}

	/**
	 * @param string $value
	 * @return string
	 */
	private static function getNameOrSurname($value)
	{
		return isset($value) ? substr(preg_replace("/[^ A-Za-z0-9-,.\x{00c0}-\x{02c0}]/u", " ", $value), 0, 100) : null;
	}
}

class GeneratedBilltechLink
{
	public $token;
	public $link;
	public $shortLink;
}