<?php
// ============================================================
// Order database helpers: reads and writes of cake_order rows
// and of order_change_log. The rules are in order_lines.php.
// Callers run these inside a transaction and handle the
// exceptions. Values are escaped with esc().
// ============================================================
require_once 'upload_helper.php';
require_once 'order_lines.php';

function ot_sql_str($value) {
    return "'" . esc((string) $value) . "'";
}

function ot_sql_int_or_null($value) {
    return ($value === null || $value === '') ? 'NULL' : (string) intval($value);
}

// Unsaved rows of one bill (ordercancel = 0), ordered by id, with photo and voice flags.
function ot_load_bill($mysqli, $billNo) {
    $res = mysqli_query($mysqli, "SELECT id, bill_no, inv_date, deliver_date, delivery_time, party_detail, cell_no,
        order_taker, user, order_type, sale_type, inv_id, category, qty, amount, retail_price, tiers, uom, flavor,
        shape, cake_message, material, kitchen_note, notes, box_group, box_qty, flat_disc, advance, paid, status,
        payment_method, pay_date, priority, delivery_branch, dateent,
        (image_data IS NOT NULL AND image_data <> '') AS has_image,
        (audio_data IS NOT NULL AND audio_data <> '') AS has_audio
        FROM cake_order WHERE bill_no = " . intval($billNo) . " AND ordercancel = 0 ORDER BY id");
    $rows = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
    return $rows;
}

// Change history of one bill, newest first.
function ot_load_log($mysqli, $billNo) {
    $out = array();
    $res = mysqli_query($mysqli, "SELECT changed_by, changed_at, old_total, new_total, details
        FROM order_change_log WHERE bill_no = " . intval($billNo) . " ORDER BY id DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = $r;
        }
    }
    return $out;
}

// Image, thumbnail and voice as SQL-ready hex, or null where the line has no new media.
function ot_media_hex(array $line) {
    $m = array('image' => null, 'thumb' => null, 'audio' => null);
    if (!empty($line['image_data'])) {
        $hex = dataUrlToHex($line['image_data']);
        $thumb = generateThumbnailHex($line['image_data'], 200);
        if ($hex) {
            $m['image'] = ot_sql_str($hex);
        }
        if ($thumb) {
            $m['thumb'] = ot_sql_str($thumb);
        }
    }
    if (!empty($line['audio_data'])) {
        $hex = (strpos($line['audio_data'], 'data:') === 0) ? dataUrlToHex($line['audio_data']) : $line['audio_data'];
        if ($hex) {
            $m['audio'] = ot_sql_str($hex);
        }
    }
    return $m;
}

// Updates one saved row. Media is replaced only when a new photo or voice note was sent.
function ot_update_row($mysqli, $billNo, $id, array $line, $notes) {
    $sets = array(
        "sale_type = " . ot_sql_str($line['sale_type']),
        "category = " . ot_sql_str($line['category']),
        "inv_id = " . intval($line['inv_id']),
        "qty = " . sprintf('%.2f', (float) $line['qty']),
        "retail_price = " . sprintf('%.2f', (float) $line['price']),
        "amount = " . intval($line['amount']),
        "tiers = " . sprintf('%.2f', (float) $line['tiers']),
        "uom = " . ot_sql_str($line['uom']),
        "flavor = " . ot_sql_str($line['flavor']),
        "shape = " . ot_sql_str($line['shape']),
        "cake_message = " . ot_sql_str($line['cake_message']),
        "material = " . ot_sql_str($line['material']),
        "kitchen_note = " . ot_sql_str($line['kitchen_note']),
        "box_group = " . ot_sql_int_or_null($line['box_group']),
        "box_qty = " . ot_sql_int_or_null($line['box_qty']),
        "notes = " . ot_sql_str($notes),
    );
    $m = ot_media_hex($line);
    if ($m['image'] !== null) {
        $sets[] = "image_data = " . $m['image'];
    }
    if ($m['thumb'] !== null) {
        $sets[] = "thumb_data = " . $m['thumb'];
    }
    if ($m['audio'] !== null) {
        $sets[] = "audio_data = " . $m['audio'];
    }
    $sql = "UPDATE cake_order SET " . implode(', ', $sets)
        . " WHERE id = " . intval($id) . " AND bill_no = " . intval($billNo) . " AND ordercancel = 0";
    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Could not save an item: ' . mysqli_error($mysqli));
    }
}

// Adds one new row to a bill. Bill-level fields (advance, paid, user, status) come from the bill's first row.
function ot_insert_row($mysqli, $billNo, array $first, array $header, $advance, $paid, array $line, $notes) {
    $m = ot_media_hex($line);
    $invDate = ((string) $first['inv_date'] !== '') ? ot_sql_str(substr((string) $first['inv_date'], 0, 10)) : 'CURDATE()';
    $taker = ((string) $first['order_taker'] !== '') ? $first['order_taker'] : $first['user'];
    $payDate = ((string) $first['pay_date'] !== '') ? ot_sql_str(substr((string) $first['pay_date'], 0, 10)) : 'NULL';
    $payMethod = ((string) $first['payment_method'] !== '') ? ot_sql_str($first['payment_method']) : 'NULL';
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
        (" . intval($billNo) . ", $invDate, NOW(), " . ot_sql_str($header['deliver_date']) . ", " . ot_sql_str($header['delivery_time']) . ",
         " . intval($line['amount']) . ", " . intval($advance) . ", " . intval($paid) . ", " . ot_sql_str($header['cell_no']) . ", " . ot_sql_str($header['party_detail']) . ", " . ot_sql_str($notes) . ",
         " . ot_sql_str($first['user']) . ", NOW(), " . ot_sql_str($taker) . ", 'main', 0,
         " . ot_sql_str($first['status']) . ", 0, NOW(), " . intval($header['flat_disc']) . ", $payDate,
         " . ot_sql_str($header['source']) . ", " . intval($line['inv_id']) . ", " . sprintf('%.2f', (float) $line['qty']) . ", 0, " . ot_sql_str($line['flavor']) . ", 0,
         " . ot_sql_str($header['priority']) . ", " . ot_sql_str($line['category']) . ", " . sprintf('%.2f', (float) $line['tiers']) . ", " . ot_sql_str($line['shape']) . ", " . ot_sql_str($line['cake_message']) . ", " . sprintf('%.2f', (float) $line['price']) . ",
         " . ($m['image'] !== null ? $m['image'] : 'NULL') . ", " . ($m['thumb'] !== null ? $m['thumb'] : 'NULL') . ", " . ($m['audio'] !== null ? $m['audio'] : 'NULL') . ", " . ot_sql_str($line['uom']) . ", $payMethod,
         " . ot_sql_str($line['sale_type']) . ", " . ot_sql_int_or_null($line['box_group']) . ", " . ot_sql_int_or_null($line['box_qty']) . ", " . ot_sql_str($line['kitchen_note']) . ", " . ot_sql_str($line['material']) . ", " . ot_sql_str($header['delivery_branch']) . ")";
    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Could not add an item: ' . mysqli_error($mysqli));
    }
}

// Removes saved rows of a bill. Weighed sweet-box amounts are never removed here.
function ot_delete_rows($mysqli, $billNo, array $ids) {
    $list = array();
    foreach ($ids as $id) {
        $list[] = intval($id);
    }
    if (empty($list)) {
        return;
    }
    $sql = "DELETE FROM cake_order WHERE bill_no = " . intval($billNo) . " AND ordercancel = 0
        AND id IN (" . implode(',', $list) . ") AND (sale_type IS NULL OR sale_type <> 'weighed')";
    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Could not remove an item: ' . mysqli_error($mysqli));
    }
}

// Bill-level fields on every row of the bill, and the edit stamp in the notes (kept from the earlier edit).
function ot_update_header($mysqli, $billNo, array $h, $user) {
    $sql = "UPDATE cake_order SET
        party_detail = " . ot_sql_str($h['party_detail']) . ",
        cell_no = " . ot_sql_str($h['cell_no']) . ",
        deliver_date = " . ot_sql_str($h['deliver_date']) . ",
        delivery_time = " . ot_sql_str($h['delivery_time']) . ",
        priority = " . ot_sql_str($h['priority']) . ",
        flat_disc = " . intval($h['flat_disc']) . ",
        order_type = " . ot_sql_str($h['source']) . ",
        delivery_branch = " . ot_sql_str($h['delivery_branch']) . ",
        return_date = NOW(),
        notes = CONCAT(IFNULL(notes, ''), ' | EDITED by " . esc((string) $user) . " at ', NOW())
        WHERE bill_no = " . intval($billNo) . " AND ordercancel = 0";
    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Could not save the order details: ' . mysqli_error($mysqli));
    }
}

// One line in the change log: who, when, the bill total before and after, and what changed.
function ot_write_log($mysqli, $billNo, $user, $oldTotal, $newTotal, $details) {
    $sql = "INSERT INTO order_change_log (bill_no, changed_by, changed_at, old_total, new_total, details)
        VALUES (" . intval($billNo) . ", " . ot_sql_str($user) . ", NOW(), "
        . sprintf('%.2f', (float) $oldTotal) . ", " . sprintf('%.2f', (float) $newTotal) . ", " . ot_sql_str($details) . ")";
    if (!mysqli_query($mysqli, $sql)) {
        throw new Exception('Could not write the change log: ' . mysqli_error($mysqli));
    }
}
