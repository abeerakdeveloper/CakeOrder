<?php
require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;
$reason = isset($input['reason']) ? esc($input['reason']) : 'No reason';

if (!$billNo) {
    jsonResponse(array('success' => false, 'message' => 'Missing bill number'));
}

$res = mysqli_query($mysqli, "SELECT status, paid, advance FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 LIMIT 1");
$order = mysqli_fetch_assoc($res);

if (!$order) {
    jsonResponse(array('success' => false, 'message' => 'Order not found'));
}

$role = getUserRole();

// BUSINESS RULES per role
if (!isAdmin()) {
    // ADMIN can cancel anytime, even after payment
    // Others (POS user, kitchen):
    
    // Cannot cancel if payment received
    if ($order['paid'] > 0) {
        jsonResponse(array(
            'success' => false, 
            'message' => '❌ Cannot cancel: Payment of Rs. ' . number_format($order['paid']) . ' already received. Contact ADMIN for cancellation.'
        ));
    }
    
    // Cannot cancel if order is READY for delivery
    if ($order['status'] === 'ready') {
        jsonResponse(array(
            'success' => false, 
            'message' => '❌ Cannot cancel: Order is ready for delivery. Contact ADMIN.'
        ));
    }
    
    if (in_array($order['status'], array('delivered', 'paid'))) {
        jsonResponse(array(
            'success' => false, 
            'message' => '❌ Cannot cancel: Order already ' . $order['status'] . '. Contact ADMIN.'
        ));
    }
}

$user = esc($_SESSION['user']);

mysqli_autocommit($mysqli, false);
try {
    // Cancel the order
    $sql = "UPDATE cake_order SET 
        ordercancel = 1, 
        status = 'cancelled',
        notes = CONCAT(IFNULL(notes,''), ' | CANCELLED by $user: $reason'),
        return_date = NOW()
        WHERE bill_no = $billNo AND ordercancel = 0";

    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception(mysqli_error($mysqli));
    }
/*    
    // ============================================
    // If admin and payment was made, create refund GL entry
    // ============================================
    $refundProcessed = false;
    if (isAdmin() && ($order['paid'] > 0 || $order['advance'] > 0)) {
        $refundAmt = floatval($order['paid']) + floatval($order['advance']);
        
        // Get original payment type from gledg to determine refund type
        $pmRes = mysqli_query($mysqli, "SELECT amt_type FROM gledg WHERE ref_no = $billNo AND amt_type IN ('CR','BR') ORDER BY id DESC LIMIT 1");
        $pmRow = mysqli_fetch_assoc($pmRes);
        $origType = ($pmRow ? $pmRow['amt_type'] : 'CR');
        
        // Refund logic:
        // Original CR (cash receipt) -> Refund CP (cash payment) using retvchno('CP')
        // Original BR (bank receipt) -> Refund BP (bank payment) using retvchno('BP')
        if ($origType == 'CR') {
            $refundType = 'CP';
            $vnoFunc = 'CP';
        } else {
            $refundType = 'BP';
            $vnoFunc = 'BP';
        }
        
        // Get new voucher number for refund
        $vno = 0;
        $vRes = mysqli_query($mysqli, "SELECT retvchno('$vnoFunc') AS vno");
        if ($vRes) {
            $vRow = mysqli_fetch_assoc($vRes);
            $vno = intval($vRow['vno']);
        }
        
        $descText = "REFUND - Cancelled Order #$billNo by $user: $reason";
        
        $refundSql = "INSERT INTO gledg 
            (amt_type,vno, acc_code, date, v_type, amount, `desc`, ref_no,  user, dateent) 
            VALUES 
            ('DR',$vno, '112000001', CURDATE(), '$refundType', $refundAmt, 
             '$descText', $billNo, '$user', NOW())";
        
        if (!mysqli_query($mysqli, $refundSql)) {
            throw new Exception('Refund GL failed: ' . mysqli_error($mysqli));
        }
        
        $refundProcessed = true;
    }
  */
  
	    // ============================================
    // If admin and payment was made, create refund GL entry
    // ============================================
    $refundProcessed = false;
    if (isAdmin() && ($order['paid'] > 0 || $order['advance'] > 0)) {
        $refundAmt = floatval($order['paid']) + floatval($order['advance']);
        
        // Get original v_type from gledg to determine refund type
        $pmRes = mysqli_query($mysqli, "SELECT v_type FROM gledg WHERE bill_no = $billNo AND v_type IN ('CR','BR') ORDER BY id DESC LIMIT 1");
        $pmRow = mysqli_fetch_assoc($pmRes);
        $origVType = ($pmRow ? $pmRow['v_type'] : 'CR');
        
        // Refund logic:
        // Original CR (Cash Receipt) -> Refund CP (Cash Payment) using retvchno('CP')
        // Original BR (Bank Receipt) -> Refund BP (Bank Payment) using retvchno('BP')
        if ($origVType == 'CR') {
            $refundVType = 'CP';
            $vnoFunc = 'CP';
        } else {
            $refundVType = 'BP';
            $vnoFunc = 'BP';
        }
        
        // amt_type = DR (Payment Refund - outgoing)
        $amtType = 'DR';
        
        // Get new voucher number for refund
        $vno = 0;
        $vRes = mysqli_query($mysqli, "SELECT retvchno('$vnoFunc') AS vno");
        if ($vRes) {
            $vRow = mysqli_fetch_assoc($vRes);
            $vno = intval($vRow['vno']);
        }
        
        $descText = "REFUND - Cancelled Order #$billNo by $user: $reason";
        
        $refundSql = "INSERT INTO gledg 
            (vno, v_type, acc_code, date, amt_type, amount, `desc`, ref_no, user, dateent) 
            VALUES 
            ($vno, '$refundVType', '112000001', CURDATE(), '$amtType', $refundAmt, 
             '$descText', $billNo, '$user', NOW())";
        
        if (!mysqli_query($mysqli, $refundSql)) {
            throw new Exception('Refund GL failed: ' . mysqli_error($mysqli));
        }
        
        $refundProcessed = true;
    }

    mysqli_commit($mysqli);
    jsonResponse(array(
        'success' => true, 
        'message' => 'Order cancelled', 
        'bill_no' => $billNo,
        'refund_processed' => $refundProcessed
    ));
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, true);
?>