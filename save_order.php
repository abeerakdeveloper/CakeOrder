<?php
require_once 'db.php';
require_once 'upload_helper.php';
require_once 'order_lines.php';

// Order types: cake, lunch box, sweet box, eatable picture, other.
// Lunch and sweet boxes are saved as box groups (one row per item per group).
// Extra charges are saved as their own rows, so bill totals and balance need no new formula.

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(array('success' => false, 'message' => 'No order received'));
}

$built = ot_build_lines($input);
if (!empty($built['errors'])) {
    jsonResponse(array('success' => false, 'message' => $built['errors'][0]));
}
$lines = $built['lines'];

$status = isset($input['status']) ? $input['status'] : 'pending';
$partyDetail = isset($input['party_detail']) && $input['party_detail'] !== '' ? $input['party_detail'] : 'Walk-in';
$cellNo = isset($input['cell_no']) ? $input['cell_no'] : '';
$deliverDate = isset($input['deliver_date']) ? $input['deliver_date'] : date('Y-m-d');
$deliveryTime = isset($input['delivery_time']) ? $input['delivery_time'] : '12:00';
$priority = isset($input['priority']) ? $input['priority'] : 'normal';
$flatDisc = isset($input['flat_disc']) ? intval($input['flat_disc']) : 0;
$advance = isset($input['advance']) ? intval($input['advance']) : 0;
$advanceMethod = isset($input['advance_method']) ? $input['advance_method'] : '';
$occasion = isset($input['occasion']) ? trim($input['occasion']) : '';
$deliveryType = isset($input['delivery_type']) ? esc($input['delivery_type']) : 'pickup';
$deliveryAddress = isset($input['delivery_address']) ? trim($input['delivery_address']) : '';
$source = isset($input['source']) ? esc($input['source']) : 'walk-in';
$deliveryBranchRaw = isset($input['delivery_branch']) ? trim($input['delivery_branch']) : '';
if ($deliveryBranchRaw === '') {
    $deliveryBranchRaw = getBranchName();
}
$deliveryBranch = esc($deliveryBranchRaw);

// ============================================
// GET BILL NUMBER FROM retvchno('CAK') FUNCTION
// ============================================
$billNo = 0;
$voucherRes = mysqli_query($mysqli, "SELECT retvchno('CAK') AS bill_no");
if ($voucherRes) {
    $voucherRow = mysqli_fetch_assoc($voucherRes);
    $billNo = intval($voucherRow['bill_no']);
}
// Fallback if function returns 0 or fails
if ($billNo <= 0) {
    $res = mysqli_query($mysqli, "SELECT MAX(bill_no) AS max_bill FROM cake_order");
    $row = mysqli_fetch_assoc($res);
    $billNo = (intval($row['max_bill']) > 0 ? intval($row['max_bill']) : 9920) + 1;
}

$user = $_SESSION['user'];

$totalAmount = 0;
foreach ($lines as $l) {
    $totalAmount += $l['amount'];
}

// Begin transaction
mysqli_autocommit($mysqli, false);

$success = true;
$errorMsg = '';

try {
    foreach ($lines as $line) {
        // Convert image/audio to HEX
        $imageHex = null;
        $thumbHex = null;
        $audioHex = null;

        if (!empty($line['image_data'])) {
            $imageHex = dataUrlToHex($line['image_data']);
            $thumbHex = generateThumbnailHex($line['image_data'], 200);
        }

        if (!empty($line['audio_data'])) {
            if (strpos($line['audio_data'], 'data:') === 0) {
                $audioHex = dataUrlToHex($line['audio_data']);
            } else {
                $audioHex = $line['audio_data'];
            }
        }

        $invId = intval($line['inv_id']);
        $qty = sprintf('%.2f', (float) $line['qty']);
        $price = sprintf('%.2f', (float) $line['price']);
        $amount = intval($line['amount']);
        $category = esc($line['category']);
        $flavor = esc($line['flavor']);
        $shape = esc($line['shape']);
        $tiers = sprintf('%.2f', (float) $line['tiers']);
        $uom = esc($line['uom']);
        $cakeMsg = esc($line['cake_message']);
        $material = esc($line['material']);
        $kitchenNote = esc($line['kitchen_note']);
        $saleSql = "'" . esc($line['sale_type']) . "'";
        $boxGroupSql = ($line['box_group'] === null) ? 'NULL' : intval($line['box_group']);
        $boxQtySql = ($line['box_qty'] === null) ? 'NULL' : intval($line['box_qty']);

        // Build combined notes with extra info (kept as before)
        $fullNote = $line['kitchen_note'];
        if ($occasion) $fullNote = "OCCASION: $occasion | " . $fullNote;
        if ($deliveryType == 'delivery' && $deliveryAddress) {
            $fullNote .= " | DELIVERY ADDR: $deliveryAddress";
        }
        $fullNote = esc($fullNote);

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
             image_data, thumb_data, audio_data, uom, payment_method,
             sale_type, box_group, box_qty, kitchen_note, material, delivery_branch)
            VALUES
            ($billNo, CURDATE(), NOW(), '" . esc($deliverDate) . "', '" . esc($deliveryTime) . "',
             $amount, $advance, 0, '" . esc($cellNo) . "', '" . esc($partyDetail) . "', '$fullNote',
             '" . esc($user) . "', NOW(), '" . esc($user) . "', 'main', 0,
             '" . esc($status) . "', 0, NOW(), $flatDisc, '" . esc($deliverDate) . "',
             '$source', $invId, $qty, 0, '$flavor', 0,
             '" . esc($priority) . "', '$category', $tiers, '$shape', '$cakeMsg', $price,
             $imageSql, $thumbSql, $audioSql, '$uom', NULL,
             $saleSql, $boxGroupSql, $boxQtySql, '$kitchenNote', '$material', '$deliveryBranch')";

        if (!mysqli_query($mysqli, $sql)) {
            $success = false;
            $errorMsg = mysqli_error($mysqli);
            break;
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
