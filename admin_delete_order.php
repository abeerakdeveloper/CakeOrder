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
$origType = isset($payment['amt_type']) ? $payment['amt_type'] : 'CR';

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
    // ============================================
    // 3. Log the admin action as reversal entry
    // Original CR -> CP (cash payment/reversal) using retvno('CP')
    // Original BR -> BP (bank payment/reversal) using retvno('BP')
    // ============================================
    if ($origType == 'CR') {
        $reverseType = 'CP';
        $vnoFunc = 'CP';
    } else if ($origType == 'BR') {
        $reverseType = 'BP';
        $vnoFunc = 'BP';
    } else {
        $reverseType = 'CP';
        $vnoFunc = 'CP';
    }
    
    // Get voucher number
    $logVno = 0;
    $vRes = mysqli_query($mysqli, "SELECT retvno('$vnoFunc') AS vno");
    if ($vRes) {
        $vRow = mysqli_fetch_assoc($vRes);
        $logVno = intval($vRow['vno']);
    }
    
    $descText = "ADMIN DELETED Payment ID#$paymentId (Rs.$amount) by $user: $reason";
    
    $logSql = "INSERT INTO gledg 
        (vno, acc_code, date, amt_type, amount, `desc`, ref_no,  user, dateent)
        VALUES 
        ($logVno, '112000001', CURDATE(), '$reverseType', $amount, 
         '$descText', $billNo, '$user', NOW())";
    //21-05-2026 mysqli_query($mysqli, $logSql);
*/

	    // ============================================
    // 3. Log the admin action as reversal entry
    // Original CR (Cash Receipt) -> CP (Cash Payment) using retvno('CP')
    // Original BR (Bank Receipt) -> BP (Bank Payment) using retvno('BP')
    // amt_type = DR (Refund/Outgoing)
    // ============================================
    if ($origVType == 'CR') {
        $reverseVType = 'CP';
        $vnoFunc = 'CP';
    } else if ($origVType == 'BR') {
        $reverseVType = 'BP';
        $vnoFunc = 'BP';
    } else {
        $reverseVType = 'CP';
        $vnoFunc = 'CP';
    }
    
    $amtType = 'DR'; // Payment Refund
    
    // Get voucher number
    $logVno = 0;
    $vRes = mysqli_query($mysqli, "SELECT retvno('$vnoFunc') AS vno");
    if ($vRes) {
        $vRow = mysqli_fetch_assoc($vRes);
        $logVno = intval($vRow['vno']);
    }
    
    $descText = "ADMIN DELETED Payment ID#$paymentId (Rs.$amount) by $user: $reason";
    
    $logSql = "INSERT INTO gledg 
        (vno, v_type, acc_code, date, amt_type, amount, `desc`, ref_no, bill_no, user, dateent)
        VALUES 
        ($logVno, '$reverseVType', '112000001', CURDATE(), '$amtType', $amount, 
         '$descText', $billNo, $billNo, '$user', NOW())";
    mysqli_query($mysqli, $logSql);

    mysqli_commit($mysqli);
    jsonResponse(array('success' => true, 'message' => 'Payment deleted and order updated'));
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, true);
?>