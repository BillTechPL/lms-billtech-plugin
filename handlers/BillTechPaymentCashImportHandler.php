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
            $description = $import['description'];
            if ($description) {
                echo 'importing ' . $description;
                $description = preg_replace('/[,|]/', '', $description);
                preg_match('/ref[: ](\d{8}-\d{6})/', $description, $matches);
                if (isset($matches[1])) {
                    $reference_number = $matches[1];
                    $payments = $DB->GetAll("SELECT id, amount, cashid FROM billtech_payments WHERE reference_number=? AND closed=0", array($reference_number));
                    if (is_array($payments) && !empty($payments)) {
                        foreach ($payments as $payment) {
			  $cash = $LMS->GetCashByID($payment['cashid']);
			  if ($cash && $cash['comment'] == BillTech::CASH_COMMENT) {
			    $DB->Execute("UPDATE billtech_payments SET closed = 1, cashid = NULL WHERE id = ?", array($payment['id']));
			    $LMS->DelBalance($payment['cashid']);
			  }
			}
                    }
                }
            }
        }
        return $hookdata;
    }
}

