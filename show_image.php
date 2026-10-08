<?php
require_once 'db.php';
require_once 'upload_helper.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'image';
$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
$idx = isset($_GET['idx']) ? intval($_GET['idx']) : 0;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$billNo && !$id) {
    http_response_code(404);
    exit;
}

if ($id) {
    $sql = "SELECT image_data, thumb_data, audio_data FROM cake_order WHERE id = $id LIMIT 1";
} else {
    $sql = "SELECT image_data, thumb_data, audio_data FROM cake_order WHERE bill_no = $billNo ORDER BY id LIMIT 1 OFFSET $idx";
}

$res = mysqli_query($mysqli, $sql);
$row = mysqli_fetch_assoc($res);

if (!$row) { 
    http_response_code(404); 
    exit; 
}

// Cache headers (1 day cache - images are immutable once saved)
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

if ($type == 'image' && !empty($row['image_data'])) {
    header('Content-Type: image/jpeg');
    echo pack('H*', $row['image_data']);
} elseif ($type == 'thumb' && !empty($row['thumb_data'])) {
    header('Content-Type: image/jpeg');
    echo pack('H*', $row['thumb_data']);
} elseif ($type == 'audio' && !empty($row['audio_data'])) {
    header('Content-Type: audio/webm');
    echo pack('H*', $row['audio_data']);
} else {
    http_response_code(404);
}
?>