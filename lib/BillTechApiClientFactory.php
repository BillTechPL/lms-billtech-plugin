<?php
use GuzzleHttp\Client;

class BillTechApiClientFactory {
	public static function getClient() {
		return new Client([
			'base_uri' => ConfigHelper::getConfig('billtech.api_url'),
			'auth' => [
				ConfigHelper::getConfig('billtech.api_key'),
				ConfigHelper::getConfig('billtech.api_secret')
			]
		]);
	}
}