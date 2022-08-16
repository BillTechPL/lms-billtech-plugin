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

	public function getCashLinkByDocumentId($documentId, $params)
	{
		global $DB;
		$row = $DB->GetRow("select bpl.* from billtech_payment_links bpl
								left join cash c on bpl.src_cash_id = c.id
								left join documents d on c.docid=d.id
								where d.id=?", array($documentId));

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

		if ($this->performActions($actions)) {
			$this->updateCustomerInfos($customerIds, $maxCashId);
		}
	}

	public function updateCustomerBalance($customerId)
	{
		global $DB;
		$this->addMissingCustomerInfo();

		$customerInfo = $DB->GetRow("select bci.*, max(c.id) as new_last_cash_id from billtech_customer_info bci 
										left join cash c on c.customerid = bci.customer_id
										where bci.customer_id = ?
										group by bci.customer_id", array($customerId));

		if ($customerInfo['new_last_cash_id'] > $customerInfo['last_cash_id']) {
			$actions = $this->getCustomerUpdateBalanceActions($customerId);
			if ($this->performActions($actions)) {
				$DB->Execute("update billtech_customer_info set last_cash_id = ? where customer_id = ?", array($customerInfo['new_last_cash_id'], $customerId));
			}
		}
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
				number_format($link->amount, 2, '.', '')
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
			array(number_format($link->amount, 2, '.', ''), $generatedLink->link, $generatedLink->shortLink, $generatedLink->token, $link->id));
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

	/**
	 * @return array
	 */
	private function getPaymentLinksToCancel()
	{
		global $DB;
		return $DB->GetCol("select token from billtech_payment_links where src_cash_id is null and src_document_id is null")?: array();
	}

	/**
	 * @var $linkToken String
	 */
	private function deletePaymentLinkByToken($linkToken)
	{
		global $DB;
		$DB->Execute("delete from billtech_payment_links where token = ?", array($linkToken));
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

	public function cancelPaymentLinksIfManuallyDeletedLiability() {
		$paymentLinkTokensToCancel = $this->getPaymentLinksToCancel();
		foreach($paymentLinkTokensToCancel as $linkToken){
			BillTechLinkApiService::cancelPaymentLink($linkToken);
			$this->deletePaymentLinkByToken($linkToken);
		}
		echo "Cancelled " . count($paymentLinkTokensToCancel) . " links for manually deleted liability\n";
	}

	/**
	 * @param array $actions
	 * @return bool
	 */
	public function performActions($actions)
	{
		global $DB;
		$addBatches = array_chunk($actions['add'], $this->batchSize);
		$errorCount = 0;
		foreach ($addBatches as $idx => $links) {
			if ($this->verbose) {
				echo "Adding batch " . ($idx + 1) . " of " . count($addBatches) . "\n";
			}
			try {
				$DB->BeginTrans();
				$this->addPayments($links);
				if (!$DB->GetErrors()) {
					$DB->CommitTrans();
				} else {
					foreach ($DB->GetErrors() as $error) {
						echo $error['query'] . PHP_EOL;
						echo $error['error'] . PHP_EOL;
					}
					$errorCount++;
					$DB->RollbackTrans();
				}
			} catch (Exception $e) {
				$errorCount++;
				if ($this->debug) {
					echo $e->getMessage();
				}
			}
		}

		foreach ($actions['update'] as $idx => $link) {
			if ($this->verbose) {
				echo "Updating link " . ($idx + 1) . " of " . count($actions['update']) . "\n";
			}
			try {
				$DB->BeginTrans();
				$this->updatePaymentAmount($link);
				if (!$DB->GetErrors()) {
					$DB->CommitTrans();
				} else {
					foreach ($DB->GetErrors() as $error) {
						echo $error['query'] . PHP_EOL;
						echo $error['error'] . PHP_EOL;
					}
					$errorCount++;
					$DB->RollbackTrans();
				}
			} catch (Exception $e) {
				$errorCount++;
				if ($this->debug) {
					echo $e->getMessage();
				}
			}
		}

		foreach ($actions['close'] as $idx => $link) {
			if ($this->verbose) {
				echo "Closing link " . ($idx + 1) . " of " . count($actions['close']) . "\n";
			}
			try {
				$DB->BeginTrans();
				$this->closePayment($link);
				if (!$DB->GetErrors()) {
					$DB->CommitTrans();
				} else {
					foreach ($DB->GetErrors() as $error) {
						echo $error['query'] . PHP_EOL;
						echo $error['error'] . PHP_EOL;
					}
					$errorCount++;
					$DB->RollbackTrans();
				}
			} catch (Exception $e) {
				$errorCount++;
				if ($this->verbose) {
					echo $e->getMessage();
				}
			}
		}

		return $errorCount == 0;
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
		$cashItems = $DB->GetAll("select c.id, value, c.customerid, d.id as docid from cash c left join documents d on d.id = c.docid where c.customerid = ? order by c.time desc, c.id desc", array($customerId));
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
	 * @param $maxCashId
	 */
	private function updateCustomerInfos(array $customerIds, $maxCashId)
	{
		global $DB;
		$params = $customerIds;
		array_unshift($params, $maxCashId);
		$DB->Execute("update billtech_customer_info set last_cash_id = ? where customer_id in (" . BillTech::repeatWithSeparator("?", ",", count($customerIds)) . ")", $params);
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
