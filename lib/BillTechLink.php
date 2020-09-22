<?php

class BillTechLink
{
	public $id;
	public $customerId;
	public $srcCashId;
	public $type;
	public $link;
	public $token;
	public $amount;

	public function __construct($customerId, $cashId, $amount)
	{
		$this->customerId = $customerId;
		$this->srcCashId = $cashId;
		$this->amount = $amount;
	}

	/**
	 * @return BillTechLink
	 * @var $amount float
	 * @var $cash array
	 */
	public static function linked($cash, $amount)
	{
		$instance = new self($cash['customerid'], $cash['id'], $amount);
		$instance->type = 'linked';
		return $instance;
	}

	/**
	 * @return BillTechLink
	 * @var $amount float
	 * @var $cash array
	 */
	public static function notLinked($cash, $amount)
	{
		$instance = new self($cash['customerid'], $cash['id'], $amount);
		$instance->type = 'not_linked';
		return $instance;
	}

	public static function fromRow(array $row)
	{
		$instance = new self(null, null, null);
		$instance->id = $row['id'];
		$instance->customerId = $row['customer_id'];
		$instance->srcCashId = $row['src_cash_id'];
		$instance->docid = $row['docid'];
		$instance->type = $row['type'];
		$instance->link = $row['link'];
		$instance->token = $row['token'];
		$instance->amount = floatval($row['amount']);
		return $instance;
	}
}