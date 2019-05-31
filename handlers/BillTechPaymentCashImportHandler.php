<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechPaymentCashImportHandler
{
	function processCashImport(array $hookdata = array())
	{
		global $DB, $LMS;

		foreach ($hookdata['cashimports'] as $import) {
			$description = $import['comment'];

			if (ConfigHelper::getConfig('billtech.cashimport_enabled', false) && $description) {
				preg_match('/ref:(\d{8}-\d{6})/', preg_replace('[,|]', '', $description), $matches);
				if (isset($matches[1])) {
					$reference_number = $matches[1];
					$payment = $DB->GetRow("SELECT id, amount, closed, cashid FROM billtech_payments WHERE reference_number=? AND closed=0", array($reference_number));
					if ($payment) {
						$DB->Execute("UPDATE billtech_payments SET closed = 1, cashid = NULL WHERE id = ?", array($payment['id']));
						$LMS->DelBalance($payment['cashid']);
					}
				}
			}
		}
		return $hookdata;
	}
}