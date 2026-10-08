<?php
require_once 'db.php';
requireRole(array(3)); // Admin only

$input = json_decode(file_get_contents('php://input'), true);
$paymentId = isset($input['id']) ? intval($input['id']) : 0;
$reason = isset($input['reason']) ? esc($input['reason']) : '';

if (!$paymentId) {
    jsonResponse(array('success' => false, 'message' => 'Missing payment ID'));
}

// Get payment details
$res = mysqli_query($mysqli, "SELECT * FROM gledg WHERE id = $paymentId LIMIT 1");
$payment = mysqli_fetch_assoc($res);

if (!$payment) {
    jsonResponse(array('success' => false, 'message' => 'Payment not found'));
}

$user = esc($_SESSION['user']);
$billNo = intval($payment['bill_no']);
$amount = floatval($payment['amount']);

mysqli_autocommit($mysqli, false);
try {
    // 1. Delete from gledg
    if (!mysqli_query($mysqli, "DELETE FROM gledg WHERE id = $paymentId")) {
        throw new Exception('Delete failed: ' . mysqli_error($mysqli));
    }
    
    // 2. Update cake_order: reduce paid amount
    if ($billNo > 0 && $amount > 0) {
        $sql = "UPDATE cake_order SET 
            paid = GREATEST(0, paid - $amount),
            notes = CONCAT(IFNULL(notes,''), ' | PAYMENT DELETED by admin $user (Rs.$amount): $reason'),
            status = CASE 
                WHEN status = 'paid' THEN 'delivered'
                ELSE status
            END
            WHERE bill_no = $billNo";
        
        if (!mysqli_query($mysqli, $sql)) {
            throw new Exception('Order update failed: ' . mysqli_error($mysqli));
        }
    }
    
   /*
	// 3. Log the admin action
    $logSql = "INSERT INTO gledg (acc_code, date, amt_type, amount, description, bill_no, user, dateent)
        VALUES ('112000001', CURDATE(), 'ADJ', 0, 
        'ADMIN DELETED Payment ID#$paymentId (Rs.$amount): $reason', $billNo, '$user', NOW())";
    mysqli_query($mysqli, $logSql);
  */  
    mysqli_commit($mysqli);
    jsonResponse(array('success' => true, 'message' => 'Payment deleted and order updated'));
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, true);
?>