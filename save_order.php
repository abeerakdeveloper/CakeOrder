<?php
require_once 'db.php';
require_once 'upload_helper.php';

$rawInput = file_get_contents('php://input');
error_log("DEBUG: save_order.php called at " . date('Y-m-d H:i:s') . ". Raw Input: " . substr($rawInput, 0, 200));

$input = json_decode($rawInput, true);if (!$input || empty($input['items'])) {
    jsonResponse(array('success' => false, 'message' => 'No items provided'));
}

$items = $input['items'];
$status = isset($input['status']) ? $input['status'] : 'pending';
$partyDetail = isset($input['party_detail']) ? $input['party_detail'] : 'Walk-in';
$cellNo = isset($input['cell_no']) ? $input['cell_no'] : '';
$deliverDate = isset($input['deliver_date']) ? $input['deliver_date'] : date('Y-m-d');
$deliveryTime = isset($input['delivery_time']) ? $input['delivery_time'] : '12:00';
$priority = isset($input['priority']) ? $input['priority'] : 'normal';
$flatDisc = isset($input['flat_disc']) ? intval($input['flat_disc']) : 0;
$advance = isset($input['advance']) ? intval($input['advance']) : 0;
$advanceMethod = isset($input['advance_method']) ? $input['advance_method'] : '';
$extraCharge = isset($input['extra_charge']) ? intval($input['extra_charge']) : 0;
$occasion = isset($input['occasion']) ? esc($input['occasion']) : '';
$deliveryType = isset($input['delivery_type']) ? esc($input['delivery_type']) : 'pickup';
$deliveryAddress = isset($input['delivery_address']) ? esc($input['delivery_address']) : '';
$deliveryBranch = isset($input['delivery_branch']) ? esc($input['delivery_branch']) : '';
$source = isset($input['source']) ? esc($input['source']) : 'walk-in';

// ============================================
// GET BILL NUMBER FROM retvchno('CAK') FUNCTION
// ============================================
$billNo = 0;
$voucherRes = mysqli_query($mysqli, "SELECT retvchno('CAK') AS bill_no");
if ($voucherRes) {
    $voucherRow = mysqli_fetch_assoc($voucherRes);
    $billNo = intval($voucherRow['bill_no']);
    error_log("DEBUG: retvchno('CAK') returned billNo: " . $billNo);
}
// Fallback if function returns 0 or fails
if ($billNo <= 0) {
    $res = mysqli_query($mysqli, "SELECT MAX(bill_no) AS max_bill FROM cake_order");
    $row = mysqli_fetch_assoc($res);
    $billNo = (intval($row['max_bill']) > 0 ? intval($row['max_bill']) : 9920) + 1;
}

$user = $_SESSION['user'];
$salesman = !empty($_SESSION['salesman_name']) ? $_SESSION['salesman_name'] : $user;

// Calculate total (extra charge added once, not as a separate item row)
$totalAmount = 0;
foreach ($items as $item) {
    $totalAmount += $item['price'] * $item['qty'];
}
$totalAmount += $extraCharge;
$extraApplied = false;

// Begin transaction
mysqli_autocommit($mysqli, false);

$success = true;
$errorMsg = '';

try {
    foreach ($items as $item) {
        // Convert image/audio to HEX
        $imageHex = null;
        $thumbHex = null;
        $audioHex = null;
        
        if (!empty($item['image_data'])) {
            $imageHex = dataUrlToHex($item['image_data']);
            $thumbHex = generateThumbnailHex($item['image_data'], 200);
        }
        
        if (!empty($item['audio_data'])) {
            if (strpos($item['audio_data'], 'data:') === 0) {
                $audioHex = dataUrlToHex($item['audio_data']);
            } else {
                $audioHex = $item['audio_data'];
            }
        }
        
        $invId = isset($item['inv_id']) ? intval($item['inv_id']) : 0;
        $qty = floatval($item['qty']);
        $price = floatval($item['price']);
        $amount = round($price * $qty);
        $category = isset($item['category']) ? esc($item['category']) : esc($item['name']);
        $flavor = isset($item['flavor']) ? esc($item['flavor']) : '';
        $shape = isset($item['shape']) ? esc($item['shape']) : '';
        $tiers = isset($item['tiers']) ? intval($item['tiers']) : 1;
        $uom = isset($item['uom']) ? esc($item['uom']) : 'pcs';
        $cakeMsg = isset($item['cake_message']) ? esc($item['cake_message']) : '';
        $itemNote = isset($item['note']) ? esc($item['note']) : '';
        
        // Build combined notes with extra info
        $fullNote = $itemNote;
        if ($deliveryBranch) $fullNote = "DELBRANCH: $deliveryBranch | " . $fullNote;
        if ($occasion) $fullNote = "OCCASION: $occasion | " . $fullNote;
        if ($deliveryType == 'delivery' && $deliveryAddress) {
            $fullNote .= " | DELIVERY ADDR: $deliveryAddress";
        }
        $fullNote = esc($fullNote);
        
        $imageSql = $imageHex ? "'" . esc($imageHex) . "'" : "NULL";
        $thumbSql = $thumbHex ? "'" . esc($thumbHex) . "'" : "NULL";
        $audioSql = $audioHex ? "'" . esc($audioHex) . "'" : "NULL";
        $extPay = 0;
        if ($extraCharge > 0 && !$extraApplied) { $amount += $extraCharge; $extPay = $extraCharge; $extraApplied = true; }
        
        $sql = "INSERT INTO cake_order 
            (bill_no, inv_date, return_date, deliver_date, delivery_time, 
             amount, advance, paid, cell_no, party_detail, notes, 
             user, dateent, order_taker, order_factory, ordercancel,
             status, off_bill_no, off_dateent, flat_disc, pay_date, 
             order_type, inv_id, qty, ext_pay, flavor, flavor_amt, 
             priority, category, tiers, shape, cake_message, retail_price,
             image_data, thumb_data, audio_data, uom, payment_method)
            VALUES 
            ($billNo, CURDATE(), NOW(), '".esc($deliverDate)."', '".esc($deliveryTime)."',
             $amount, $advance, 0, '".esc($cellNo)."', '".esc($partyDetail)."', '$fullNote',
             '".esc($user)."', NOW(), '".esc($salesman)."', 'main', 0,
             '".esc($status)."', 0, NOW(), $flatDisc, '".esc($deliverDate)."',
             '".esc($source)."', $invId, $qty, $extPay, '$flavor', 0,
             '".esc($priority)."', '$category', $tiers, '$shape', '$cakeMsg', $price,
             $imageSql, $thumbSql, $audioSql, '$uom', NULL)";

        if (!mysqli_query($mysqli, $sql)) {
            $success = false;
            $errorMsg = mysqli_error($mysqli);
            break;
        }
    }
    
    // ============================================
    // Extra photos for this bill (multi-image orders)
    // ============================================
    if ($success && !empty($input['extra_images']) && is_array($input['extra_images'])) {
        mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS order_extra_images (
            id INT NOT NULL AUTO_INCREMENT,
            bill_no INT NOT NULL,
            image_data MEDIUMTEXT,
            thumb_data MEDIUMTEXT,
            dateent DATETIME,
            PRIMARY KEY (id), KEY idx_bill (bill_no)
        ) ENGINE=MyISAM");
        foreach ($input['extra_images'] as $durl) {
            $hex  = dataUrlToHex($durl);
            $thex = generateThumbnailHex($durl, 200);
            if ($hex) {
                mysqli_query($mysqli, "INSERT INTO order_extra_images
                    (bill_no, image_data, thumb_data, dateent)
                    VALUES ($billNo, '" . esc($hex) . "', '" . esc($thex) . "', NOW())");
            }
        }
    }

    // ============================================
    // If advance payment given, record in gledg
    // ============================================
    if ($success && $advance > 0 && $advanceMethod) {
        // amt_type: CR=Cash Receipt, BR=Bank Receipt
        $amtType = ($advanceMethod == 'cash') ? 'CR' : 'BR';
        $vnoFunc = ($advanceMethod == 'cash') ? 'CR' : 'BR';
        $partyEsc = esc($partyDetail);
        $userEsc = esc($user);
        
        // Get new voucher number from retvno() function
        $vno = 0;
        $vRes = mysqli_query($mysqli, "SELECT retvno('$vnoFunc') AS vno");
        if ($vRes) {
            $vRow = mysqli_fetch_assoc($vRes);
            $vno = intval($vRow['vno']);
        }
        
        $descText = "ADVANCE - Cake Order #$billNo - $partyEsc";
        
        $glSql = "INSERT INTO gledg 
            (amt_type,vno, acc_code, date, v_type, amount, `desc`, ref_no, user, dateent) 
            VALUES 
            ('CR',$vno, '112000001', CURDATE(), '$amtType', $advance, 
             '$descText', $billNo, '$userEsc', NOW())";
        
        if (!mysqli_query($mysqli, $glSql)) {
            error_log('Advance GL entry failed: ' . mysqli_error($mysqli));
        }
    }
    
    if ($success) {
        // eatable / decoration pictures (cake_detail table)
if (!empty($input['eatables'])) {
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS cake_detail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_no INT, image_data MEDIUMTEXT, thumb_data MEDIUMTEXT,
        qty VARCHAR(10), price DECIMAL(10,2), description VARCHAR(255),
        dateent DATETIME, KEY (bill_no))");
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
        if (!mysqli_query($mysqli, "INSERT INTO cake_detail (bill_no, image_data, thumb_data, qty, price, description, dateent)
            VALUES ($billNo, '$eImg', '$eThumb', '$eq', $ep, '$ed', NOW())")) {
            error_log('cake_detail insert failed: ' . mysqli_error($mysqli));
        }
    }
}

mysqli_commit($mysqli);
        jsonResponse(array(
            'success' => true,
            'bill_no' => $billNo,
            'total' => $totalAmount - $flatDisc,
            'message' => 'Order #' . $billNo . ' saved successfully'
        ));
    } else {
        mysqli_rollback($mysqli);
        jsonResponse(array('success' => false, 'message' => $errorMsg));
    }
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}

mysqli_autocommit($mysqli, false);
?>