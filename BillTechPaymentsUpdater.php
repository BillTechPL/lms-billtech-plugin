<?php

class BillTechPaymentsUpdater
{
	private $linksManager;
	public $verbose;

	public function __construct($verbose = false)
	{
		$this->linksManager = new BillTechLinksManager($verbose);
		$this->verbose = $verbose;
	}

	public function checkForUpdate()
	{
		if (!ConfigHelper::getConfig('billtech.api_secret')) {
			if ($this->verbose) {
				echo "Missing api_secret\n";
			}
			return;
		}

		$this->checkExpired();
		$this->updatePayments();
		$this->clearOldLogs();
	}

	private function checkExpired()
	{
		global $DB, $LMS;
		$expiration = ConfigHelper::getConfig('billtech.payment_expiration', 5);

		if ($expiration == 'never') return;

		if ($this->verbose) {
			echo "Checking expired payments\n";
		}

		$DB->BeginTrans();
		$payments = $DB->GetAll("SELECT id, customerid, amount, cdate, cashid FROM billtech_payments WHERE closed = 0 AND ?NOW? > cdate + $expiration * 86400");

		if ($this->verbose) {
			echo "Closing " . count($payments) . " expired payments\n";
		}

		if (is_array($payments) && sizeof($payments)) {
			foreach ($payments as $payment) {
				$cash = $LMS->GetCashByID($payment['cashid']);
				if ($cash && strpos($cash['comment'], BillTech::CASH_COMMENT) !== false) {
					$DB->Execute("UPDATE billtech_payments SET closed = 1, cashid = NULL WHERE id = ?", array($payment['id']));
					$LMS->DelBalance($payment['cashid']);
				}
			}
		}
		if (is_array($DB->GetErrors()) && sizeof($DB->GetErrors())) {
			$DB->RollbackTrans();
		} else {
			$DB->CommitTrans();
		}
	}

	private
	function updatePayments()
	{
		global $DB, $LMS;

		if ($this->verbose) {
			echo "Updating payments\n";
		}

		$now = time();
		$last_sync = $this->getLastSync();

		$client = BillTechApiClientFactory::getClient();
		$path = "/pay/v1/payments/search" . "?fromDate=" . (ConfigHelper::getConfig("billtech.debug") ? 0 : $last_sync);

		$response = $client->get($path);

		if ($response->getStatusCode() != 200) {
			$DB->Execute("INSERT INTO billtech_log (cdate, type, description)  VALUES (?NOW?, ?, ?)", array('ERROR', "/pay/v1/payments returned code " . $response->getStatusCode() . "\n" . $response->getBody()));
			return;
		}
		if (ConfigHelper::getConfig("billtech.debug")) {
			file_put_contents('/var/tmp/billtech_debug.txt', print_r($response->getBody(), true));
		}

		$payments = json_decode($response->GetBody());
		$DB->Execute("INSERT INTO billtech_log (cdate, type, description)  VALUES (?NOW?, ?, ?)", array('SYNC_SUCCESS', ''));
		$DB->BeginTrans();
		$DB->Execute("UPDATE billtech_info SET keyvalue = ? WHERE keytype = 'current_sync'", array($now));
		$customers = array();

		if ($this->verbose) {
			echo "Found " . count($payments) . " new payments\n";
		}

		if (!is_array($payments)) {
			return;
		}

		foreach ($payments as $payment) {
			$payment->paidAt = strtotime($payment->paidAt);

			if (!$payment->userId) {
				$DB->Execute("INSERT INTO billtech_log (cdate, type, description)  VALUES (?NOW?, ?, ?)", array('ERROR', json_encode($payment)));
				continue;
			}

			$id = $DB->GetOne("SELECT id FROM billtech_payments WHERE reference_number=?", array($payment->referenceNumber));
			if (!$id) {
				$addbalance = array(
					'value' => $payment->amount,
					'type' => 100,
					'userid' => null,
					'customerid' => $payment->userId,
					'comment' => BillTech::CASH_COMMENT.' for: '.$payment->invoiceNumber,
					'time' => $payment->paidAt
				);

				$cashid = $this->AddBalanceAndReturnCashIdOrFalse($addbalance);
				if ($cashid) {
					$title = $payment->title ? $payment->title : '';

					$amount = str_replace(',', '.', $payment->amount);

					$DB->Execute("INSERT INTO billtech_payments (cashid, ten, document_number, customerid, amount, title, reference_number, cdate, closed, token) "
						. "VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)",
						array($cashid, '', $payment->invoiceNumber, $payment->userId, $amount, $title, $payment->referenceNumber, $payment->paidAt, $payment->token));

					$customers[$payment->userId] = $payment->userId;
				}
			}
		}

		if ($this->verbose) {
			echo "Updating " . count($customers) . " customers\n";
		}

		foreach ($customers as $customerid) {
			if (ConfigHelper::getConfig('billtech.manage_cutoff', true)) {
				$this->checkCutoff($customerid);
			}

			$this->linksManager->updateCustomerBalance($customerid);
		}


		$DB->Execute("UPDATE billtech_info SET keyvalue = ? WHERE keytype='last_sync'", array($now));

		if (is_array($DB->GetErrors()) && sizeof($DB->GetErrors())) {
			$DB->RollbackTrans();
		} else {
			$DB->CommitTrans();
		}
	}

	private
	function checkCutoff($customerid)
	{
		global $DB, $LMS;
		$excluded_customergroups = ConfigHelper::getConfig('cutoff.excluded_customergroups', '');
		$customergroups = ConfigHelper::getConfig('cutoff.customergroups', '');
		if ($excluded_customergroups) {
			$excluded = $DB->GetOne("SELECT count(*) FROM customerassignments
                                WHERE customerassignments.customerid = ?
                                  AND customerassignments.customergroupid IN ($excluded_customergroups)", array($customerid));
			if ($excluded) return;
		}

		if ($customergroups) {
			$included = $DB->GetOne("SELECT count(*) FROM customerassignments
                                WHERE customerassignments.customerid = ?
                                  AND customerassignments.customergroupid IN ($customergroups)", array($customerid));
			if (!$included) return;
		}


		$extend_deadline = ConfigHelper::getConfig('cutoff.extend_deadline', 7);

		$extended_balance = $this->getCustomerDueBalance($customerid, $extend_deadline);
		$balance = $this->getCustomerDueBalance($customerid, 0);

		$cutoff_limit = ConfigHelper::getConfig('cutoff.limit', 0);

		if ($extended_balance >= $cutoff_limit) {
			$LMS->NodeSetU($customerid, TRUE);

			$cutoffstop = $DB->Execute("SELECT cutoffstop FROM customers WHERE id = ?", array($customerid));
			if ($cutoffstop > time() || $balance >= 0) {
				$LMS->NodeSetWarnU($customerid, FALSE);
			}

			$expiration = ConfigHelper::getConfig('billtech.payment_expiration', 5);
			$new_cutoffstop = time() + $expiration * 86400;

			if ($new_cutoffstop > $cutoffstop) {
				$DB->Execute("UPDATE customers SET cutoffstop = ? WHERE id = ?", array($new_cutoffstop, $customerid));
			}
		}

	}

	private
	function getCustomerDueBalance($customerid, $extend_deadline)
	{
		global $DB;
		$filter = "((d.paytime > 0 AND cdate + ((d.paytime + $extend_deadline) * 86400) < ?NOW?)
			OR d.paytime = 0
			OR d.paytime IS NULL)";

		return $balance = $DB->GetOne("SELECT SUM(c.value) FROM cash c 
												LEFT JOIN documents d ON d.id = c.docid 
												WHERE $filter AND c.customerid = ?", array($customerid));
	}

	private
	function readBillTechInfo()
	{
		global $DB;
		$billTechInfo = array();
		$rows = $DB->GetAll("SELECT keytype, keyvalue FROM billtech_info");

		if (!is_array($rows)) {
			return array();
		}

		foreach ($rows as $row) {
			$billTechInfo[$row['keytype']] = $row['keyvalue'];
		}
		return $billTechInfo;
	}

	private function clearOldLogs()
	{
		global $DB;

		if ($this->verbose) {
			echo "Clearing old logs\n";
		}

		$DB->Execute("DELETE FROM billtech_log WHERE type='SYNC_SUCCESS' AND cdate<(?NOW? - ?);",
			array(ConfigHelper::getConfig('billtech.log_retention_days', 7) * 24 * 3600));
	}

	/**
	 * @return mixed
	 */
	private function getLastSync()
	{
		$billTechInfo = $this->readBillTechInfo();
		return $billTechInfo['last_sync'];
	}

	public static function AddBalanceAndReturnCashIdOrFalse($addbalance) {
		global $DB, $LMS;

		$cashid = $LMS->AddBalance($addbalance);
		if ($cashid && $cashid == 1) {
			$cashid = $DB->GetLastInsertID('cash');
		}
		return $cashid;
	}
}
