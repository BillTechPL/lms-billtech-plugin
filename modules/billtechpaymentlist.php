<?php

function GetBillTtechPaymentsList($search = NULL, $cat = NULL, $hideclosed = NULL, $order, $pagelimit = 100, $page = NULL)
{
	global $DB;

	if ($order == '')
		$order = 'id,asc';

	list($order, $direction) = sscanf($order, '%[^,],%s');
	($direction == 'desc') ? $direction = 'desc' : $direction = 'asc';

	$sqlord = '';
	switch ($order) {
		case 'id':
			$sqlord = ' ORDER BY p.id';
			break;
		case 'cdate':
			$sqlord = ' ORDER BY p.cdate';
			break;
		case 'document_number':
			$sqlord = ' ORDER BY p.document_number';
			break;
		case 'reference_number':
			$sqlord = ' ORDER BY p.reference_number';
			break;
		case 'amount':
			$sqlord = ' ORDER BY p.amount';
			break;
		case 'name':
			$sqlord = ' ORDER BY name';
			break;
	}

	$where = '';

	if ($search != '' && $cat) {
		switch ($cat) {
			case 'reference_number':
				$where = ' AND p.reference_number ?LIKE? ' . $DB->Escape('%' . $search . '%');
				break;
			case 'cdate':
				$where = ' AND p.cdate >= ' . intval($search) . ' AND cdate < ' . (intval($search) + 86400);
				break;
			case 'month':
				$last = mktime(23, 59, 59, date('n', $search) + 1, 0, date('Y', $search));
				$where = ' AND p.cdate >= ' . intval($search) . ' AND cdate <= ' . $last;
				break;
			case 'customerid':
				$where = ' AND p.customerid = ' . intval($search);
				break;
			case 'name':
				$where = ' AND UPPER(name) ?LIKE? UPPER(' . $DB->Escape('%' . $search . '%') . ')';
				break;
			case 'amount':
				$where = ' AND p.amount = ' . str_replace(',', '.', f_round($search));
				break;
		}
	}

	if ($hideclosed)
		$where .= ' AND closed = 0';

	if ($res = $DB->Exec("SELECT p.id, p.customerid as customerid, p.amount, p.title, p.document_number, p.reference_number, p.cdate, p.closed,  
				CONCAT(c.lastname, ' ', c.name) as name
			FROM billtech_payments p LEFT JOIN customers c ON c.id = p.customerid 
			LEFT JOIN (
				SELECT DISTINCT a.customerid FROM customerassignments a
				JOIN excludedgroups e ON (a.customergroupid = e.customergroupid)
				WHERE e.userid = lms_current_user()
				) e ON (e.customerid = p.customerid) 
			WHERE e.customerid IS NULL "
		. $where . $sqlord . " " . $direction)) {
		if ($page > 0) {
			$start = ($page - 1) * $pagelimit;
			$stop = $start + $pagelimit;
		}
		$id = 0;

		while ($row = $DB->FetchRow($res)) {
			$row['customlinks'] = array();
			$result[$id] = $row;
			// free memory for rows which will not be displayed
			if ($page > 0) {
				if (($id < $start || $id > $stop) && isset($result[$id]))
					$result[$id] = NULL;
			} elseif (isset($result[$id - $pagelimit]))
				$result[$id - $pagelimit] = NULL;

			$id++;
		}

		$result['page'] = $page > 0 ? $page : ceil($id / $pagelimit);
	}

	$result['order'] = $order;
	$result['direction'] = $direction;

	return $result;
}

$SESSION->save('backto', $_SERVER['QUERY_STRING']);

$SESSION->restore('bplm', $marks);
if(isset($_POST['marks']))
	foreach($_POST['marks'] as $id => $mark)
		$marks[$id] = $mark;
$SESSION->save('bplm', $marks);

if(isset($_POST['search']))
	$s = $_POST['search'];
else
	$SESSION->restore('bpls', $s);
if(!isset($s))
{
	$year=date("Y", time());
	$month=date("m", time());
	$s = $year.'/'.$month;
}
$SESSION->save('bpls', $s);

if(isset($_GET['o']))
	$o = $_GET['o'];
else
	$SESSION->restore('bplo', $o);
$SESSION->save('bplo', $o);

if(isset($_POST['cat']))
	$c = $_POST['cat'];
else
	$SESSION->restore('bplc', $c);
if (!isset($c))
{
	$c="month";
}
$SESSION->save('bplc', $c);

if (isset($_POST['search']))
	$h = isset($_POST['hideclosed']);
elseif (($h = $SESSION->get('bplh')) === NULL)
	$h = ConfigHelper::checkConfig('billtech.hide_closed_payments');
$SESSION->save('bplh', $h);

if($c == 'cdate' && $s && preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/', $s))
{
	list($year, $month, $day) = explode('/', $s);
	$s = mktime(0,0,0, $month, $day, $year);
}
elseif($c == 'month' && $s && preg_match('/^[0-9]{4}\/[0-9]{2}$/', $s))
{
	list($year, $month) = explode('/', $s);
	$s = mktime(0,0,0, $month, 1, $year);
}

$pagelimit = ConfigHelper::getConfig('phpui.billtechpaymentlist_pagelimit', 100);
$page = !isset($_GET['page']) ? 0 : intval($_GET['page']);

$paymentlist = GetBillTtechPaymentsList($s, $c, $h, $o, $pagelimit, $page);

$SESSION->restore('bplc', $listdata['cat']);
$SESSION->restore('bpls', $listdata['search']);
$SESSION->restore('bplh', $listdata['hideclosed']);


$listdata['order'] = $paymentlist['order'];
$listdata['direction'] = $paymentlist['direction'];
$page = $paymentlist['page'];

unset($paymentlist['page']);
unset($paymentlist['order']);
unset($paymentlist['direction']);

$listdata['total'] = sizeof($paymentlist);

$hook_data = $LMS->ExecuteHook('billtechpaymentlist_before_display',
	array(
		'paymentlist' => $paymentlist,
	)
);
$paymentlist = $hook_data['paymentlist'];

$SMARTY->assign('listdata', $listdata);
$SMARTY->assign('pagelimit', $pagelimit);
$SMARTY->assign('start', ($page - 1) * $pagelimit);
$SMARTY->assign('page', $page);
$SMARTY->assign('marks', $marks);
$SMARTY->assign('paymentlist', $paymentlist);
$SMARTY->display('billtechpaymentlist.html');
