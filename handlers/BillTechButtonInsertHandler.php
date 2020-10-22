<?php

/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechButtonInsertHandler
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
			return $linksManager->getBalanceLink($customerId, $params)->link;
		} else {
			$cashId = $DB->GetOne("select id from cash where docid = ?", array($doc));
			return $linksManager->getCashLink($cashId, $params)->link;
		}
	}

	public function addButtonToInvoiceEmail(array $hook_data = array())
	{
		global $DB;
		$linksManager = $this->getLinksManager();
		$linksManager->updateCustomerBalance($hook_data['doc']['customerid']);
		$cashId = $DB->GetOne("select id from cash where docid = ?;", array($hook_data['doc']['id']));
		$cashLink = $this->getLinksManager()->getCashLink($cashId, ['utm_medium' => 'email'])->link;
		$balanceLink = $this->getLinksManager()->getBalanceLink($hook_data['doc']['customerid'], ['utm_medium' => 'email'])->link;
		$cashBtnCode = $this->getBtnCode($hook_data['mail_format'], $cashLink);
		$balanceBtnCode = $this->getBtnCode($hook_data['mail_format'], $balanceLink);

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
		global $LMS;
		$linksManager = $this->getLinksManager();
		$smarty = $hook_data['smarty'];
		$userinfo = $hook_data['userinfo'] ? $hook_data['userinfo'] : $LMS->GetCustomer($SESSION->id);
		$customerId = $userinfo['id'];
		$linksManager->updateCustomerBalance($customerId);

		if (!BillTech::checkConfig('billtech.row_buttons_disabled')) {
			$balancelist = $hook_data['balancelist'] ? $hook_data['balancelist'] : $hook_data['smarty']->getTemplateVars('balancelist');

			if (isset($balancelist)) {
				$paymentLinks = $linksManager->getCustomerPaymentLinks($customerId);
				$paymentLinksMap = BillTech::toMap(function ($link) {
					/* @var $link BillTechLink */
					return $link->docid;
				}, $paymentLinks);

				$invoices = $smarty->get_template_vars('invoices');
				foreach ($invoices as &$invoice) {
					$link = $paymentLinksMap[$invoice['id']];
					$button = isset($link) ? $this->createRowButton($link->link) : "";
					$invoice['billtech_btn'] = $button;
				}
				$smarty->assign('invoices', $invoices);
			}
			$smarty->assign('balancelist', $balancelist);

		}

		if (!BillTech::checkConfig('billtech.balance_button_disabled')) {
			$balanceLink = $linksManager->getBalanceLink($customerId, ['utm_medium' => 'userpanel'])->link;
			$smarty->assign('custom_content', $smarty->get_template_vars('custom_content') . $this->createBalanceButton($balanceLink));
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

        $nrb = bankaccount($doc_content['customerid'], $doc_content['account']);

        if ($nrb == "" && !empty($doc_content['bankaccounts'])) {
            $nrb = $doc_content['bankaccounts'][0];
        }

        $headers['X-BillTech-ispId'] = BillTech::getConfig('billtech.isp_id');
        $headers['X-BillTech-customerId'] = $doc_content['customerid'];
        $headers['X-BillTech-invoiceNumber'] = $document_number;
        $headers['X-BillTech-nrb'] = $nrb;
        $headers['X-BillTech-amount'] = $doc_content['total'];
        $headers['X-BillTech-paymentDue'] = $doc_content['pdate'];

        return $headers;
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

    /**
     * @param $mail_format
     * @param $cashLink
     * @return string|string[]|null
     */
    private function getBtnCode($mail_format, $cashLink)
    {
        $btnCode = $this->createEmailButton($mail_format, $cashLink);
        $btnCode = preg_replace('/\r?\n/', ' ', $btnCode);
        return $btnCode;
    }

    /**
     * @param $balancelist1
     * @param $smarty
     * @return mixed
     */
    private function getBalance($hook_data)
    {
        $balancelist = $hook_data['balancelist'] ? $hook_data['balancelist'] : $hook_data['smarty']->getTemplateVars('balancelist');
        if (!isset($balancelist['list']) && isset($balancelist['id'])) {
            $balancelist['list'] = array();
            foreach ($balancelist['id'] as $idx => $id) {
                array_push($balancelist['list'], array(
                    'id' => $id,
                    'customlinks' => array()
                ));
            }
        }
        return $balancelist;
    }


    function createBalanceButton($link)
    {
        return "<style>
					.billtech_balance_button {
						text-decoration: none;
						font-family: Roboto, \"Helvetica Neue\", Helvetica, Arial, sans-serif;
					}
				
					.billtech_balance_button div {
						padding: 10px 15px;
						color: #fff;
						border-width: 0;
						margin: 8px 3px 3px 3px;
						background-color: #FF9F32;
						border-radius: 3px;
					}
				
					.billtech_balance_button:hover {
						text-decoration: none;
					}
				
					.billtech_balance_button:hover div {
						background-color: #ff9319;
					}
				</style>
				<div style=\"width:100%; text-align:center\">
					<a class=\"billtech_balance_button\" style=\"cursor: pointer;\" target=\"_blank\" href=\"{$link}\">
						<div>
							Opłać teraz
						</div>
					</a>
				</div>";
    }

    function createRowButton($link)
    {
        return "<style>
					.billtech_row_button {
						font-family: Roboto, \"Helvetica Neue\", Helvetica, Arial, sans-serif;
					}
				
					.billtech_row_button div {
						padding: 2px 5px;
						color: #fff;
						border-width: 0;
						margin: 0;
						background-color: #FF9F32;
						border-radius: 3px;
						cursor: pointer;
					}
				
					.billtech_row_button:hover {
						text-decoration: none;
					}
				
					.billtech_row_button:hover div{
						background-color: #ff9319;
					}
				</style>
				<div style=\"width:100%; text-align:center\">
					<a class=\"billtech_row_button\"
					   href=\"{$link}\"
					   target=\"_blank\">
						<div>
							Opłać teraz
						</div>
					</a>
				</div>";
    }
}