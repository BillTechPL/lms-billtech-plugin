<?php

/*********************
 * @author BillTech Group sp. z o.o.
 * Copyright 2022
 *
 * All rights reserved.
 * Distribution of the software in any financial form is only allowed with
 * explicit, prior permission from the owner.
 *
 */

class BillTech extends LMSPlugin
{
	const PLUGIN_DB_VERSION = '2022012000';
	const PLUGIN_DBVERSION = BillTech::PLUGIN_DB_VERSION; /* Legacy for old LMS versions */
	const PLUGIN_SOFTWARE_VERSION = '2022012000';
	const PLUGIN_DIRECTORY_NAME = 'BillTech';
	const PLUGIN_NAME = 'BillTech';
	const PLUGIN_DESCRIPTION = 'BillTech - wersja: 20220120';
	const PLUGIN_AUTHOR = 'Michał Kaciuba &lt;michal@billtech.pl&gt;';
	const PLUGIN_DOC_URL = 'https://github.com/BillTechPL/lms-billtech-plugin/blob/master/README.md';
	const PLUGIN_REPO_URL = 'https://github.com/BillTechPL/lms-billtech-plugin';
	const CASH_COMMENT = 'Wpłata online';

	public function registerHandlers()
	{
		$this->handlers = array(
			'menu_initialized' => array(
				'class' => 'BillTechInitHandler',
				'method' => 'menuBillTech'
			),
			'modules_dir_initialized' => array(
				'class' => 'BillTechInitHandler',
				'method' => 'modulesBillTech'
			),
			'smarty_initialized' => array(
				'class' => 'BillTechInitHandler',
				'method' => 'smartyBillTech'
			),
			'userpanel_smarty_initialized' => array(
				'class' => 'BillTechInitHandler',
				'method' => 'smartyBillTech'
			),
			'invoice_email_before_send' => array(
				'class' => 'BillTechLinkInsertHandler',
				'method' => 'addButtonToInvoiceEmail'
			),
			'notify_parse_customer_data' => array(
				'class' => 'BillTechLinkInsertHandler',
				'method' => 'notifyCustomerDataParse'
			),
			'customer_before_display' => array(
				'class' => 'BillTechLinkInsertHandler',
				'method' => 'addButtonToCustomerView'
			),
			'customer_otherip_before_display' => array(
				'class' => 'BillTechLinkInsertHandler',
				'method' => 'addButtonToCustomerOtherIPView'
			),
			'userpanel_finances_main_before_module_display' => array(
				'class' => 'BillTechLinkInsertHandler',
				'method' => 'addButtonsToFinancesView'
			),
			'cashimport_after_commit' => array(
				'class' => 'BillTechPaymentCashImportHandler',
				'method' => 'processCashImport'
			),
			'messageadd_variable_parser' => array(
				'class' => 'BillTechLinkInsertHandler',
				'method' => 'messageaddCustomerDataParse'
			),
		);
	}

	public static function toMap($callback, array $array)
	{
		$map = array();
		foreach ($array as $item) {
			$map[$callback($item)] = $item;
		}
		return $map;
	}

	/**
	 * @param string $str
	 * @param $separator
	 * @param int $repeatCount
	 * @return string
	 */
	public static function repeatWithSeparator($str, $separator, $repeatCount)
	{
		return implode($separator, array_fill(0, $repeatCount, $str));
	}

	/**
	 * @param int $rowCount
	 * @param int $valuesCount
	 * @return string
	 */
	public static function prepareMultiInsertPlaceholders($rowCount, $valuesCount)
	{
		return BillTech::repeatWithSeparator("(" . BillTech::repeatWithSeparator("?", ",", $valuesCount) . ")", ',', $rowCount);
	}

	public static function measureTime($callback, $verbose)
	{
		$start = microtime(true);
		$callback();
		$time_elapsed_secs = microtime(true) - $start;
		if ($verbose) {
			echo "Update took " . $time_elapsed_secs . " s\n";
		}
	}

	public static function lock($lockName, $callback)
	{
		$fp = fopen('/tmp/billtech-lock-' . $lockName, 'w+');

		try {
			if (flock($fp, LOCK_EX | LOCK_NB)) {
				echo "Lock acquired\n";
				$callback();
				flock($fp, LOCK_UN);
			} else {
				exit("Could not acquire lock. Another process is running.\n");
			}
		} finally {
			fclose($fp);
		}
	}
}
