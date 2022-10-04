<?php

$cashimport_enabled = $this->Execute("SELECT var from uiconfig WHERE section=? AND var=?", array('billtech', 'cashimport_enabled'));

if ($cashimport_enabled) {
	$this->Execute("UPDATE uiconfig SET value=? WHERE section=? AND var=?", array('never', 'billtech', 'payment_expiration'));
}

$this->Execute("UPDATE dbinfo SET keyvalue = ? WHERE keytype = ?", array('2022100400', 'dbversion_BillTech'));