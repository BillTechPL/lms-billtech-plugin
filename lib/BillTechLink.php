<?php

class BillTechLink
{
	public $id;
	public $customerId;
	public $srcCashId;
	public $srcDocumentId;
	public $type;
	public $link;
	public $shortLink;
	public $token;
	public $amount;
	public $docid;

	public function __construct($customerId, $cashId, $documentId, $amount)
	{
		$this->customerId = $customerId;
		$this->srcCashId = $cashId;
		$this->srcDocumentId = $documentId;
		$this->amount = $amount;
	}

	public function getKey()
	{
		return isset($this->srcDocumentId) ? $this->srcDocumentId : 'cash_' . $this->srcCashId;
	}

	/**
	 * @param $customerId
	 * @param $cashId
	 * @param $documentId
	 * @param $amount
	 * @return BillTechLink
	 */
	public static function linked($customerId, $cashId, $documentId, $amount)
	{
		$instance = new self($customerId, $cashId, $documentId, $amount);
		$instance->type = 'linked';
		return $instance;
	}

	public static function fromRow(array $row)
	{
		$instance = new self(null, null, null, null);
		$instance->id = $row['id'];
		$instance->customerId = $row['customer_id'];
		$instance->srcCashId = $row['src_cash_id'];
		$instance->srcDocumentId = $row['src_document_id'];
		$instance->type = $row['type'];
		$instance->link = $row['link'];
		$instance->shortLink = $row['short_link'];
		$instance->token = $row['token'];
		$instance->amount = floatval($row['amount']);
		return $instance;
	}
}