<?php
$this->Execute("
CREATE TABLE billtech_log
(
	cdate       INT DEFAULT 0           NOT NULL,
	type   VARCHAR(255) DEFAULT '' NOT NULL,
	description TEXT                    NOT NULL
)
	ENGINE = InnoDB;
");

$this->Execute("UPDATE dbinfo SET keyvalue = ? WHERE keytype = ?",array('2017112400', 'dbversion_BillTech'));
