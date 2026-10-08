<?php
require_once 'db.php';
requireRole(array(1, 3)); // POS user and Admin only

$input = json_decode(file_get_contents('php://input'), true);
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;
$amount = isset($input['amount']) ? intval($input['amount']) : 0;
$method = isset($input['method']) ? esc($input['method']) : '';
$reference = isset($input['reference']) ? esc($input['reference']) : '';

if (!$billNo || !$amount || !$method) {
    jsonResponse(array('success' => false, 'message' => 'Missing required fields'));
}

// Get order details
$res = mysqli_query($mysqli, "SELECT SUM(amount) AS total, MAX(advance) AS advance, 
    MAX(paid) AS paid, MAX(flat_disc) AS disc, MAX(status) AS status, MAX(ordercancel) AS cancelled,
    MAX(party_detail) AS party_detail
    FROM cake_order WHERE bill_no = $billNo GROUP BY bill_no");

$order = mysqli_fetch_assoc($res);
if (!$order) jsonResponse(array('success' => false, 'message' => 'Order not found'));
if ($order['cancelled']) jsonResponse(array('success' => false, 'message' => 'Order is cancelled'));

// Check status - allow advance only for active orders
if (in_array($order['status'], array('paid', 'cancelled'))) {
    jsonResponse(array('success' => false, 'message' => 'Cannot add advance to ' . $order['status'] . ' order'));
}

$totalDue = $order['total'] - $order['disc'];
$alreadyPaid = $order['advance'] + $order['paid'];
$balance = $totalDue - $alreadyPaid;

if ($amount > $balance) {
    jsonResponse(array('success' => false, 'message' => 'Amount exceeds balance Rs. ' . number_format($balance)));
}

// v_type = CR (Cash Receipt) or BR (Bank Receipt)
// amt_type = CR (Payment Received)
$vType = ($method == 'cash') ? 'CR' : 'BR';
$amtType = 'CR'; // Payment Received
$vnoFunc = ($method == 'cash') ? 'CR' : 'BR';

$newAdvance = $order['advance'] + $amount;
$payDate = date('Y-m-d');
$user = esc($_SESSION['user']);
$partyDetail = esc($order['party_detail']);

mysqli_autocommit($mysqli, false);

try {
    // 1. Update cake_order: increase advance
    $sql = "UPDATE cake_order SET 
        advance = $newAdvance,
        notes = CONCAT(IFNULL(notes,''), ' | +ADVANCE: $method Rs.$amount " . ($reference ? "Ref:$reference" : "") . " by $user'),
        return_date = NOW()
        WHERE bill_no = $billNo AND ordercancel = 0";

    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Update failed: ' . mysqli_error($mysqli));
    }
    
    // 2. Get voucher number from retvno()
    $vno = 0;
    $vRes = mysqli_query($mysqli, "SELECT retvchno('$vnoFunc') AS vno");
    if (!$vRes) {
    throw new Exception('Voucher query failed: ' . mysqli_error($mysqli));
}
    if ($vRes) {
        $vRow = mysqli_fetch_assoc($vRes);
        $vno = intval($vRow['vno']);
    }
    // echo 'vno'.$vno;
    if(!$vno){
        jsonResponse(array('success' => false, 'message' => 'Missing required fields '.$vRes));

    }
    // exit();
    
    $descText = "ADDITIONAL ADVANCE - Cake Order #$billNo - $partyDetail" . ($reference ? " Ref:$reference" : "");
    
    // 3. Insert GL entry
    /*
	$glSql = "INSERT INTO gledg 
        (vno, acc_code, date, amt_type, amount, `desc`, ref_no, bill_no, user, dateent) 
        VALUES 
        ($vno, '112000001', '$payDate', '$amtType', $amount, 
         '$descText', $billNo, $billNo, '$user', NOW())";
    */

	// $glSql = "INSERT INTO gledg 
    //     (vno, v_type, acc_code, date, amt_type, amount, `desc`, ref_no, bill_no, user, dateent) 
    //     VALUES 
    //     ($vno, '$vType', '112000001', '$payDate', '$amtType', $amount, 
    //      '$descText', $billNo, $billNo, '$user', NOW())";

	$glSql = "INSERT INTO gledg 
        (vno, v_type, acc_code,ref_acc_code, date, amt_type, amount, `desc`, ref_no, user, dateent) 
        VALUES 
        ($vno, '$vType', '112000001','110000001' ,'$payDate', '$amtType', $amount, 
         '$descText', $billNo, '$user', NOW())";

	// $glSqlDR = "INSERT INTO gledg 
    //     (vno, v_type, acc_code, ref_acc_code,date, amt_type, amount, `desc`, ref_no, user, dateent) 
    //     VALUES 
    //     ($vno, '$vType','110000001' , '112000001', '$payDate', 'DR', $amount, 
    //      '$descText', $billNo, '$user', NOW())";

    if (!mysqli_query($mysqli, $glSql)) {
        throw new Exception('GL Entry failed: ' . mysqli_error($mysqli));
    }
    // if (!mysqli_query($mysqli, $glSqlDR)) {
    //     throw new Exception('GL Entry failed: ' . mysqli_error($mysqli));
    // }
    
    mysqli_commit($mysqli);
    
    jsonResponse(array(
        'success' => true,
        'bill_no' => $billNo,
        'vno' => $vno,
        'new_advance' => $newAdvance,
        'amt_type' => $amtType,
        'message' => 'Advance added successfully'.$vno
    ));
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, true);
?>