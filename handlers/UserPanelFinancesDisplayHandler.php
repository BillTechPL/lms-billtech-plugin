<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class UserPanelFinancesDisplayHandler
{
    public function create_payment_links(Smarty $smarty, $userId, $isp_id, $style)
    {
        global $LMS;
		$balance = $LMS->GetCustomerBalanceList($userId);
        $billtech_links = array();

        if (isset($balance['docid'])) {
            foreach ($balance['docid'] as $idx => $val) {
                if ($balance['doctype'][$idx] == 1) {
                    if ($number = $LMS->docnumber($val))
                        $balance['number'][$idx] = trans('Invoice No. $a', $number);
                }
            }
        }

        foreach ($balance['list'] as $idx => $val) {
            $link = BillTechLinkGenerator::create_payment_link($val['docid'], $userId, $isp_id);
            $smarty->assign('link', $link);
            $billtech_row_button = $smarty->fetch($style . DIRECTORY_SEPARATOR . 'billtechrowbutton.html');

            array_push($billtech_links, $billtech_row_button);
        }

        return $billtech_links;
    }

    public function handle_add_billtech_buttons(array $hook_data = array())
    {
        global $SESSION;
        $isp_id = ConfigHelper::getConfig('billtech.isp_id');
        $smarty = $hook_data['smarty'];
        $style = ConfigHelper::getConfig('userpanel.style', 'default');
        $smarty->assign('billtech_balance_link', BillTechLinkGenerator::create_payment_link('balance', $SESSION->id, $isp_id));
        $billtech_balance_button = $smarty->fetch($style . DIRECTORY_SEPARATOR . 'billtechbalancebutton.html');
        $smarty->assign('billtech_balance_button', $billtech_balance_button);
        $smarty->assign('billtech_row_buttons', $this->create_payment_links($smarty, $SESSION->id, $isp_id, $style));
        $smarty->assign('billtech_enabled', true);
        return $hook_data;
    }
}