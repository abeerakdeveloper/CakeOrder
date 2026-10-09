<?php
require_once 'db.php';
require_once 'upload_helper.php';
require_once 'order_lines.php';
require_once 'order_store.php';
requireRole(array(1, 3));

// Full edit of a saved order. Allowed only until the kitchen presses Start preparing.
// Changed rows are updated in place (photos and voice notes stay), new lines are added,
// removed lines are deleted, and every saved change is written to order_change_log.
// The order type and the advance cannot be changed here.

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(array('success' => false, 'message' => 'No changes received'));
}
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;
if ($billNo <= 0) {
    jsonResponse(array('success' => false, 'message' => 'Missing order number'));
}

$rows = ot_load_bill($mysqli, $billNo);
if (empty($rows)) {
    jsonResponse(array('success' => false, 'message' => 'Order not found'));
}
$first = $rows[0];
if (!ot_editable_status($first['status'])) {
    jsonResponse(array('success' => false, 'message' => 'This order is locked. The kitchen has started preparing it, so it cannot be changed.'));
}
$type = ot_bill_type($rows);
if (isset($input['type']) && $input['type'] !== $type) {
    jsonResponse(array('success' => false, 'message' => 'The order type cannot be changed.'));
}
$input['type'] = $type;

$built = ot_build_lines($input);
if (!empty($built['errors'])) {
    jsonResponse(array('success' => false, 'message' => $built['errors'][0]));
}

$branch = getBranchName();
$current = ot_edit_prefill($rows, $branch);
$oldHeader = $current['header'];
$newHeader = ot_header_from_input($input, $branch);
$plan = ot_plan_edit($rows, $built['lines']);
if (!empty($plan['errors'])) {
    jsonResponse(array('success' => false, 'message' => $plan['errors'][0]));
}

// Money checks: the bill may not go below what is already received
$oldTotal = 0;
$savedById = array();
foreach ($rows as $r) {
    $oldTotal += (int) round(ot_num($r['amount']));
    $savedById[(int) $r['id']] = $r;
}
$oldDue = $oldTotal - $oldHeader['flat_disc'];
$newTotal = ot_plan_total($rows, $plan);
$newDue = $newTotal - $newHeader['flat_disc'];
if ($newDue < 0) {
    jsonResponse(array('success' => false, 'message' => 'The discount is more than the total.'));
}
$received = $oldHeader['advance'] + $oldHeader['paid'];
if ($newDue < $received) {
    jsonResponse(array('success' => false, 'message' => 'The new total, Rs. ' . ot_money($newDue)
        . ', would be less than the Rs. ' . ot_money($received) . ' already received. Keep enough items, or reduce the discount, so the total still covers the payments.'));
}

$summary = ot_diff_summary($rows, $plan, $oldHeader, $newHeader);
if (empty($summary)) {
    jsonResponse(array('success' => true, 'changed' => false, 'bill_no' => $billNo, 'message' => 'No changes to save.'));
}
$notesChanged = ($oldHeader['occasion'] !== $newHeader['occasion'])
    || ($oldHeader['delivery_address'] !== $newHeader['delivery_address']);
$user = $_SESSION['user'];

mysqli_autocommit($mysqli, false);
try {
    // Check again inside the transaction: the kitchen may press Start preparing at the same moment.
    $lockRes = mysqli_query($mysqli, "SELECT id, status FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 FOR UPDATE");
    if (!$lockRes) {
        throw new Exception('Could not read the order: ' . mysqli_error($mysqli));
    }
    $lockRow = mysqli_fetch_assoc($lockRes);
    if (!$lockRow || !ot_editable_status($lockRow['status'])) {
        throw new Exception('This order is locked. The kitchen has started preparing it, so it cannot be changed.');
    }

    foreach ($plan['update'] as $id => $line) {
        if (empty($plan['changed'][$id]) && !$notesChanged) {
            continue; // unchanged rows are not written
        }
        $notes = ot_compose_notes($line['kitchen_note'], $newHeader['occasion'], $newHeader['delivery_address'], ot_notes_suffix($savedById[$id]['notes']));
        ot_update_row($mysqli, $billNo, $id, $line, $notes);
    }
    foreach ($plan['insert'] as $line) {
        $notes = ot_compose_notes($line['kitchen_note'], $newHeader['occasion'], $newHeader['delivery_address'], '');
        ot_insert_row($mysqli, $billNo, $first, $newHeader, $oldHeader['advance'], $oldHeader['paid'], $line, $notes);
    }
    ot_delete_rows($mysqli, $billNo, $plan['delete']);
    ot_update_header($mysqli, $billNo, $newHeader, $user);
    ot_write_log($mysqli, $billNo, $user, $oldDue, $newDue, implode("\n", $summary));

    mysqli_commit($mysqli);
    mysqli_autocommit($mysqli, true);
    jsonResponse(array(
        'success' => true,
        'changed' => true,
        'bill_no' => $billNo,
        'total' => $newDue,
        'message' => 'Order #' . $billNo . ' saved. ' . count($summary) . ' change(s) recorded in the history.',
    ));
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    mysqli_autocommit($mysqli, true);
    jsonResponse(array('success' => false, 'message' => $e->getMessage()));
}
