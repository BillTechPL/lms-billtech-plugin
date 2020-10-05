<?php

class BillTechLinksManager
{
	private $batchSize = 100;
	private $verbose = false;

	public function __construct($verbose = false)
	{
		$this->verbose = $verbose;
	}

	/** @return BillTechLink[]
	 * @var string $customerId
	 */
	public function getCustomerPaymentLinks($customerId)
	{
		global $DB;
		$rows = $DB->GetAll("select * from billtech_payment_links where customer_id = ?", array($customerId));
		if (!is_array($rows)) {
			return array();
		}
		return array_map(function ($row) {
			return BillTechLink::fromRow($row);
		}, $rows);
	}

	public function getCashLink($cashId)
	{
		global $DB;
		$row = $DB->GetRow("select l.* from billtech_payment_links l
								left join cash c on l.src_cash_id = c.id
								where src_cash_id = ?", array($cashId));
		return $row ? BillTechLink::fromRow($row) : null;
	}

	public function getBalanceLink($customerId)
	{
		global $DB;
		$row = $DB->GetRow("select l.* from billtech_payment_links l
								left join cash c on l.src_cash_id = c.id
								where customer_id = ?
								order by c.time desc limit 1", array($customerId));
		if (!$row) {
			return null;
		} else {
			$balanceLink = BillTechLink::fromRow($row);
			$balanceLink->link .= '&type=balance';
			return $balanceLink;
		}
	}

	public function updateForAll()
	{
		global $DB;
		$DB->BeginTrans();
		$actions = array(
			'add' => array(),
			'update' => array(),
			'close' => array(),
		);
		$this->addMissingCustomerInfo();
		$customerIds = $this->getCustomerIdsForUpdate();

		var_dump($customerIds);
		var_dump($DB->GetErrors());
		var_dump($DB->GetRow("select * from billtech_customer_info where customer_id = 8947;"));

		if ($this->verbose) {
			echo "Found " . count($customerIds) . " customers to update\n";
		}

		if (!is_array($customerIds)) {
			return;
		}

		foreach ($customerIds as $idx => $customerId) {
			echo "Collecting actions for customer " . ($idx + 1) . " of " . count($customerIds) . "\n";
			$actions = array_merge_recursive($actions, $this->getCustomerUpdateBalanceActions($customerId));
		}

		$this->updateCustomerInfos($customerIds);

		if ($this->verbose) {
			echo "Adding " . count($actions['add']) . " links\n";
			echo "Updating " . count($actions['update']) . " links\n";
			echo "Cancelling " . count($actions['close']) . " links\n";
		}

		$this->performActions($actions);

		$DB->CommitTrans();
	}

	public function updateCustomerBalance($customerId)
	{
		global $DB;
		$DB->BeginTrans();
		if ($this->checkLastUpdate($customerId)) {
			$actions = $this->getCustomerUpdateBalanceActions($customerId);
			$this->performActions($actions);
		}
		$DB->CommitTrans();
	}

	/**
	 * @param $cashItems array
	 * @return BillTechLink[]
	 */
	public function getLiabilities(array $cashItems)
	{
		$balance = array_reduce($cashItems, function ($carry, $item) {
			return $carry + $item['value'];
		}, 0.0);
		$liabilities = array();

		if (!is_array($cashItems)) {
			return array();
		}

		foreach ($cashItems as $cash) {
			$intCashValue = self::moneyToInt($cash['value']);
			$intBalance = self::moneyToInt($balance);
			if ($intCashValue >= 0) {
				continue;
			}
			if ($intBalance < 0) {
				$amountToPay = self::intToMoney(-max(min($intBalance, 0), $intCashValue));
				array_push($liabilities, BillTechLink::linked($cash, $amountToPay));
			}
			$balance = self::intToMoney($intBalance - $intCashValue);
		}
		return $liabilities;
	}

	/* @throws Exception
	 * @var $links BillTechLink[]
	 */
	private function addPayments($links)
	{
		global $DB;

		$linkDataList = array_map(function ($link) {
			return array(
				'cashId' => $link->srcCashId,
				'amount' => $link->amount
			);
		}, $links);

		$generatedLinks = BillTechLinkApiService::generatePaymentLink($linkDataList);
		$values = array();
		foreach ($generatedLinks as $idx => $generatedLink) {
			$link = $links[$idx];
			array_push($values,
				$link->customerId,
				$link->srcCashId,
				$link->type,
				$generatedLink->link,
				$generatedLink->token,
				$link->amount
			);
		}

		$sql = "insert into billtech_payment_links(customer_id, src_cash_id, type, link, token, amount) values " .
			BillTech::prepareMultiInsertPlaceholders(count($generatedLinks), 6) . ";";
		$DB->Execute($sql, $values);
	}

	/* @throws Exception
	 * @var $link BillTechLink
	 */
	private function updatePaymentAmount(BillTechLink $link)
	{
		global $DB;
		if (self::shouldCancelLink($link)) {
			BillTechLinkApiService::cancelPaymentLink($link->token);
		}
		$linkDataList = array(
			array(
				'cashId' => $link->srcCashId,
				'amount' => $link->amount
			)
		);
		$generatedLink = BillTechLinkApiService::generatePaymentLink($linkDataList)[0];
		$DB->Execute("update billtech_payment_links set amount = ?, link = ?, token = ? where id = ?",
			array($link->amount, $generatedLink->link, $generatedLink->token, $link->id));
	}

	/* @throws Exception
	 * @var $link BillTechLink
	 */
	private function closePayment(BillTechLink $link)
	{
		global $DB;
		if (self::shouldCancelLink($link)) {
			BillTechLinkApiService::cancelPaymentLink($link->token, "PAID");
		}
		$DB->Execute("delete from billtech_payment_links where id = ?", array($link->id));
	}

	private function shouldCancelLink($link)
	{
		global $DB;
		return $DB->GetOne("select count(*) from billtech_payments where token = ?", array($link->token)) == 0;
	}

	public static function moneyToInt($value)
	{
		return intval(round($value * 100));
	}

	public static function intToMoney($value)
	{
		return $value / 100.0;
	}

	/**
	 * @param array $actions
	 * @throws Exception
	 */
	public function performActions($actions)
	{
		$addBatches = array_chunk($actions['add'], $this->batchSize);
		foreach ($addBatches as $idx => $links) {
			echo "Adding batch " . ($idx + 1) . " of " . count($addBatches) . "\n";
			$this->addPayments($links);
		}

		foreach ($actions['update'] as $idx => $link) {
			echo "Updating link " . ($idx + 1) . " of " . count($actions['update']) . "\n";
			$this->updatePaymentAmount($link);
		}

		foreach ($actions['close'] as $idx => $link) {
			echo "Closing link " . ($idx + 1) . " of " . count($actions['close']) . "\n";
			$this->closePayment($link);
		}
	}

	/**
	 * @param $customerId
	 * @return array
	 */
	private function getCustomerUpdateBalanceActions($customerId)
	{
		global $DB;
		$actions = array(
			"add" => array(),
			"update" => array(),
			"close" => array()
		);
		$cashItems = $DB->GetAll("select id, value, customerid from cash where customerid = ? order by time desc, id desc", array($customerId));
		if (!$cashItems) {
			return $actions;
		}

		$liabilities = $this->getLiabilities($cashItems);
		$links = $this->getCustomerPaymentLinks($customerId);
		$paymentMap = BillTech::toMap(function ($payment) {
			/* @var $payment BillTechLink */
			return $payment->srcCashId;
		}, $links);

		foreach ($liabilities as $liability) {
			/* @var $link BillTechLink */
			$link = $paymentMap[$liability->srcCashId];
			if (isset($link) && self::moneyToInt($link->amount) != self::moneyToInt($liability->amount)) {
				$link->amount = $liability->amount;
				array_push($actions['update'], $link);
			} else if (!isset($link) && self::moneyToInt($liability->amount) > 0) {
				array_push($actions['add'], $liability);
			}

			if (isset($link)) {
				unset($paymentMap[$liability->srcCashId]);
			}
		}

		foreach ($paymentMap as $cashId => $link) {
			array_push($actions['close'], $link);
		}

		return $actions;
	}

	private function checkLastUpdate($customerId)
	{
		global $DB;
		$customerInfo = $DB->GetRow("select bci.*, max(c.time) as last_cash_time from billtech_customer_info bci 
										left join cash c on c.customerid = bci.customer_id
										where bci.customer_id = ?
										group by bci.customer_id", array($customerId));

		if ($customerInfo) {
			$DB->Exec("update billtech_customer_info set balance_update_time = ?NOW? where customer_id = ?", array($customerId));
			return $customerInfo['last_cash_time'] > $customerInfo['balance_update_time'];
		} else {
			$DB->Exec("insert into billtech_customer_info (customer_id, balance_update_time) values (?, ?NOW?)", array($customerId));
			return true;
		}
	}

	private function addMissingCustomerInfo()
	{
		global $DB;
		$DB->Exec("insert into billtech_customer_info (customer_id, balance_update_time)
					select cu.id, 0
					from customers cu
							 left join billtech_customer_info bci on bci.customer_id = cu.id
					where bci.customer_id is null;");
	}

	/**
	 * @return array
	 */
	private function getCustomerIdsForUpdate()
	{
		global $DB;
		return $DB->GetCol("select bci.customer_id
										from customers cu
												 left join billtech_customer_info bci on bci.customer_id = cu.id
												 left join cash ca on ca.customerid = cu.id
										group by bci.customer_id, bci.balance_update_time
										having bci.balance_update_time <= coalesce(max(ca.time), 0);");
	}

	/**
	 * @param array $customerIds
	 */
	private function updateCustomerInfos(array $customerIds)
	{
		global $DB;
		$DB->Exec("update billtech_customer_info set balance_update_time = ?NOW? where customer_id in (" . BillTech::repeatWithSeparator("?", ",", count($customerIds)) . ")", $customerIds);
	}
}

