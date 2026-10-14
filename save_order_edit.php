<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');   // never break the JSON response with notices
ini_set('log_errors', '1');
require_once 'db.php';
require_once 'upload_helper.php';
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
$salesman = !empty($_SESSION['salesman_name']) ? esc($_SESSION['salesman_name']) : $user;

// Verify order is editable (full_replace = cake edit mode: allowed on any existing bill)
$isFullReplace = !empty($input['full_replace']);
$res = mysqli_query($mysqli, "SELECT status, ordercancel FROM cake_order WHERE bill_no = $billNo LIMIT 1");
$check = mysqli_fetch_assoc($res);
if (!$check) jsonResponse(array('success' => false, 'message' => 'Order not found'));
if (!$isFullReplace) {
    if ($check['ordercancel']) jsonResponse(array('success' => false, 'message' => 'Order is cancelled'));
    if (in_array($check['status'], array('ready','delivered','paid','cancelled'))) {
        jsonResponse(array('success' => false, 'message' => 'Cannot edit ' . $check['status'] . ' order'));
    }
}

mysqli_autocommit($mysqli, false);

try {
    // 0. FULL REPLACE (cake edit mode): rebuild every row of this bill
    if (!empty($input['full_replace'])) {
        error_log('save_order_edit: full_replace start bill=' . $billNo . ' user=' . $user);
        $oldRes = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo ORDER BY id LIMIT 1");
        $old = mysqli_fetch_assoc($oldRes);
        $oldStatus  = $old ? $old['status'] : 'confirmed';
        $oldAdvance = $old ? intval($old['advance']) : 0;
        $oldPaid    = $old ? intval($old['paid']) : 0;
        $oldAudio   = ($old && !empty($old['audio_data'])) ? $old['audio_data'] : '';
        $oldFactory = $old ? $old['order_factory'] : 'main';
        $oldFlat    = $old ? intval($old['flat_disc']) : 0;
        // status follows the pressed button (hold = hold, save = confirmed);
        // delivered & paid bills keep their status
        $reqStatus = isset($input['status']) ? $input['status'] : 'confirmed';
        if (in_array($oldStatus, array('delivered', 'paid'))) {
            $newStatus = $oldStatus;
        } else {
            $newStatus = ($reqStatus === 'hold') ? 'hold' : 'confirmed';
        }

        mysqli_query($mysqli, "DELETE FROM cake_order WHERE bill_no = $billNo");
        mysqli_query($mysqli, "DELETE FROM order_extra_images WHERE bill_no = $billNo");
        mysqli_query($mysqli, "DELETE FROM cake_detail WHERE bill_no = $billNo");

        $extraCharge  = isset($input['extra_charge']) ? intval($input['extra_charge']) : 0;
        $extraApplied = false;
        $eatTotal = 0;
        if (!empty($input['eatables']) && is_array($input['eatables'])) {
            foreach ($input['eatables'] as $erow) {
                $eatTotal += floatval(isset($erow['price']) ? $erow['price'] : 0);
            }
        }
        $eatTotal = round($eatTotal);
        $occasion     = isset($input['occasion']) ? esc($input['occasion']) : '';
        $deliveryType = isset($input['delivery_type']) ? esc($input['delivery_type']) : 'pickup';
        $deliveryAddress = isset($input['delivery_address']) ? esc($input['delivery_address']) : '';
        $newAudioHex  = (!empty($input['audio_data']) && strpos($input['audio_data'], 'data:') === 0) ? dataUrlToHex($input['audio_data']) : '';
        $rItems = isset($input['items']) ? $input['items'] : array();
        $firstItem = true;
        foreach ($rItems as $item) {
            $invId  = isset($item['inv_id']) ? intval($item['inv_id']) : 0;
            $qty    = floatval($item['qty']);
            $price  = floatval($item['price']);
            $amount = round($price * $qty);
            $category = isset($item['category']) ? esc($item['category']) : esc($item['name']);
            $flavor = isset($item['flavor']) ? esc($item['flavor']) : '';
            $shape  = isset($item['shape']) ? esc($item['shape']) : '';
            $tiers  = isset($item['tiers']) ? intval($item['tiers']) : 1;
            $uom    = isset($item['uom']) ? esc($item['uom']) : 'pcs';
            $cakeMsg = isset($item['cake_message']) ? esc($item['cake_message']) : '';
            $itemNote = isset($item['note']) ? esc($item['note']) : '';
            $fullNote = $itemNote;
            if (!empty($input['delivery_branch'])) $fullNote = "DELBRANCH: " . esc($input['delivery_branch']) . " | " . $fullNote;
            if ($occasion) $fullNote = "OCCASION: $occasion | " . $fullNote;
            if ($deliveryType == 'delivery' && $deliveryAddress) $fullNote .= " | DELIVERY ADDR: " . $deliveryAddress;
            $fullNote = esc($fullNote);

            $imageHex = null; $thumbHex = null;
            if (!empty($item['image_data']) && strpos($item['image_data'], 'data:') === 0) {
                $imageHex = dataUrlToHex($item['image_data']);
                $thumbHex = generateThumbnailHex($item['image_data']);
            }
            $audioHex = null;
            if ($firstItem) {
                if ($newAudioHex) $audioHex = $newAudioHex;
                elseif ($oldAudio) $audioHex = $oldAudio;
            }
            $extPay = 0;
            if (($extraCharge + $eatTotal) > 0 && !$extraApplied) { $amount += $extraCharge + $eatTotal; $extPay = $extraCharge; $extraApplied = true; }

            $imageSql = $imageHex ? "'" . esc($imageHex) . "'" : "NULL";
            $thumbSql = $thumbHex ? "'" . esc($thumbHex) . "'" : "NULL";
            $audioSql = $audioHex ? "'" . esc($audioHex) . "'" : "NULL";
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
                 $amount, $oldAdvance, $oldPaid, '$cellNo', '$partyDetail', '$fullNote',
                 '$user', NOW(), '$salesman', '$oldFactory', 0,
                 '$newStatus', 0, NOW(), $oldFlat, '$deliverDate',
                 '" . esc(isset($input['source']) ? $input['source'] : 'walk-in') . "', $invId, $qty, $extPay, '$flavor', 0,
                 '$priority', '$category', $tiers, '$shape', '$cakeMsg', $price,
                 $imageSql, $thumbSql, $audioSql, '$uom', NULL)";
            if (!mysqli_query($mysqli, $sql)) throw new Exception('Replace insert failed: ' . mysqli_error($mysqli));
            $firstItem = false;
        }
        if (!empty($input['extra_images']) && is_array($input['extra_images'])) {
            mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS order_extra_images (
                id INT AUTO_INCREMENT PRIMARY KEY, bill_no INT,
                image_data MEDIUMTEXT, thumb_data MEDIUMTEXT, dateent DATETIME, KEY (bill_no))");
            foreach ($input['extra_images'] as $durl) {
                if (empty($durl) || strpos($durl, 'data:') !== 0) continue;
                $ih = dataUrlToHex($durl);
                $th = generateThumbnailHex($durl, 200);
                mysqli_query($mysqli, "INSERT INTO order_extra_images (bill_no, image_data, thumb_data, dateent)
                    VALUES ($billNo, '" . esc($ih) . "', '" . esc($th) . "', NOW())");
            }
        }
        if (isset($input['eatables'])) {
            mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS cake_detail (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bill_no INT, image_data MEDIUMTEXT, thumb_data MEDIUMTEXT,
                qty VARCHAR(10), price DECIMAL(10,2), description VARCHAR(255),
                dateent DATETIME, KEY (bill_no))");
            mysqli_query($mysqli, "DELETE FROM cake_detail WHERE bill_no = $billNo");
            foreach ($input['eatables'] as $eat) {
                $eSrc = !empty($eat['image']) ? $eat['image'] : (!empty($eat['img']) ? $eat['img'] : '');
                $eImg = ''; $eThumb = '';
                if (!empty($eSrc) && strpos($eSrc, 'data:') === 0) {
                    $eImg = dataUrlToHex($eSrc);
                    $eThumb = generateThumbnailHex($eSrc, 200);
                }
                $eq = mysqli_real_escape_string($mysqli, isset($eat['qty']) ? $eat['qty'] : '1');
                $ep = floatval(isset($eat['price']) ? $eat['price'] : 0);
                $ed = mysqli_real_escape_string($mysqli, isset($eat['desc']) ? $eat['desc'] : '');
                mysqli_query($mysqli, "INSERT INTO cake_detail (bill_no, image_data, thumb_data, qty, price, description, dateent)
                    VALUES ($billNo, '$eImg', '$eThumb', '$eq', $ep, '$ed', NOW())");
            }
        }
        mysqli_commit($mysqli);
        jsonResponse(array('success' => true, 'bill_no' => $billNo, 'message' => 'Order updated successfully'));
    }

    // 1. Update customer info and status on ALL rows of this bill
    $updateSql = "UPDATE cake_order SET 
        party_detail = '$partyDetail',
        cell_no = '$cellNo',
        deliver_date = '$deliverDate',
        delivery_time = '$deliveryTime',
        priority = '$priority',
        order_taker = '$salesman',
        status = 'confirmed',
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
    $status = 'confirmed';
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
             '$user', NOW(), '$salesman', 'main', 0,
             '$status', 0, NOW(), $flatDisc, '$deliverDate',
             '$orderType', $invId, $qty, 0, '$flavor', 0,
             '$priority', '$category', $tiers, '$shape', '$cakeMsg', $price,
             NULL, NULL, NULL, '$uom', NULL)";
        
        if (!mysqli_query($mysqli, $sql)) {
            throw new Exception('Insert failed: ' . mysqli_error($mysqli));
        }
    }
    
    // voice note re-record (attach to one existing item row)
    if (!empty($input['audio_data']) && !empty($input['audio_item_id'])) {
        $aid = intval($input['audio_item_id']);
        $aHex = dataUrlToHex($input['audio_data']);
        mysqli_query($mysqli, "UPDATE cake_order SET audio_data = '$aHex' WHERE id = $aid AND bill_no = $billNo");
    }

    // eatable / decoration pictures replace
    if (isset($input['eatables'])) {
        mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS cake_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bill_no INT, image_data MEDIUMTEXT, thumb_data MEDIUMTEXT,
            qty VARCHAR(10), price DECIMAL(10,2), description VARCHAR(255),
            dateent DATETIME, KEY (bill_no))");
        mysqli_query($mysqli, "DELETE FROM cake_detail WHERE bill_no = $billNo");
        foreach ($input['eatables'] as $eat) {
            $eSrc = !empty($eat['image']) ? $eat['image'] : (!empty($eat['img']) ? $eat['img'] : '');
            $eImg = ''; $eThumb = '';
            if (!empty($eSrc) && strpos($eSrc, 'data:') === 0) {
                $eImg = dataUrlToHex($eSrc);
                $eThumb = generateThumbnailHex($eSrc, 200);
            }
            $eq = mysqli_real_escape_string($mysqli, isset($eat['qty']) ? $eat['qty'] : '1');
            $ep = floatval(isset($eat['price']) ? $eat['price'] : 0);
            $ed = mysqli_real_escape_string($mysqli, isset($eat['desc']) ? $eat['desc'] : '');
            mysqli_query($mysqli, "INSERT INTO cake_detail (bill_no, image_data, thumb_data, qty, price, description, dateent)
                VALUES ($billNo, '$eImg', '$eThumb', '$eq', $ep, '$ed', NOW())");
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