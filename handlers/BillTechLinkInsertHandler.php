<?php

/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechLinkInsertHandler
{
	private $linksManager;

	private function getLinksManager()
	{
		if (!isset($this->linksManager)) {
			$this->linksManager = new BillTechLinksManager();
		}
		return $this->linksManager;
	}

	private function getPaymentLink($doc, $customerId, $params = array())
	{
		global $DB;
		$linksManager = $this->getLinksManager();

		if ($doc == 'balance') {
			$link = $linksManager->getBalanceLink($customerId, $params);
		} else {
			$link = $linksManager->getCashLinkByDocumentId($doc, $params);
		}

		return $link ? $link->link : '';
	}

	private function getShortPaymentLink($doc, $customerId, $params = array())
	{
		global $DB;
		$linksManager = $this->getLinksManager();

		if ($doc == 'balance') {
			$link = $linksManager->getBalanceLink($customerId, $params);
		} else {
			$link = $linksManager->getCashLinkByDocumentId($doc, $params);
		}

		return $link ? $link->shortLink : '';
	}

	public function addButtonToInvoiceEmail(array $hook_data = array())
	{
		global $DB;
		$linksManager = $this->getLinksManager();

		$linksManager->updateCustomerBalance($hook_data['doc']['customerid']);
		$cashLink = $linksManager->getCashLinkByDocumentId($hook_data['doc']['id'], ['utm_medium' => 'email']) ?
			$linksManager->getCashLinkByDocumentId($hook_data['doc']['id'], ['utm_medium' => 'email'])->link : '';
		$balanceLink = $linksManager->getBalanceLink($hook_data['doc']['customerid'], ['utm_medium' => 'email']) ?
			$linksManager->getBalanceLink($hook_data['doc']['customerid'], ['utm_medium' => 'email'])->link : '';
		$cashBtnCode = $this->createEmailButton($hook_data['mail_format'], $cashLink);
		$balanceBtnCode = $this->createEmailButton($hook_data['mail_format'], $balanceLink);

		$hook_data['body'] = preg_replace('/%billtech_btn/', $cashBtnCode, $hook_data['body']);
		$hook_data['body'] = preg_replace('/%billtech_balance_btn/', $balanceBtnCode, $hook_data['body']);

		$hook_data['headers'] = $this->fillEmailHeaders($hook_data['doc'], $hook_data['headers']);

		return $hook_data;
	}

	public function notifyCustomerDataParse(array $hook_data = array())
	{
		$data = $hook_data['data'];
		$customerid = $hook_data['customer']['id'];
		$link = self::getPaymentLink('balance', $customerid, ['utm_medium' => 'email']);

		$hook_data['data'] = preg_replace('/%billtech_balance_btn/',
			$this->createEmailButton('html', $link), $data);

		return $hook_data;
	}

	public function messageaddCustomerDataParse(array $hook_data = array())
	{
		$customerid = $hook_data['data']['id'];
		$appendCustomerInfoEnabled = ConfigHelper::getConfig('billtech.append_customer_info', true);

		$amount = sprintf('%01.2f', -$hook_data['data']['balance']);
		$btnPatterns = ['/%billtech_balance_btn/', '/'.$amount.'illtech_balance_btn/'];

        if(isset($hook_data['data']['phone'])) {
            $phone = $hook_data['data']['phone'];
        }else if(isset($hook_data['data']['phones'])) {
            $phone = $hook_data['data']['phones'];
        }

		if (isset($phone)) {
			$link = self::getShortPaymentLink('balance', $customerid);
			if ($appendCustomerInfoEnabled) {
				$hook_data['body'] = preg_replace($btnPatterns, $link, $hook_data['body']);
			} else {
				$hook_data['body'] = preg_replace($btnPatterns, '', $hook_data['body']);
			}
		} else {
			$link = self::getPaymentLink('balance', $customerid, ['utm_medium' => 'email']);
			if (isset($hook_data['data']['contenttype']) && $hook_data['data']['contenttype'] == 'text/html') {
				$mail_format = ConfigHelper::getConfig('sendinvoices.mail_format', 'html');
				$balanceBtnCode = $this->createEmailButton($mail_format, $link);
				$hook_data['body'] = preg_replace($btnPatterns, $balanceBtnCode, $hook_data['body']);
			} else {
				$hook_data['body'] = preg_replace($btnPatterns, $this->createEmailButton('txt', $link), $hook_data['body']);
			}
		}

		return $hook_data;
	}

	public function addButtonToCustomerView(array $hook_data = array())
	{
		global $LMS;
		$smarty = $hook_data['smarty'];
		$customerid = $hook_data['customerid'];

		$customerinfo = $LMS->GetCustomer($customerid);
		if ($customerinfo['balance'] < 0) {
			$smarty->assign('billtech_balance_link', self::getPaymentLink('balance', $customerid, ['utm_medium' => 'cutoffpage']));
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
			$smarty->assign('billtech_balance_link', self::getPaymentLink('balance', $customerid, ['utm_medium' => 'cutoffpage']));
			$billtech_balance_button = $smarty->fetch('button' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'customerotheripbilltechbutton.html');
		} else {
			$billtech_balance_button = '';
		}

		$custombalancedata = $smarty->getTemplateVars('custombalancedata') . $billtech_balance_button;
		$smarty->assign('custombalancedata', $custombalancedata);
	}

	public function addButtonsToFinancesView(array $hook_data = array())
	{
		global $LMS, $SESSION;
		$linksManager = $this->getLinksManager();
		$smarty = $hook_data['smarty'];
		$userinfo = $LMS->GetCustomer($SESSION->id);
		$customerId = $userinfo['id'];
		$linksManager->updateCustomerBalance($customerId);

		$style = ConfigHelper::getConfig('userpanel.style', 'default');

		if (!ConfigHelper::checkConfig('billtech.row_buttons_disabled')) {
			$balancelist = $smarty->getTemplateVars('balancelist');

			if (isset($balancelist) && isset($balancelist['list'])) {
				$paymentLinks = $linksManager->getCustomerPaymentLinks($customerId);
				$paymentLinksMap = BillTech::toMap(function ($link) {
					/* @var $link BillTechLink */
					return $link->getKey();
				}, $paymentLinks);

				foreach ($balancelist['list'] as &$item) {
					/* @var $link BillTechLink */
					$link = isset($paymentLinksMap[$item['docid']]) ? $paymentLinksMap[$item['docid']] : null;
					if (!isset($link) && isset($paymentLinksMap['cash_' . $item['id']])) {
						$link = $paymentLinksMap['cash_' . $item['id']];
					}
					if (isset($link)) {
						$customlinks = $item['customlinks'];
						if (!isset($customlinks)) {
							$customlinks = array();
						}
						$button = $this->createRowButton($smarty, $link->link, $style);
						array_push($customlinks, array(
							'extra' => $button
						));
						$item['customlinks'] = $customlinks;
					}
				}
			}
			$smarty->assign('balancelist', $balancelist);

		}

		if (!ConfigHelper::checkConfig('billtech.balance_button_disabled')) {
			$balanceLink = $linksManager->getBalanceLink($customerId, ['utm_medium' => 'userpanel'])
				? $linksManager->getBalanceLink($customerId, ['utm_medium' => 'userpanel'])->link : '';
			if($balanceLink != '') {
				$smarty->assign('billtech_balance_link', $balanceLink);
				$billtech_balance_button = $smarty->fetch('button' . DIRECTORY_SEPARATOR . $style . DIRECTORY_SEPARATOR . 'billtechbalancebutton.html');

				$smarty->assign('custom_content', $smarty->getTemplateVars('custom_content') . $billtech_balance_button);
			}
		}
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

		$nrb = iban_account('PL', 26, $doc_content['customerid'], $doc_content['account']);

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

	private function createRowButton(Smarty $smarty, $link, $style = 'default')
	{
		$smarty->assign('link', $link);
		return $smarty->fetch('button' . DIRECTORY_SEPARATOR . $style . DIRECTORY_SEPARATOR . 'billtechrowbutton.html');
	}

	function createEmailButton($mail_format, $link)
	{
		global $SMARTY;
		if ($link === null) {
			return '';
		} else if (isset($SMARTY) && isset($mail_format) && $mail_format == 'html') {
			$SMARTY->assign('link', $link);
			return $SMARTY->fetch('button/billtechemailbutton.html');
		} else {
			return 'Opłać teraz: ' . $link;
		}
	}
}