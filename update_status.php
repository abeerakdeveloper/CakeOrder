<?php
require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$billNo = isset($input['bill_no']) ? intval($input['bill_no']) : 0;
$newStatus = isset($input['status']) ? esc($input['status']) : '';

if (!$billNo || !$newStatus) {
    jsonResponse(array('success' => false, 'message' => 'Missing data'));
}

$validTransitions = array(
    'pending' => array('confirmed', 'hold', 'cancelled'),
    'hold' => array('confirmed', 'cancelled'),
    'confirmed' => array('preparing', 'cancelled'),
    'preparing' => array('ready'),
    'ready' => array('delivered'),
    'delivered' => array('paid'),
    'paid' => array(),
    'cancelled' => array()
);

// Allow admin override
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

$res = mysqli_query($mysqli, "SELECT status, paid FROM cake_order WHERE bill_no = $billNo LIMIT 1");
$current = mysqli_fetch_assoc($res);

if (!$current) {
    jsonResponse(array('success' => false, 'message' => 'Order not found'));
}

$currentStatus = $current['status'];

if (!$isAdmin) {
    if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
        jsonResponse(array('success' => false, 'message' => "Cannot change from '$currentStatus' to '$newStatus'"));
    }
}

$sql = "UPDATE cake_order SET status = '$newStatus', return_date = NOW() WHERE bill_no = $billNo AND ordercancel = 0";
if (mysqli_query($mysqli, $sql)) {
    jsonResponse(array(
        'success' => true,
        'bill_no' => $billNo,
        'old_status' => $currentStatus,
        'new_status' => $newStatus
    ));
} else {
    jsonResponse(array('success' => false, 'message' => mysqli_error($mysqli)));
}
?>