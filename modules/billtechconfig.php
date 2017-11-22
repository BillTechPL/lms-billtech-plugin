<?php

$isp_id = ConfigHelper::getConfig('billtech.isp_id');
$payment_url = ConfigHelper::getConfig('billtech.payment_url');
$api_url = ConfigHelper::getConfig('billtech.api_url');
$api_key = ConfigHelper::getConfig('billtech.api_key');
$api_secret = ConfigHelper::getConfig('billtech.api_secret');

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

switch ($action) {
	case 'generatesecret':
		$isp_id = $_POST['isp_id'];
		$api_url = $_POST['api_url'];
		$api_key = $_POST['api_key'];
		$pin = $_POST['pin'];

		if ($pin) {
			$data = json_encode(array(
				'providerCode' => $isp_id,
				'apiKey' => $api_key,
				'pin' => $pin
			));

			$curl = curl_init($api_url . '/api/service-provider/generate-key');
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			$result = curl_exec($curl);

			$api_secret = json_decode($result)->data->apiSecret;

			if ($api_secret) {
				$DB->Execute("INSERT INTO uiconfig(section, var, value)
								VALUES ('billtech', 'api_secret', ?)
									ON DUPLICATE KEY UPDATE value = VALUES(value)",
					array($api_secret));
			}
		}

	case 'setbilltechconfig':
		$isp_id = $_POST['isp_id'];
		$payment_url = $_POST['payment_url'];
		$api_url = $_POST['api_url'];
		$api_key = $_POST['api_key'];
		$DB->Execute("INSERT INTO uiconfig(section, var, value) 
								VALUES('billtech', 'isp_id', ?),
									('billtech', 'payment_url', ?),
									('billtech', 'api_url', ?),
									('billtech', 'api_key', ?)
									ON DUPLICATE KEY UPDATE value = VALUES(value)",
			array($isp_id, $payment_url, $api_url, $api_key));
}

$SMARTY->assign('isp_id', $isp_id);
$SMARTY->assign('payment_url', $payment_url);
$SMARTY->assign('api_url', $api_url);
$SMARTY->assign('api_key', $api_key);
$SMARTY->assign('api_secret', $api_secret);
$SMARTY->display('billtechconfig.html');
