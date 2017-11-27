<?php

/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechButtonInsertHandler
{
	public function addButtonToInvoiceEmail(array $hook_data = array())
	{
		$link = BillTechLinkGenerator::createPaymentLink($hook_data['doc']['id'], $hook_data['doc']['customerid']);
		$hook_data['body'] = preg_replace('/%billtech_btn/',
			$this->createEmailButton($hook_data['mail_format'], $link),
			$hook_data['body']);

		$hook_data['headers'] = $this->fillEmailHeaders($hook_data['doc'], $hook_data['headers']);

		return $hook_data;
	}

	public function notifyCustomerDataParse(array $hook_data = array())
	{
		$data = $hook_data['data'];
		$customerid = $hook_data['customer']['id'];
		$link = BillTechLinkGenerator::createPaymentLink('balance', $customerid);

		$hook_data['data'] = preg_replace('/%billtech_btn/',
			$this->createEmailButton('html', $link), $data);

		return $hook_data;
	}

	public function addButtonToCustomerView(array $hook_data = array())
	{
		global $LMS;
		$smarty = $hook_data['smarty'];
		$customerid = $hook_data['customerid'];

		$customerinfo = $LMS->GetCustomer($customerid);
		if ($customerinfo['balance'] < 0) {
			$smarty->assign('billtech_balance_link', BillTechLinkGenerator::createPaymentLink('balance', $customerid));
			$billtech_balance_button = $smarty->fetch('button' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'customerbilltechbutton.html');
		} else {
			$billtech_balance_button = '';
		}

		$custombalancedata = $smarty->getTemplateVars('custombalancedata') . $billtech_balance_button;
		$smarty->assign('custombalancedata', $custombalancedata);
	}

	public function addButtonToCustomerOtherIPView(array $hook_data = array())
	{
		global $LMS;
		$smarty = $hook_data['smarty'];
		$customerid = $hook_data['customerid'];

		$customerinfo = $LMS->GetCustomer($customerid);
		if ($customerinfo['balance'] < 0) {
			$smarty->assign('billtech_balance_link', BillTechLinkGenerator::createPaymentLink('balance', $customerid));
			$billtech_balance_button = $smarty->fetch('button' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'customerotheripbilltechbutton.html');
		} else {
			$billtech_balance_button = '';
		}

		$custombalancedata = $smarty->getTemplateVars('custombalancedata') . $billtech_balance_button;
		$smarty->assign('custombalancedata', $custombalancedata);
	}

	public function addButtonsToFinancesView(array $hook_data = array())
	{
		global $SESSION, $DB;
		$smarty = $hook_data['smarty'];
		$userinfo = $smarty->getTemplateVars('userinfo');
		$balancelist = $smarty->getTemplateVars('balancelist');

		if ($balancelist['balance'] >= 0) return;

		$style = ConfigHelper::getConfig('userpanel.style', 'default');
		$customerid = $userinfo['id'];

		$billtech_payments = $DB->GetAll("SELECT id, ten, amount, document_number, closed FROM billtech_payments WHERE customerid = ?", array($customerid));

		$billtech_payments_balance = 0;

		$number_to_payment = array();

		if (isset($billtech_payments)) {
			foreach ($billtech_payments as $billtech_payment) {
				if ($billtech_payment['closed'] == 0) {
					$billtech_payments_balance += $billtech_payment['amount'];
				}

				$number_to_payment[$billtech_payment['document_number']] = $billtech_payment;
			}
		}

		if (isset($balancelist)) {
			foreach ($balancelist['list'] as &$item) {
				$document = $DB->GetRow("SELECT number, template, cdate FROM documents WHERE id = ?", array($item['docid']));
				$fullnumber = docnumber(array(
					'number' => $document['number'],
					'template' => $document['template'],
					'cdate' => $document['cdate'],
					'customerid' => $customerid,
				));

				$billtech_payment = $number_to_payment[$fullnumber];

				$closed = $billtech_payment['amount'] + $item['value'] == 0;

				if (!$closed && $item['docid'] && $item['value'] < 0) {
					$customlinks = $item['customlinks'];
					if (!isset($customlinks)) {
						$customlinks = array();
					}
					$link = $this->createRowButton($smarty, $item['docid'], $customerid, $style);
					array_push($customlinks, array(
						'extra' => $link
					));
					$item['customlinks'] = $customlinks;
				}
			};
		}
		$smarty->assign('balancelist', $balancelist);
		$smarty->assign('billtech_balance_link', BillTechLinkGenerator::createPaymentLink('balance', $SESSION->id));
		$billtech_balance_button = $smarty->fetch('button' . DIRECTORY_SEPARATOR . $style . DIRECTORY_SEPARATOR . 'billtechbalancebutton.html');
		$smarty->assign('custom_content', $smarty->getTemplateVars('custom_content') . $billtech_balance_button);
		return $hook_data;
	}

	function fillEmailHeaders($doc, $headers)
	{
		global $LMS;

		$doc_content = $LMS->GetInvoiceContent($doc['id']);
		$document_number = (!empty($doc['template']) ? $doc['template'] : '%N/LMS/%Y');
		$document_number = docnumber(array(
			'number' => $doc['number'],
			'template' => $document_number,
			'cdate' => $doc['cdate'] + date('Z'),
			'customerid' => $doc['customerid'],
		));

		$nrb = bankaccount($doc_content['customerid'], $doc_content['account']);

		if ($nrb == "" && !empty($doc_content['bankaccounts'])) {
			$nrb = $doc_content['bankaccounts'][0];
		}

		$headers['X-BillTech-ispId'] = ConfigHelper::getConfig('billtech.isp_id');
		$headers['X-BillTech-customerId'] = $doc_content['customerid'];
		$headers['X-BillTech-invoiceNumber'] = $document_number;
		$headers['X-BillTech-nrb'] = $nrb;
		$headers['X-BillTech-amount'] = $doc_content['total'];
		$headers['X-BillTech-paymentDue'] = $doc_content['pdate'];

		return $headers;
	}

	private function createRowButton(Smarty $smarty, $docid, $userid, $style = 'default')
	{
		$link = BillTechLinkGenerator::createPaymentLink($docid, $userid);
		$smarty->assign('link', $link);
		return $smarty->fetch('button' . DIRECTORY_SEPARATOR . $style . DIRECTORY_SEPARATOR . 'billtechrowbutton.html');
	}

	function createEmailButton($mail_format, $link)
	{
		global $SMARTY;
		if (isset($SMARTY) && isset($mail_format) && $mail_format == 'html') {
			$SMARTY->assign('link', $link);
			return $SMARTY->fetch('button/billtechemailbutton.html');
		} else {
			return 'Opłać teraz: ' . $link;
		}
	}
}