<?php

class BillTechLinksManager
{
	private $batchSize = 100;
	private $verbose = false;
	private $linkShortener;

	public function __construct($verbose = false)
	{
		$this->verbose = $verbose;
		$this->linkShortener = new LinkShortenerApiService();
	}

	/** @return BillTechLink[]
	 * @var string $customerId
	 */
	public function getCustomerPaymentLinks($customerId)
	{
		global $DB;
		$rows = $DB->GetAll("select bpl.*, c.docid
                                from billtech_payment_links bpl
                                         left join cash c on c.id = bpl.src_cash_id
                                         left join billtech_payments bp on bpl.token = bp.token
                                where bp.id is null
                                  and customer_id = ?", array($customerId));
		if (!is_array($rows)) {
			return array();
		}
		return array_map(function ($row) {
			return BillTechLink::fromRow($row);
		}, $rows);
	}

	public function getCashLink($cashId, $params)
	{
		global $DB;
		$row = $DB->GetRow("select l.* from billtech_payment_links l
								left join cash c on l.src_cash_id = c.id
								where src_cash_id = ?", array($cashId));
		if (!$row) {
			return null;
		}
		$link = BillTechLink::fromRow($row);

		$this->addParamsToLink($params, $link);
		return $link;
	}

	public function getBalanceLink($customerId, $params)
	{
		global $DB;
		$row = $DB->GetRow("select l.*, c.docid from billtech_payment_links l
								left join cash c on l.src_cash_id = c.id
								left join billtech_payments bp on l.token = bp.token
								where customer_id = ? and bp.id is null
								order by c.time desc limit 1", array($customerId));
		if (!$row) {
			return null;
		} else {
			$balanceLink = BillTechLink::fromRow($row);
			$this->addParamsToLink(array_merge($params, ['type' => 'balance']), $balanceLink);
			return $balanceLink;
		}
	}

	public function updateForAll()
	{
		global $DB;
		$actions = array(
			'add' => array(),
			'update' => array(),
			'close' => array(),
		);
		$this->addMissingCustomerInfo();
		$customerIds = $this->getCustomerIdsForUpdate();

		if ($this->verbose) {
			echo "Found " . count($customerIds) . " customers to update\n";
		}

		if (!is_array($customerIds)) {
			return;
		}

		$maxCashId = $DB->GetOne("select max(id) from cash");

		foreach ($customerIds as $idx => $customerId) {
			echo "Collecting actions for customer " . ($idx + 1) . " of " . count($customerIds) . "\n";
			$actions = array_merge_recursive($actions, $this->getCustomerUpdateBalanceActions($customerId));
		}

		if ($this->verbose) {
			echo "Adding " . count($actions['add']) . " links\n";
			echo "Updating " . count($actions['update']) . " links\n";
			echo "Cancelling " . count($actions['close']) . " links\n";
		}

		$this->performActions($actions);
		$this->updateCustomerInfos($customerIds, $maxCashId);
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
	public function getTargetLinks(array $cashItems)
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

				$key = isset($cash['docid']) ? $cash['docid'] : 'cash_' . $cash['id'];

				if (isset($liabilities[$key])) {
					$liabilities[$key]->amount += $amountToPay;
				} else {
					$liabilities[$key] = BillTechLink::linked($cash['customerid'], $cash['id'], $cash['docid'], $amountToPay);
				}
			}
			$balance = self::intToMoney($intBalance - $intCashValue);
		}

		return array_values($liabilities);
	}

	/* @throws Exception
	 * @var $links BillTechLink[]
	 */
	private function addPayments($links)
	{
		global $DB;

		$generatedLinks = BillTechLinkApiService::generatePaymentLinks($links);
		$values = array();
		foreach ($generatedLinks as $idx => $generatedLink) {
			$link = $links[$idx];
			array_push($values,
				$link->customerId,
				$link->srcCashId,
				$link->srcDocumentId,
				$link->type,
				$generatedLink->link,
				$generatedLink->shortLink,
				$generatedLink->token,
				$link->amount
			);
		}

		$sql = "insert into billtech_payment_links(customer_id, src_cash_id, src_document_id, type, link, short_link, token, amount) values " .
			BillTech::prepareMultiInsertPlaceholders(count($generatedLinks), 8) . ";";
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
		$generatedLink = BillTechLinkApiService::generatePaymentLinks([$link])[0];
		$DB->Execute("update billtech_payment_links set amount = ?, link = ?, short_link = ?, token = ? where id = ?",
			array($link->amount, $generatedLink->link, $generatedLink->shortLink, $generatedLink->token, $link->id));
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
			if ($this->verbose) {
				echo "Adding batch " . ($idx + 1) . " of " . count($addBatches) . "\n";
			}
			$this->addPayments($links);
		}

		foreach ($actions['update'] as $idx => $link) {
			if ($this->verbose) {
				echo "Updating link " . ($idx + 1) . " of " . count($actions['update']) . "\n";
			}
			$this->updatePaymentAmount($link);
		}

		foreach ($actions['close'] as $idx => $link) {
			if ($this->verbose) {
				echo "Closing link " . ($idx + 1) . " of " . count($actions['close']) . "\n";
			}
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
		$cashItems = $DB->GetAll("select id, value, customerid, docid from cash where customerid = ? order by time desc, id desc", array($customerId));
		if (!$cashItems) {
			return $actions;
		}

		$targetLinks = $this->getTargetLinks($cashItems);
		$existingLinks = $this->getCustomerPaymentLinks($customerId);
		$existingLinkMap = BillTech::toMap(function ($link) {
			/* @var $payment BillTechLink */
			return $link->getKey();
		}, $existingLinks);

		foreach ($targetLinks as $targetLink) {
			/* @var $existingLink BillTechLink */
			$existingLink = $existingLinkMap[$targetLink->getKey()];
			if (isset($existingLink) && self::moneyToInt($existingLink->amount) != self::moneyToInt($targetLink->amount)) {
				$existingLink->amount = $targetLink->amount;
				array_push($actions['update'], $existingLink);
			} else if (!isset($existingLink) && self::moneyToInt($targetLink->amount) > 0) {
				array_push($actions['add'], $targetLink);
			}

			if (isset($existingLink)) {
				unset($existingLinkMap[$targetLink->getKey()]);
			}
		}

		foreach ($existingLinkMap as $cashId => $existingLink) {
			array_push($actions['close'], $existingLink);
		}

		return $actions;
	}

	private function checkLastUpdate($customerId)
	{
		global $DB;
		$customerInfo = $DB->GetRow("select bci.*, max(c.id) as new_last_cash_id from billtech_customer_info bci 
										left join cash c on c.customerid = bci.customer_id
										where bci.customer_id = ?
										group by bci.customer_id", array($customerId));

		if ($customerInfo) {
			if ($customerInfo['new_last_cash_id'] > $customerInfo['last_cash_id']) {
				$DB->Execute("update billtech_customer_info set last_cash_id = ? where customer_id = ?", array($customerInfo['new_last_cash_id'], $customerId));
				return true;
			} else {
				return false;
			}
		} else {
			$DB->Execute("insert into billtech_customer_info (customer_id, last_cash_id) values (?, ?)", array($customerId, $customerInfo['new_last_cash_id']));
			return true;
		}
	}

	private function addMissingCustomerInfo()
	{
		global $DB;
		$DB->Execute("insert into billtech_customer_info (customer_id, last_cash_id)
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
										group by bci.customer_id, bci.last_cash_id
										having bci.last_cash_id <= coalesce(max(ca.id), 0);");
	}

	/**
	 * @param array $customerIds
	 */
	private function updateCustomerInfos(array $customerIds, $maxCashId)
	{
		global $DB;
		$DB->Execute("update billtech_customer_info set last_cash_id = ? where customer_id in ("
			. BillTech::repeatWithSeparator("?", ",", count($customerIds)) . ")", array_merge([$maxCashId], $customerIds));
	}

	/**
	 * @param array $params
	 * @param BillTechLink $link
	 */
	private function addParamsToLink(array $params, BillTechLink $link)
	{
		$link->link .= http_build_query($params);

		if ($link->shortLink) {
			$link->shortLink = $this->linkShortener->addParameters($link->shortLink, $params);
		}
	}
}

