<?php
use GuzzleHttp\Client;

class BillTechApiClientFactory {
	public static function getClient() {
		$api_url = ConfigHelper::getConfig('billtech.api_url');
		return new Client([
			'base_uri' =>
				self::endsWith($api_url, '/') ?
					substr_replace($api_url, '', -1, 1) : $api_url,
			'auth' => [
				ConfigHelper::getConfig('billtech.api_key'),
				ConfigHelper::getConfig('billtech.api_secret')
			]
		]);
	}

	private static function endsWith($haystack, $needle) {
		$length = strlen($needle);
		if(!$length) {
			return false;
		}
		return substr($haystack, -$length) === $needle;
	}
}