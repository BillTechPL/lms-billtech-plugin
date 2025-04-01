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
	 * @param BillTechLink[] $linkRequests
	 * @return GeneratedBilltechLink[]
	 * @throws Exception
	 */
	public static function generatePaymentLinks($linkRequests)
	{
		$isp_id = ConfigHelper::getConfig('billtech.isp_id');
		$client = BillTechApiClientFactory::getClient();

		$linkDataList = array();
		$apiRequests = array();

		foreach ($linkRequests as $linkRequest) {
			array_push($linkDataList, $linkData = self::getLinkData($linkRequest));
			array_push($apiRequests, self::createApiRequest($linkData));
		}

		try {
			$response = $client->post(self::BASE_PATH, [
				'json' => [
					'providerCode' => ConfigHelper::getConfig('billtech.isp_id'),
					'payments' => $apiRequests
				],
				'http_errors' => FALSE
			]);
		} catch (ClientException $e) {
			$response = $e->getResponse();
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
			$linkData = $linkDataList[$idx];
			$link->link = $link->link .
				'?email=' . urlencode($linkData['email']) .
				'&name=' . urlencode(self::getNameOrSurname($linkData['name'])) .
				'&surname=' . urlencode(self::getNameOrSurname($linkData['lastname'])) .
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
			],
			"http_errors" => FALSE
		]);

		if (!in_array($response->getStatusCode(), [204, 404, 409])) {
			throw new Exception($path . " returned code " . $response->getStatusCode() . "\n" . $response->getBody());
		}
	}

	/**
	 * @param BillTechLink $linkRequest
	 * @return array|bool
	 */
	public static function getLinkData($linkRequest)
	{
		global $DB;

		if ($linkRequest->srcDocumentId) {
			$linkData = $DB->GetRow("select" . ($DB->GetDbType() == "postgres" ? " distinct on (c.id)" : "") .
				" d.customerid, d.number, d.fullnumber, d.comment, COALESCE(NULLIF(d.div_account,''), di.account) as div_account, d.id, d.name as fullname, c.lastname,
				 c.name, d.cdate, d.paytime, di.id as division_id, di.shortname as division_name, cc.contact as email 
										from documents d
										left join customers c on d.customerid = c.id
										left join customercontacts cc on cc.customerid = c.id and (cc.type & 8) > 1
										left join divisions di on c.divisionid = di.id where d.id = ?" . ($DB->GetDbType() == "mysql" ? " group by c.id" : ""), [$linkRequest->srcDocumentId]);
			if (!$linkData) {
				throw new Exception("Could not fetch link data by document id: " . $linkRequest->srcDocumentId);
			}
			$linkData['title'] = self::getDocumentTitle($linkData['fullnumber'], $linkData['comment'], $linkData['fullname']);
		} else {
			$linkData = $DB->GetRow("select" . ($DB->GetDbType() == "postgres" ? " distinct on (cu.id)" : "") .
				" ca.customerid, ca.docid, cu.lastname, cu.name, d.cdate, d.paytime, ca.comment as title, di.id as division_id, di.shortname as division_name, cc.contact as email, di.account as div_account from cash ca
										left join customers cu on ca.customerid = cu.id 
										left join customercontacts cc on cc.customerid = cu.id and (cc.type & 8) > 1
										left join documents d on d.id = ca.docid
										left join divisions di on cu.divisionid = di.id 
										where ca.id = ?" . ($DB->GetDbType() == "mysql" ? " group by cu.id" : ""), [$linkRequest->srcCashId]);
			if (!$linkData) {
				throw new Exception("Could not fetch link data by cash id: " . $linkRequest->srcCashId);
			}
		}

		$linkData['key'] = $linkRequest->getKey();
		$linkData['amount'] = $linkRequest->amount;
		$linkData['pdate'] = $linkData['cdate'] + ($linkData['paytime'] * 86400);
		return $linkData;
	}

	private static function getDocumentTitle($fullnumber, $comment, $name)
	{
		if ($fullnumber != '' || $comment != '') {
			return $comment != '' ? $fullnumber . ' ' . $comment : $fullnumber;
		} else {
			return 'Faktura ' . $name;
		}
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
	 * @param $customerId
	 * @param $divisionBankAccount
	 * @return string
	 */
	private static function getBankAccount($customerId, $divisionBankAccount)
	{
		global $DB;

		$alternativeBankAccounts = $DB->GetAll(
			'SELECT contact FROM customercontacts WHERE customerid = ? AND (type & ?) = ?',
			array(
				$customerId,
				(CONTACT_BANKACCOUNT | CONTACT_INVOICES | CONTACT_DISABLED),
				(CONTACT_BANKACCOUNT | CONTACT_INVOICES)
			)
		);

		if (!empty($alternativeBankAccounts)) {
			return iban_account('PL', 26, $customerId, $alternativeBankAccounts[0]['contact']);
		}

		return iban_account('PL', 26, $customerId, $divisionBankAccount);
	}

	/**
	 * @param $linkData
	 * @return array
	 * @throws Exception
	 */
	private static function createApiRequest($linkData)
	{
		$request = array(
			'userId' => $linkData['customerid'],
			'operationId' => $linkData['key'],
			'amount' => $linkData['amount'],
			'nrb' => ConfigHelper::getConfig('billtech.bankaccount', self::getBankAccount($linkData['customerid'], $linkData['div_account'])),
			'paymentDue' => (new DateTime('@' . ($linkData['pdate'] ?: time())))->format('Y-m-d'),
			'title' => self::getTitle($linkData['title'])
		);

		if (ConfigHelper::checkConfig("billtech.append_customer_info")) {
			$request = array_merge_recursive($request, array(
				'name' => self::getNameOrSurname($linkData['name']),
				'surname' => self::getNameOrSurname($linkData['lastname']),
				'email' => trim($linkData['email']) ?: null,
			));
		}

		if (ConfigHelper::checkConfig("billtech.branding_enabled")) {
			$request = array_merge_recursive($request, array(
				'recipient' => array(
					'id' => $linkData['division_id'],
					'name' => self::getRecipientName($linkData['division_name'])
				)
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
		return substr(preg_replace("/[^ A-Za-z0-9#&_\-',.\\/\x{00c0}-\x{02c0}]/u", " ", $title), 0, 105) ?: "";
	}

	private static function getNameOrSurname($nameOrSurname)
	{
		return substr(preg_replace("/[^ A-Za-z0-9\-,.\x{00c0}-\x{02c0}]/u", " ", $nameOrSurname), 0, 100) ?: null;
	}

	private static function getRecipientName($divisionName)
	{
		return substr(preg_replace("/[^ A-Za-z0-9\-,.\x{00c0}-\x{02c0}]+/u", " ", $divisionName), 0, 35);
	}
}

class GeneratedBilltechLink
{
	public $token;
	public $link;
	public $shortLink;
}
