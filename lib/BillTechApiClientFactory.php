<?php

use GuzzleHttp\Client;

class BillTechApiClientFactory {
	public static function getClient() {
		return new Client([
			'base_uri' => BillTech::getConfig('billtech.api_url'),
			'auth' => [
				BillTech::getConfig('billtech.api_key'),
				BillTech::getConfig('billtech.api_secret')
			]
		]);
	}
}