<?php
$this->Execute("
ALTER TABLE billtech_payment_links
DROP CONSTRAINT billtech_payment_links_src_cash_id_fkey,
                ADD CONSTRAINT billtech_payment_links_src_cash_id_fkey
FOREIGN KEY (src_cash_id) REFERENCES cash ON
DELETE
SET NULL
");

$this->Execute("
ALTER TABLE billtech_payment_links
DROP CONSTRAINT billtech_payment_links_src_document_id_fkey,
                ADD CONSTRAINT billtech_payment_links_src_document_id_fkey
FOREIGN KEY (src_document_id) REFERENCES documents ON
DELETE
SET NULL
");
