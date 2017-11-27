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

$this->Execute("INSERT INTO uiconfig(section, var, value)
  SELECT 'billtech', 'isp_id', '' 
  WHERE NOT EXISTS (SELECT 1 from uiconfig WHERE section = 'billtech' AND var = 'isp_id')");
$this->Execute("INSERT INTO uiconfig(section, var, value)
  SELECT 'billtech', 'payment_url', '' 
  WHERE NOT EXISTS (SELECT 1 from uiconfig WHERE section = 'billtech' AND var = 'payment_url')");
$this->Execute("INSERT INTO uiconfig(section, var, value)
  SELECT 'billtech', 'api_url', '' 
  WHERE NOT EXISTS (SELECT 1 from uiconfig WHERE section = 'billtech' AND var = 'api_url')");
$this->Execute("INSERT INTO uiconfig(section, var, value)
  SELECT 'billtech', 'api_key', '' 
  WHERE NOT EXISTS (SELECT 1 from uiconfig WHERE section = 'billtech' AND var = 'api_key')");
$this->Execute("INSERT INTO uiconfig(section, var, value)
  SELECT 'billtech', 'api_secret', '' 
  WHERE NOT EXISTS (SELECT 1 from uiconfig WHERE section = 'billtech' AND var = 'api_secret')");

$this->Execute("UPDATE dbinfo SET keyvalue = ? WHERE keytype = ?",array('2017112400', 'dbversion_BillTech'));
