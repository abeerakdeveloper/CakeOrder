<?php
/*
$session_path = __DIR__ . '/sessions';
if (!file_exists($session_path)) {
    @mkdir($session_path, 0777, true);
}
session_save_path($session_path);
session_start();
*/

// ============================================
// SESSION OPTIMIZATION
// Only initialize once, cache check in memory
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    
    // Define session path constant (only computed once per request)
    if (!defined('SESSION_PATH')) {
        define('SESSION_PATH', __DIR__ . '/sessions');
    }
    
    // Only check/create folder ONCE per server lifetime using APC/file flag
    $sessionFlagFile = SESSION_PATH . '/.initialized';
    
    if (!@is_dir(SESSION_PATH)) {
        @mkdir(SESSION_PATH, 0755, true);
        @file_put_contents($sessionFlagFile, time());
        @file_put_contents(SESSION_PATH . '/.htaccess', "Deny from all\n");
    }
    
    // Session optimization settings
    ini_set('session.save_path', SESSION_PATH);
    ini_set('session.gc_maxlifetime', 28800);      // 8 hours
    ini_set('session.cookie_lifetime', 28800);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.gc_probability', 1);           // Run GC 1 in 100 requests
    ini_set('session.gc_divisor', 100);
    
    // Use shorter session IDs for faster lookup
    ini_set('session.sid_length', 32);
    ini_set('session.sid_bits_per_character', 5);
    
    session_start();
}


$host = '182.180.87.102';
$port = '34599';
$dbname = 'salman_gulbahar_bakery';
$username = 'waqar';
$password = 'waqarmgr2019';


// Using mysqli for PHP 5.3.2 compatibility
$mysqli = mysqli_connect($host, $username, $password, $dbname,$port);
if (!$mysqli) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($mysqli, "utf8");



// Check if logged in - except for login page
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

// Helper functions
function esc($str) {
    global $mysqli;
    return mysqli_real_escape_string($mysqli, $str);
}

function getStatusBadge($status) {
    $colors = array(
        'pending' => '#f39c12', 'confirmed' => '#3498db', 'hold' => '#95a5a6',
        'preparing' => '#e67e22', 'ready' => '#27ae60', 'delivered' => '#2ecc71',
        'paid' => '#1abc9c', 'cancelled' => '#e74c3c'
    );
    $color = isset($colors[$status]) ? $colors[$status] : '#999';
    return "<span style='background:".$color.";color:#fff;padding:3px 10px;border-radius:12px;font-size:12px;'>".ucfirst($status)."</span>";
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Role helper functions
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
    $roles = array(1 => 'POS User', 2 => 'Kitchen', 3 => 'Admin');
    return isset($roles[getUserRole()]) ? $roles[getUserRole()] : 'Unknown';
}

// Access control - redirect if user doesn't have access
function requireRole($allowedRoles) {
    if (!in_array(getUserRole(), $allowedRoles)) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

// ============================================
// BRANCH INFO HELPER (cached per request)
// ============================================
function getBranchInfo() {
    global $mysqli;
    static $branchInfo = null;
    
    // Return cached value if already fetched in this request
    if ($branchInfo !== null) return $branchInfo;
    
    $res = mysqli_query($mysqli, "SELECT branch_name, address1 FROM branch LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $branchInfo = array(
            'name' => $row['branch_name'],
            'address' => $row['address1']
        );
    } else {
        $branchInfo = array(
            'name' => 'BestPOS',
            'address' => 'Pakistan'
        );
    }
    return $branchInfo;
}

function getBranchName() {
    $b = getBranchInfo();
    return $b['name'];
}

function getBranchAddress() {
    $b = getBranchInfo();
    return $b['address'];
}

?>