<?php
require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'search';

if ($action == 'search') {
    // Auto-suggest customer by phone or name
    $q = isset($_GET['q']) ? esc(trim($_GET['q'])) : '';
    if (strlen($q) < 2) {
        jsonResponse(array('customers' => array()));
    }
    
    $sql = "SELECT party_detail, cell_no, 
        COUNT(DISTINCT bill_no) AS total_orders,
        SUM(amount - flat_disc) AS total_spent,
        MAX(deliver_date) AS last_order_date,
        GROUP_CONCAT(DISTINCT flavor SEPARATOR ', ') AS fav_flavors
        FROM cake_order 
        WHERE ordercancel = 0 
        AND (cell_no LIKE '%$q%' OR party_detail LIKE '%$q%')
        GROUP BY cell_no, party_detail
        ORDER BY total_orders DESC, last_order_date DESC
        LIMIT 10";
    
    $res = mysqli_query($mysqli, $sql);
    $customers = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $customers[] = array(
                'name' => $r['party_detail'],
                'cell' => $r['cell_no'],
                'orders' => intval($r['total_orders']),
                'spent' => floatval($r['total_spent']),
                'last_order' => $r['last_order_date'] ? date('d M Y', strtotime($r['last_order_date'])) : '',
                'fav_flavors' => $r['fav_flavors'],
                'is_vip' => intval($r['total_orders']) >= 5,
                'is_loyal' => floatval($r['total_spent']) >= 10000
            );
        }
    }
    jsonResponse(array('customers' => $customers));
}

else if ($action == 'history') {
    // Get full order history of a customer
    $cell = isset($_GET['cell']) ? esc($_GET['cell']) : '';
    if (empty($cell)) jsonResponse(array('orders' => array()));
    
    $sql = "SELECT bill_no, party_detail, deliver_date, 
        SUM(amount) AS total, MAX(status) AS status,
        GROUP_CONCAT(category SEPARATOR ', ') AS items,
        GROUP_CONCAT(DISTINCT flavor SEPARATOR ', ') AS flavors
        FROM cake_order 
        WHERE cell_no = '$cell' AND ordercancel = 0
        GROUP BY bill_no
        ORDER BY bill_no DESC
        LIMIT 10";
    
    $res = mysqli_query($mysqli, $sql);
    $orders = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $orders[] = array(
                'bill_no' => $r['bill_no'],
                'date' => date('d M Y', strtotime($r['deliver_date'])),
                'total' => number_format($r['total']),
                'status' => $r['status'],
                'status_badge' => getStatusBadge($r['status']),
                'items' => $r['items'],
                'flavors' => $r['flavors']
            );
        }
    }
    jsonResponse(array('orders' => $orders));
}

else if ($action == 'duplicate') {
    // Duplicate a previous order's items for re-ordering
    $billNo = isset($_GET['bill_no']) ? intval($_GET['bill_no']) : 0;
    if (!$billNo) jsonResponse(array('items' => array()));
    
    $sql = "SELECT inv_id, category, retail_price, qty, flavor, shape, tiers, uom, cake_message, notes
            FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0";
    
    $res = mysqli_query($mysqli, $sql);
    $items = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $items[] = array(
                'inv_id' => intval($r['inv_id']),
                'name' => $r['category'],
                'price' => floatval($r['retail_price']),
                'qty' => intval($r['qty']),
                'flavor' => $r['flavor'],
                'shape' => $r['shape'],
                'tiers' => intval($r['tiers']),
                'uom' => $r['uom'],
                'cake_message' => $r['cake_message'],
                'note' => $r['notes']
            );
        }
    }
    jsonResponse(array('items' => $items));
}

else if ($action == 'upcoming_occasions') {
    // Birthday/Anniversary alerts - orders with occasion keywords in next 30 days
    $today = date('Y-m-d');
    $future = date('Y-m-d', strtotime('+30 days'));
    
    $sql = "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time, notes
            FROM cake_order 
            WHERE deliver_date BETWEEN '$today' AND '$future'
            AND ordercancel = 0
            AND (notes LIKE '%BIRTHDAY%' OR notes LIKE '%ANNIVERSARY%' OR notes LIKE '%WEDDING%' OR notes LIKE '%OCCASION%')
            GROUP BY bill_no
            ORDER BY deliver_date ASC LIMIT 20";
    
    $res = mysqli_query($mysqli, $sql);
    $occasions = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $daysAway = floor((strtotime($r['deliver_date']) - strtotime($today)) / 86400);
            $occasions[] = array(
                'bill_no' => $r['bill_no'],
                'customer' => $r['party_detail'],
                'cell' => $r['cell_no'],
                'date' => date('d M', strtotime($r['deliver_date'])),
                'days_away' => $daysAway,
                'notes' => $r['notes']
            );
        }
    }
    jsonResponse(array('occasions' => $occasions));
}

jsonResponse(array('error' => 'Invalid action'));
?>