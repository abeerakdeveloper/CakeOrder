<?php
require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;
$amount = isset($input['amount']) ? intval($input['amount']) : 0;
$method = isset($input['method']) ? esc($input['method']) : '';
$reference = isset($input['reference']) ? esc($input['reference']) : '';

if (!$billNo || !$amount || !$method) {
    jsonResponse(array('success' => false, 'message' => 'Missing required fields'));
}

$res = mysqli_query($mysqli, "SELECT SUM(amount) AS total, MAX(advance) AS advance, 
    MAX(paid) AS paid, MAX(flat_disc) AS disc, MAX(status) AS status, MAX(ordercancel) AS cancelled,
    MAX(party_detail) AS party_detail,
    MAX(CASE WHEN sale_type = 'sweet' THEN 1 ELSE 0 END) AS has_sweet,
    MAX(CASE WHEN sale_type = 'weighed' THEN 1 ELSE 0 END) AS has_weighed
    FROM cake_order WHERE bill_no = $billNo GROUP BY bill_no");

$order = mysqli_fetch_assoc($res);
if (!$order) jsonResponse(array('success' => false, 'message' => 'Order not found'));
if ($order['cancelled']) jsonResponse(array('success' => false, 'message' => 'Order cancelled'));
// Sweet boxes are priced after weighing: no payment until the weighed amount is entered
if ($order['has_sweet'] && !$order['has_weighed']) jsonResponse(array('success' => false, 'message' => 'Weigh the sweet boxes first and enter the amount on the Payment page.'));

$totalDue = $order['total'] - $order['disc'];
$alreadyPaid = $order['advance'] + $order['paid'];
$balance = $totalDue - $alreadyPaid;

if ($amount > $balance) {
    jsonResponse(array('success' => false, 'message' => 'Amount exceeds balance Rs. ' . number_format($balance)));
}

// Determine amt_type for GL
// CR = Cash Receipt, BR = Bank Receipt
$amtType = ($method == 'cash') ? 'CR' : 'BR';
$vnoFunc = ($method == 'cash') ? 'CR' : 'BR';

$newPaid = $order['paid'] + $amount;
$newBalance = $balance - $amount;
// Fully paid: paid. Part paid: an order still in the kitchen keeps its status, so it stays on the
// kitchen board. A ready order becomes delivered, as before.
if ($newBalance <= 0) {
    $newStatus = 'paid';
} elseif (in_array($order['status'], array('confirmed', 'preparing', 'pending', 'hold'))) {
    $newStatus = $order['status'];
} else {
    $newStatus = 'delivered';
}
$payDate = date('Y-m-d');
$user = esc($_SESSION['user']);
$partyDetail = esc($order['party_detail']);

mysqli_autocommit($mysqli, false);

try {
    // 1. Update cake_order
    $sql = "UPDATE cake_order SET 
        paid = $newPaid, 
        status = '$newStatus', 
        payment_method = '$method',
        pay_date = '$payDate',
        return_date = NOW(),
        notes = CONCAT(IFNULL(notes,''), ' | PAY: $method Rs.$amount " . ($reference ? "Ref:$reference" : "") . " by $user')
        WHERE bill_no = $billNo AND ordercancel = 0";

    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Update failed: ' . mysqli_error($mysqli));
    }
    
    // ============================================
    // 2. Insert GL entry (gledg table)
    // vno from retvno('CR') for cash, retvno('BR') for bank
    // ============================================
    $vno = 0;
    $vRes = mysqli_query($mysqli, "SELECT retvno('$vnoFunc') AS vno");
    if ($vRes) {
        $vRow = mysqli_fetch_assoc($vRes);
        $vno = intval($vRow['vno']);
    }
    
    $descText = "Cake Order #$billNo - $partyDetail" . ($reference ? " Ref:$reference" : "");
    
    $glSql = "INSERT INTO gledg 
        (amt_type,vno, acc_code, date, v_type, amount, `desc`, ref_no,  user, dateent) 
        VALUES 
        ('CR',$vno, '112000001', '$payDate', '$amtType', $amount, 
         '$descText', $billNo, '$user', NOW())";
    
    if (!mysqli_query($mysqli, $glSql)) {
        throw new Exception('GL Entry failed: ' . mysqli_error($mysqli));
    }
    
    mysqli_commit($mysqli);
    
    jsonResponse(array(
        'success' => true,
        'bill_no' => $billNo,
        'paid' => $newPaid,
        'balance' => max(0, $newBalance),
        'status' => $newStatus,
        'gl_recorded' => true,
        'vno' => $vno,
        'amt_type' => $amtType
    ));
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, true);
?>