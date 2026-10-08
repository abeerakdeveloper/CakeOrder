<?php
require_once 'db.php';

$q = isset($_GET['q']) ? esc(trim($_GET['q'])) : '';

if (empty($q)) {
    jsonResponse(array('found' => false, 'orders' => array()));
}

$sql = "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time,
    SUM(amount) AS total, MAX(advance) AS advance, MAX(paid) AS paid, 
    MAX(flat_disc) AS disc, MAX(status) AS status,
    GROUP_CONCAT(category SEPARATOR ', ') AS items
    FROM cake_order 
    WHERE ordercancel = 0 
    AND (bill_no LIKE '%$q%' OR party_detail LIKE '%$q%' OR cell_no LIKE '%$q%')
    GROUP BY bill_no
    ORDER BY bill_no DESC
    LIMIT 10";

$res = mysqli_query($mysqli, $sql);
$orders = array();

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $totalNet = $r['total'] - $r['disc'];
        $balance = $totalNet - $r['advance'] - $r['paid'];
        $orders[] = array(
            'bill_no' => $r['bill_no'],
            'party_detail' => $r['party_detail'] ? $r['party_detail'] : 'Walk-in',
            'cell_no' => $r['cell_no'],
            'deliver_date' => date('d M Y', strtotime($r['deliver_date'])),
            'delivery_time' => $r['delivery_time'],
            'items' => $r['items'],
            'total' => number_format($totalNet),
            'paid' => number_format($r['advance'] + $r['paid']),
            'balance' => number_format(max(0, $balance)),
            'status' => $r['status'],
            'status_badge' => getStatusBadge($r['status'])
        );
    }
}

jsonResponse(array('found' => !empty($orders), 'orders' => $orders));
?>