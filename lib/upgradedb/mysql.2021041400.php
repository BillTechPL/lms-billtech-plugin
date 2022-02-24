<?php
$this->Execute("UPDATE uiconfig SET var='append_customer_info' WHERE var='append_client_info'");

$this->Execute("UPDATE dbinfo SET keyvalue = ? WHERE keytype = ?", array('2021041400', 'dbversion_BillTech'));
