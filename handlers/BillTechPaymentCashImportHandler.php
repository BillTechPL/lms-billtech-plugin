<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechPaymentCashImportHandler
{
    function processCashImport($import)
    {
        global $DB, $LMS;
        $description = $import['description'];

        if ($description) {
            preg_match('/ref:(\d{8}-\d{6})/', $description, $matches);
            if (isset($matches[1])) {
                $reference_number = $matches[1];
                $payment = $DB->GetRow("SELECT id, amount, closed, cashid FROM billtech_payments WHERE reference_number=? AND closed=0", array($reference_number));
                if($payment){
                    $DB->Execute("UPDATE billtech_payments SET closed = 1, cashid = NULL WHERE id = ?", array($payment['id']));
                    $LMS->DelBalance($payment['cashid']);
                }
            }
        }
        return $import;
    }
}