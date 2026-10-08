<?php
require_once 'db.php';
requireRole(array(1, 3));

$input = json_decode(file_get_contents('php://input'), true);
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;

if (!$billNo) {
    jsonResponse(array('success' => false, 'message' => 'Missing bill number'));
}

$partyDetail = esc($input['party_detail']);
$cellNo = esc($input['cell_no']);
$deliverDate = esc($input['deliver_date']);
$deliveryTime = esc($input['delivery_time']);
$priority = esc($input['priority']);
$newItems = isset($input['new_items']) ? $input['new_items'] : array();

$user = esc($_SESSION['user']);

// Verify order is editable
$res = mysqli_query($mysqli, "SELECT status, ordercancel FROM cake_order WHERE bill_no = $billNo LIMIT 1");
$check = mysqli_fetch_assoc($res);
if (!$check) jsonResponse(array('success' => false, 'message' => 'Order not found'));
if ($check['ordercancel']) jsonResponse(array('success' => false, 'message' => 'Order is cancelled'));
if (in_array($check['status'], array('ready','delivered','paid','cancelled'))) {
    jsonResponse(array('success' => false, 'message' => 'Cannot edit ' . $check['status'] . ' order'));
}

mysqli_autocommit($mysqli, false);

try {
    // 1. Update customer info on ALL rows of this bill
    $updateSql = "UPDATE cake_order SET 
        party_detail = '$partyDetail',
        cell_no = '$cellNo',
        deliver_date = '$deliverDate',
        delivery_time = '$deliveryTime',
        priority = '$priority',
        return_date = NOW(),
        notes = CONCAT(IFNULL(notes,''), ' | EDITED by $user at ', NOW())
        WHERE bill_no = $billNo AND ordercancel = 0";
    
    if (!mysqli_query($mysqli, $updateSql)) {
        throw new Exception('Update failed: ' . mysqli_error($mysqli));
    }
    
    // 2. Get existing order data for inserting new items
    $firstRes = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 LIMIT 1");
    $firstRow = mysqli_fetch_assoc($firstRes);
    
    $advance = intval($firstRow['advance']);
    $flatDisc = intval($firstRow['flat_disc']);
    $status = $firstRow['status'];
    $orderType = $firstRow['order_type'];
    
    // 3. Add new items
    foreach ($newItems as $item) {
        $invId = isset($item['inv_id']) ? intval($item['inv_id']) : 0;
        $qty = floatval($item['qty']);
        $price = floatval($item['price']);
        $amount = round($price * $qty);
        $category = esc($item['category']);
        $flavor = esc($item['flavor']);
        $shape = esc($item['shape']);
        $tiers = intval($item['tiers']);
        $uom = esc($item['uom']);
        $cakeMsg = esc($item['cake_message']);
        $itemNote = esc($item['note']);
        
        $sql = "INSERT INTO cake_order 
            (bill_no, inv_date, return_date, deliver_date, delivery_time, 
             amount, advance, paid, cell_no, party_detail, notes, 
             user, dateent, order_taker, order_factory, ordercancel,
             status, off_bill_no, off_dateent, flat_disc, pay_date, 
             order_type, inv_id, qty, ext_pay, flavor, flavor_amt, 
             priority, category, tiers, shape, cake_message, retail_price,
             image_data, thumb_data, audio_data, uom, payment_method)
            VALUES 
            ($billNo, CURDATE(), NOW(), '$deliverDate', '$deliveryTime',
             $amount, $advance, 0, '$cellNo', '$partyDetail', '[ADDED LATER by $user] $itemNote',
             '$user', NOW(), '$user', 'main', 0,
             '$status', 0, NOW(), $flatDisc, '$deliverDate',
             '$orderType', $invId, $qty, 0, '$flavor', 0,
             '$priority', '$category', $tiers, '$shape', '$cakeMsg', $price,
             NULL, NULL, NULL, '$uom', NULL)";
        
        if (!mysqli_query($mysqli, $sql)) {
            throw new Exception('Insert failed: ' . mysqli_error($mysqli));
        }
    }
    
    mysqli_commit($mysqli);
    
    jsonResponse(array(
        'success' => true,
        'bill_no' => $billNo,
        'new_items_added' => count($newItems),
        'message' => 'Order updated successfully'
    ));
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, true);
?>