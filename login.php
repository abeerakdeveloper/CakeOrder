<?php
session_start();

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = mysqli_real_escape_string($mysqli, trim($_POST['user']));
    $pass = mysqli_real_escape_string($mysqli, trim($_POST['mpass']));
    
    if (empty($user) || empty($pass)) {
        $error = 'Please enter username and password';
    } else {
        $sql = "SELECT user, role FROM mpass WHERE user = '$user' AND mpass = '$pass' LIMIT 1";
        $res = mysqli_query($mysqli, $sql);
        
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $_SESSION['user'] = $row['user'];
            $_SESSION['role'] = intval($row['role']);
            $_SESSION['login_time'] = time();
            
            // Redirect based on role
            switch ($_SESSION['role']) {
                case 1: // User
                    header('Location: dashboard.php');
                    break;
                case 2: // Kitchen
                    header('Location: kitchen_display.php');
                    break;
                case 3: // Admin
                    header('Location: dashboard.php');
                    break;
                default:
                    header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
$brLogin = getBranchInfo();
$brName  = $brLogin['name'];
$brAddr  = $brLogin['address'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — BestPOS</title>
<link rel="stylesheet" href="assets/app.css">
<style>
body {
    background: radial-gradient(1200px 600px at 80% -10%, #1d3a63 0%, transparent 60%),
                linear-gradient(160deg, #0e2440 0%, #12294a 60%, #16355c 100%);
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
}
.login-card {
    width: 100%; max-width: 400px; background: #fff; border-radius: 16px;
    padding: 34px 30px 26px; box-shadow: 0 24px 60px rgba(0,0,0,.35); text-align: center;
}
.login-card .logo-ic {
    width: 64px; height: 64px; margin: 0 auto 12px; border-radius: 18px; background: var(--blue-soft);
    display: flex; align-items: center; justify-content: center; font-size: 32px;
}
.login-card h1 { font-size: 21px; color: var(--text); }
.login-card .tag { font-size: 12px; color: var(--muted); margin-top: 3px; }
.login-card .addr { font-size: 11px; color: var(--muted); margin-top: 8px; line-height: 1.4; }
.login-card form { margin-top: 20px; text-align: left; }
.login-card .fld { margin-bottom: 12px; }
.login-card .btn-login {
    width: 100%; padding: 12px; border: none; border-radius: 9px; background: var(--blue);
    color: #fff; font-size: 14.5px; font-weight: 700; cursor: pointer; margin-top: 6px;
}
.login-card .btn-login:hover { background: #155cd6; }
.login-err { background: #fdecee; color: #c92a2f; border: 1px solid #f6c9cc; border-radius: 8px; padding: 9px 12px; font-size: 12.5px; margin-top: 14px; }
.login-info { background: var(--blue-soft); color: var(--blue); border-radius: 8px; padding: 9px 12px; font-size: 12.5px; margin-top: 14px; }
.login-foot { margin-top: 18px; font-size: 11px; color: var(--muted); }
.keys { margin-top: 14px; background: #f6f8fb; border-radius: 8px; padding: 8px 10px; font-size: 10.5px; color: var(--muted); text-align: left; }
</style>
</head>
<body>
<div class="login-card">
    <div class="logo-ic">🧁</div>
    <h1><?php echo htmlspecialchars($brName); ?></h1>
    <div class="tag">Fresh Cakes • Sweet Moments — POS System</div>
    <?php if (!empty($brAddr)): ?><div class="addr">📍 <?php echo htmlspecialchars($brAddr); ?></div><?php endif; ?>

    <?php if ($error): ?><div class="login-err">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?><div class="login-info">⏰ Session expired after 8 hours — please login again.</div><?php endif; ?>

    <form method="POST">
        <div class="fld">
            <label>👤 Username</label>
            <input class="inp" type="text" name="user" placeholder="Enter username" required autofocus>
        </div>
        <div class="fld">
            <label>🔒 Password</label>
            <input class="inp" type="password" name="mpass" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn-login">🔐 LOGIN</button>
    </form>

    <div class="keys">⌨ Shortcuts after login: <b>F1</b> help · <b>F2</b> search items · <b>F4</b> customer · <b>F9</b> confirm · <b>F10</b> hold</div>
    <div class="login-foot">BestPOS • Bakery &amp; Sweets Order Management</div>
</div>
</body>
</html>
