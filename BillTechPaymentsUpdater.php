<?php

class BillTechPaymentsUpdater
{
	private $rate;
	private $timeout;

	public function __construct($rate = 60, $timeout = 10)
	{
		$this->rate = $rate;
		$this->timeout = $timeout;
	}

	public function checkForUpdate()
	{
		global $DB;
		ob_start();
		$force_sync = $_SERVER['HTTP_X_BILLTECH_FORCE_SYNC'];
		$now = time();

		if (!ConfigHelper::getConfig('billtech.api_secret')) {
			return;
		}

		if ($force_sync) {
			$last_sync = $current_sync = 0;
		} else {
			$billTechInfo = $this->readBillTechInfo();
			$last_sync = $billTechInfo['last_sync'];
			$current_sync = $billTechInfo['current_sync'];
		}

		$this->checkExpired();

		if ($now - $last_sync > $this->rate && $now - $current_sync > $this->timeout) {
			$DB->Execute("UPDATE billtech_info SET keyvalue = ? WHERE keytype = 'current_sync'", array($now));

			$this->update($now, $last_sync);
			header('X-BillTech-Synced: true');
		}
	}

	private function checkExpired()
	{
		global $DB, $LMS;

		$expiration = ConfigHelper::getConfig('billtech.payment_expiration', 5);
		$payments = $DB->GetAll("SELECT id, customerid, amount, cdate, closed, cashid FROM billtech_payments WHERE closed = 0 AND ?NOW? > cdate + $expiration * 86400");

		if (sizeof($payments)) {
			foreach ($payments as $payment) {
				if ($payment['closed']) {
					$addbalance = array(
						'value' => $payment['amount'],
						'type' => 100,
						'customerid' => $payment['customerid'],
						'comment' => 'BillTech Payments',
						'time' => $payment['cdate']
					);

					$LMS->AddBalance($addbalance);
					$cashid = $DB->GetLastInsertID('cash');

					$DB->Execute("UPDATE billtech_payments SET closed = 0, cashid = ? WHERE id = ?", array($cashid, $payment['id']));
				} else {
					$DB->Execute("UPDATE billtech_payments SET closed = 1, cashid = NULL WHERE id = ?", array($payment['id']));
					$LMS->DelBalance($payment['cashid']);
				}
			}
		}
	}

	private
	function update($current_sync, $last_sync)
	{
		global $DB, $LMS;
		$url = ConfigHelper::getConfig("billtech.api_url") . "/api/service-provider/payments";

		$url .= "?fromDate=" . $last_sync;

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_USERPWD => ConfigHelper::getConfig('billtech.api_key') . ':' . ConfigHelper::getConfig('billtech.api_secret')
		));

		if (ConfigHelper::getConfig('billtech.dev')) {
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		}

		$response = curl_exec($curl);

		curl_close($curl);

		if (!$response) {
			$DB->Execute("INSERT INTO billtech_log (cdate, type, description)  VALUES (?NOW?, ?, ?)", array('ERROR', 'No response from BillTech server'));
			return;
		}

		$response = json_decode($response);

		if ($response->status == 'ERROR') {
			$DB->Execute("INSERT INTO billtech_log (cdate, type, description)  VALUES (?NOW?, ?, ?)", array('ERROR', json_encode($response)));
			return;
		}

		$DB->Execute("INSERT INTO billtech_log (cdate, type, description)  VALUES (?NOW?, ?, ?)", array('SYNC_SUCCESS', ''));

		$DB->BeginTrans();

		$customers = array();

		foreach ($response as $payment) {

			$id = $DB->GetOne("SELECT id FROM billtech_payments WHERE reference_number=?", array($payment->paymentReferenceNumber));
			if (!$id) {
				$addbalance = array(
					'value' => $payment->amount,
					'type' => 100,
					'customerid' => $payment->userId,
					'comment' => 'BillTech Payments',
					'time' => $payment->paymentDate
				);

				$LMS->AddBalance($addbalance);
				$cashid = $DB->GetLastInsertID('cash');
				$ten = $payment->companyTaxId ? $payment->companyTaxId : '';
				$title = $payment->title ? $payment->title : '';

				$amount = str_replace(',', '.', $payment->amount);

				$DB->Execute("INSERT INTO billtech_payments (cashid, ten, document_number, customerid, amount, title, reference_number, cdate, closed) "
					. "VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
					array($cashid, $ten, $payment->invoiceNumber, $payment->userId, $amount, $title, $payment->paymentReferenceNumber, $payment->paymentDate));

				$customers[$payment->userId] = $payment->userId;
			}
		}

		if (ConfigHelper::getConfig('billtech.manage_cutoff', true)) {
			foreach ($customers as $customerid) {
				$this->checkCutoff($customerid);
			}
		}

		$DB->Execute("UPDATE billtech_info SET keyvalue = ? WHERE keytype='last_sync'", array($current_sync));

		if (sizeof($DB->GetErrors())) {
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
		foreach ($rows as $row) {
			$billTechInfo[$row['keytype']] = $row['keyvalue'];
		}
		return $billTechInfo;
	}
}