<?php
require_once 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'kitchen_count':
        $stmt = $pdo->query("SELECT COUNT(DISTINCT bill_no) as count FROM cake_order 
            WHERE status IN ('confirmed','preparing') AND ordercancel = 0");
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;

    case 'get_audio':
        $billNo = $_GET['bill_no'] ?? 0;
        $stmt = $pdo->prepare("SELECT audio_data FROM cake_order WHERE bill_no = ? AND audio_data IS NOT NULL AND audio_data != '' LIMIT 1");
        $stmt->execute([$billNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['audio_data' => $row['audio_data'] ?? null]);
        break;

    case 'order_status':
        $billNo = $_GET['bill_no'] ?? 0;
        $stmt = $pdo->prepare("SELECT status FROM cake_order WHERE bill_no = ? LIMIT 1");
        $stmt->execute([$billNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['status' => 'unknown']);
        break;

    case 'dashboard_stats':
        $today = date('Y-m-d');
        
        $stats = [];
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(paid),0) as revenue FROM cake_order WHERE pay_date = ? AND ordercancel = 0");
        $stmt->execute([$today]);
        $stats['revenue_today'] = $stmt->fetch()['revenue'];

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT bill_no) as cnt FROM cake_order WHERE inv_date = ? AND ordercancel = 0");
        $stmt->execute([$today]);
        $stats['orders_today'] = $stmt->fetch()['cnt'];

        $stmt = $pdo->query("SELECT COUNT(DISTINCT bill_no) as cnt FROM cake_order WHERE status = 'pending' AND ordercancel = 0");
        $stats['pending'] = $stmt->fetch()['cnt'];

        echo json_encode($stats);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}