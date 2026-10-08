<?php

/*
// ============================================
// OUTPUT BUFFERING - Speeds up response
// ============================================
if (!ob_get_level()) {
    ob_start();
}

// ============================================
// ERROR HANDLING
// ============================================
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// SESSION OPTIMIZATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {

    if (!defined('SESSION_PATH')) {
        define('SESSION_PATH', __DIR__ . '/sessions');
    }

    if (!is_dir(SESSION_PATH)) {
        @mkdir(SESSION_PATH, 0755, true);
        @file_put_contents(SESSION_PATH . '/.htaccess', "Deny from all\n");
    }

    ini_set('session.save_path',             SESSION_PATH);
    ini_set('session.gc_maxlifetime',        28800);
    ini_set('session.cookie_lifetime',       28800);
    ini_set('session.use_strict_mode',       1);
    ini_set('session.cookie_httponly',       1);
    ini_set('session.use_only_cookies',      1);
    ini_set('session.gc_probability',        1);
    ini_set('session.gc_divisor',            1000);  // GC runs 1/1000 (less overhead)
    ini_set('session.lazy_write',            1);     // Only write if changed
    ini_set('session.sid_length',            32);
    ini_set('session.sid_bits_per_character', 5);

    session_start();
}

*/
session_start();
// ============================================
// DATABASE CONNECTION (mysqli - keeping your original)
// ============================================
$host     = '182.180.87.102';
$port     =  34599;           // integer, not string
$dbname   = 'salman_gulbahar_bakery';
$username = 'waqar';
$password = 'waqarmgr2019';

// -----------------------------------------------
// PERSISTENT + OPTIMIZED mysqli connection
// -----------------------------------------------
if (!isset($GLOBALS['mysqli']) || !($GLOBALS['mysqli'] instanceof mysqli)) {

    // 'p:' prefix = persistent connection (reuses socket, major speed boost)
    $mysqli = mysqli_connect('p:' . $host, $username, $password, $dbname, $port);

    if (!$mysqli) {
        error_log('[DB ERROR] Connection failed: ' . mysqli_connect_error());
        http_response_code(503);
        die(json_encode([
            'error'   => true,
            'message' => 'Database connection failed. Please try again.'
        ]));
    }

    // Set charset
    mysqli_set_charset($mysqli, 'utf8mb4');

    // Optimize cloud DB timeouts - prevents hanging requests
    mysqli_query($mysqli, "SET
        SESSION wait_timeout        = 300,
        SESSION interactive_timeout = 300,
        SESSION net_read_timeout    = 30,
        SESSION net_write_timeout   = 30,
        time_zone                   = '+05:00'
    ");

    $GLOBALS['mysqli'] = $mysqli;

} else {
    $mysqli = $GLOBALS['mysqli'];
}

// ============================================
// LOGIN & SESSION CHECK
// ============================================
$currentFile = basename($_SERVER['PHP_SELF']);

if ($currentFile !== 'login.php' && !isset($_SESSION['user'])) {
	header('Location: login.php');
    exit;
}

// Session timeout (8 hours)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 28800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Escape string safely
function esc($str) {
    global $mysqli;
    return mysqli_real_escape_string($mysqli, htmlspecialchars_decode($str));
}

// Status badge HTML
function getStatusBadge($status) {
    $colors = [
        'pending'   => '#f39c12',
        'confirmed' => '#3498db',
        'hold'      => '#95a5a6',
        'preparing' => '#e67e22',
        'ready'     => '#27ae60',
        'delivered' => '#2ecc71',
        'paid'      => '#1abc9c',
        'cancelled' => '#e74c3c'
    ];
    $color = isset($colors[$status]) ? $colors[$status] : '#999';
    return "<span style='background:{$color};color:#fff;padding:3px 10px;
            border-radius:12px;font-size:12px;'>" . ucfirst($status) . "</span>";
}

// JSON response and exit
function jsonResponse($data) {
    // Flush any buffered output first
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================
// ROLE FUNCTIONS
// ============================================

function getUserRole() {
    return isset($_SESSION['role']) ? intval($_SESSION['role']) : 0;
}

function isAdmin() {
    return getUserRole() === 3;
}

function isKitchen() {
    return getUserRole() === 2;
}

function isPOSUser() {
    return getUserRole() === 1;
}

function getRoleName() {
    $roles = [1 => 'POS User', 2 => 'Kitchen', 3 => 'Admin'];
    $role  = getUserRole();
    return isset($roles[$role]) ? $roles[$role] : 'Unknown';
}

function requireRole($allowedRoles) {
    if (!in_array(getUserRole(), (array)$allowedRoles)) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

// ============================================
// BRANCH INFO (cached per request with static)
// ============================================
function getBranchInfo() {
    global $mysqli;
    static $branchInfo = null;

    if ($branchInfo !== null) return $branchInfo;

    $res = mysqli_query($mysqli, "SELECT branch_name, address1 FROM branch LIMIT 1");

    if ($res && $row = mysqli_fetch_assoc($res)) {
        $branchInfo = [
            'name'    => $row['branch_name'],
            'address' => $row['address1']
        ];
    } else {
        $branchInfo = [
            'name'    => 'BestPOS',
            'address' => 'Pakistan'
        ];
    }

    return $branchInfo;
}

function getBranchName() {
    return getBranchInfo()['name'];
}

function getBranchAddress() {
    return getBranchInfo()['address'];
}

// ============================================
// QUERY HELPER WITH IN-MEMORY CACHE
// Use for repeated SELECT queries on same page
// ============================================
function dbQuery($mysqli, $sql) {
    static $cache = [];

    $key = md5($sql);

    if (isset($cache[$key])) {
        return $cache[$key]; // Return cached result
    }

    $result = mysqli_query($mysqli, $sql);
    $rows   = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
    }

    $cache[$key] = $rows;
    return $rows;
}

// ============================================
// PHP LIMITS FOR CLOUD DB
// ============================================
ini_set('max_execution_time',     60);
ini_set('default_socket_timeout', 15);
ini_set('memory_limit',          '128M');

// ============================================
// SECURITY HEADERS
// ============================================
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}
?>