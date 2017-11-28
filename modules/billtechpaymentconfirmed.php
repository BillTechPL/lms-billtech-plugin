<?php

$SESSION->restore('bplm', $bplm);
$SESSION->remove('bplm');

if (sizeof($_POST['marks']))
	foreach ($_POST['marks'] as $id => $mark)
		$bplm[$id] = $mark;

if (sizeof($bplm))
	foreach ($bplm as $mark)
		$ids[] = $mark;

if (sizeof($ids)) {
	$DB->BeginTrans();

	$payments = $DB->GetAll("SELECT id, customerid, amount, cdate, closed, cashid FROM billtech_payments WHERE id IN (" . implode(',', $ids) . ")", array($ids));

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

	if (sizeof($DB->GetErrors())) {
		$DB->RollbackTrans();
		throw new Exception("Error writing to database");
	} else {
		$DB->CommitTrans();
	}
}

$SESSION->redirect('?' . $SESSION->get('backto'));