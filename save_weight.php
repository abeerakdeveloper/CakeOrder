<?php
require_once 'db.php';
requireRole(array(1, 3));

// Sweet boxes are priced after weighing. This stores the weighed amount
// as one row (sale_type = 'weighed') so the bill total and balance include it.
// It can be changed until any payment has been taken.

$input = json_decode(file_get_contents('php://input'), true);
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;
$amount = isset($input['amount']) ? intval(round(floatval($input['amount']))) : 0;

if (!$billNo || $amount <= 0) {
    jsonResponse(array('success' => false, 'message' => 'Enter the weighed amount in Rs.'));
}

$res = mysqli_query($mysqli, "SELECT MAX(paid) AS paid, MAX(status) AS status,
    SUM(CASE WHEN sale_type = 'sweet' THEN 1 ELSE 0 END) AS sweet_rows,
    SUM(CASE WHEN sale_type = 'weighed' THEN 1 ELSE 0 END) AS weighed_rows
    FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 GROUP BY bill_no");
$o = $res ? mysqli_fetch_assoc($res) : null;

if (!$o) {
    jsonResponse(array('success' => false, 'message' => 'Order not found'));
}
if (intval($o['sweet_rows']) === 0) {
    jsonResponse(array('success' => false, 'message' => 'This order has no sweet boxes to weigh.'));
}
if (floatval($o['paid']) > 0 || in_array($o['status'], array('paid', 'cancelled'))) {
    jsonResponse(array('success' => false, 'message' => 'Payment has started. The weighed amount cannot be changed.'));
}

mysqli_autocommit($mysqli, false);

try {
    if (intval($o['weighed_rows']) > 0) {
        $sql = "UPDATE cake_order SET amount = $amount, retail_price = $amount
                WHERE bill_no = $billNo AND sale_type = 'weighed' AND ordercancel = 0";
    } else {
        // Copy the header fields from the first row of the bill, so every row keeps the same header values.
        $sql = "INSERT INTO cake_order
            (bill_no, inv_date, return_date, deliver_date, delivery_time,
             amount, advance, paid, cell_no, party_detail, notes,
             user, dateent, order_taker, order_factory, ordercancel,
             status, off_bill_no, off_dateent, flat_disc, pay_date,
             order_type, inv_id, qty, ext_pay, flavor, flavor_amt,
             priority, category, tiers, shape, cake_message, retail_price,
             image_data, thumb_data, audio_data, uom, payment_method,
             sale_type, box_group, box_qty, kitchen_note, material, delivery_branch)
            SELECT bill_no, inv_date, NOW(), deliver_date, delivery_time,
             $amount, advance, paid, cell_no, party_detail, '',
             user, NOW(), order_taker, order_factory, 0,
             status, 0, NOW(), flat_disc, pay_date,
             order_type, 0, 1, 0, '', 0,
             priority, 'Sweet boxes (after weight)', 1, '', '', $amount,
             NULL, NULL, NULL, 'pcs', payment_method,
             'weighed', NULL, NULL, '', '', delivery_branch
            FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0
            ORDER BY id LIMIT 1";
    }

    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Could not save the weighed amount: ' . mysqli_error($mysqli));
    }

    mysqli_commit($mysqli);
    jsonResponse(array('success' => true, 'bill_no' => $billNo, 'weighed' => $amount));
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}
