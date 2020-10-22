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
		$response = $this->client->post('/encode', [
			'query' => $params,
			'json' => [
				'url' => $url
			]]);
		return "" . $response->getBody();
	}
}