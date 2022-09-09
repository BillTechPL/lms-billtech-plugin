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
		]);
	}

	public function addParameters($url, $params = array())
	{
		$retryLimit = 3;
		$retryCount = 1;

		while($retryCount <= $retryLimit) {
			try {
				return $this->postEncodeUrl($url, $params);
			} catch (Exception $e) {
				$retryCount++;
				sleep(1);
				if($retryCount === $retryLimit) {
					$error = $e->getResponse();
					echo "Unable to add parameters to the link. Server response: ".$error;
				}
				continue;
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
		return "" . $response->getBody();
	}
}