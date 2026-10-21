<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class LinkShortenerApiService
{
	private $client;

	public function __construct()
	{
		$this->client = new Client([
			'base_uri' => 'https://zapl.ac',
			'connect_timeout' => 15,
			'timeout' => 60,
		]);
	}

	public function addParameters($url, $params = array())
	{
		$retryLimit = 3;

		for ($attempt = 1; $attempt <= $retryLimit; $attempt++) {
			try {
				return $this->postEncodeUrl($url, $params);
			} catch (Exception $e) {
				if ($attempt < $retryLimit) {
					sleep(1);
				} else {
					echo "Unable to add parameters to the link: " . $e->getMessage() . "\n";
				}
			}
		}

		return "";
	}

	private function postEncodeUrl($url, $params)
	{
		$response = $this->client->post('/encode', [
			'query' => $params,
			'json' => [
				'url' => $url
			]]);
        return $response->getBody()->getContents();
	}
}