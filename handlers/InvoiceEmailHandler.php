<?php

/*
 *  LMS version 1.11-git
 *
 *  Copyright (C) 2001-2017 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */
class InvoiceEmailHandler
{
    public function billtech_process_email_body(array $hook_data = array())
    {
		$ispId = ConfigHelper::getConfig('billtech.isp_id');
		$link = BillTechLinkGenerator::create_payment_link($hook_data['doc']['id'], $hook_data['doc']['customerid'], $ispId);
        $hook_data['body'] = preg_replace('/%billtech_btn/',
            $this->get_email_button($hook_data['mail_format'], $this->get_email_button($hook_data['mail_format'], $link)),
            $hook_data['body']);

        $hook_data['headers'] = $this->fill_headers($hook_data['doc'], $hook_data['headers']);

        return $hook_data;
    }

    function fill_headers($doc, $headers)
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

    function get_email_button($mail_format, $link)
    {
        if (isset($mail_format) && $mail_format == 'html') {
            global $SMARTY;

            $SMARTY->assign('link', $link);
            return $SMARTY->fetch('billtechemailbutton.html');
        } else {
            return 'Opłać teraz: ' . $link;
        }
    }
}
