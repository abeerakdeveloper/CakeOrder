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
            $_SESSION['salesman_name'] = '';
            
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
html, body { height: 100%; }
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
    background: #f4f6fa;
}
.lg-wrap { display: flex; min-height: 100vh; }

/* ---------- left: brand panel ---------- */
.lg-brand {
    position: relative; flex: 1.15; min-height: 100vh;
    background: url('assets/login_bg.jpg') center 38% / cover no-repeat #12294a;
}
.lg-brand::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(200deg, rgba(11,26,48,.18) 0%, rgba(11,26,48,.55) 55%, rgba(9,21,40,.88) 100%);
}
.lg-brand-in {
    position: relative; z-index: 2; height: 100%;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 56px 60px; color: #fff;
}
.lg-mark { display: flex; align-items: center; gap: 14px; }
.lg-mark img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 14px rgba(0,0,0,.35); }
.lg-mark .nm { font-size: 24px; font-weight: 700; letter-spacing: .3px; line-height: 1.15; }
.lg-mark .sb { font-size: 12px; font-weight: 500; color: #cfd9e8; letter-spacing: 1.6px; text-transform: uppercase; margin-top: 2px; }
.lg-line { width: 54px; height: 3px; background: #d8a13c; border-radius: 2px; margin: 22px 0 18px; }
.lg-feats { list-style: none; margin: 0; padding: 0; font-size: 14px; color: #e6ecf5; }
.lg-feats li { padding: 5px 0 5px 22px; position: relative; }
.lg-feats li::before { content: ''; position: absolute; left: 0; top: 12px; width: 8px; height: 8px; border: 2px solid #d8a13c; border-radius: 2px; }
.lg-copy { margin-top: 34px; font-size: 11.5px; color: #9fb0c6; }

/* ---------- right: sign-in panel ---------- */
.lg-panel {
    flex: 1; display: flex; align-items: center; justify-content: center;
    padding: 40px 24px; background: #f4f6fa;
}
.lg-box { width: 100%; max-width: 372px; }
.lg-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: 2.2px; color: #1f6ff2; text-transform: uppercase; }
.lg-box h1 { margin: 10px 0 4px; font-size: 26px; font-weight: 700; color: #16283f; }
.lg-sub { font-size: 13px; color: #6b7a90; margin: 0 0 26px; line-height: 1.5; }
.lg-card {
    background: #fff; border: 1px solid #e3e9f2; border-radius: 10px;
    padding: 26px 26px 22px; box-shadow: 0 10px 30px rgba(20, 40, 70, .07);
}
.lg-card .fld { margin-bottom: 14px; }
.lg-card label { display: block; font-size: 12px; font-weight: 600; color: #3c4c62; margin-bottom: 6px; }
.lg-card .inp {
    width: 100%; padding: 10px 12px; font-size: 14px; color: #16283f;
    border: 1px solid #cfd8e4; border-radius: 7px; background: #fbfcfe; outline: none;
}
.lg-card .inp:focus { border-color: #1f6ff2; box-shadow: 0 0 0 3px rgba(31,111,242,.12); background: #fff; }
.lg-btn {
    width: 100%; padding: 12px; border: none; border-radius: 7px; cursor: pointer;
    background: #12294a; color: #fff; font-size: 14.5px; font-weight: 700; letter-spacing: .4px;
    margin-top: 4px; transition: background .15s;
}
.lg-btn:hover { background: #1f6ff2; }
.login-err { background: #fdecee; color: #c92a2f; border: 1px solid #f2c4c9; border-radius: 7px; padding: 9px 12px; font-size: 12.5px; margin: 0 0 14px; }
.login-info { background: #eaf1fe; color: #1a5fd0; border: 1px solid #c9dbfb; border-radius: 7px; padding: 9px 12px; font-size: 12.5px; margin: 0 0 14px; }
.lg-keys { margin-top: 16px; font-size: 11px; color: #8593a7; text-align: center; }
.lg-keys b { color: #55657c; }
.lg-foot { margin-top: 26px; text-align: center; font-size: 11px; color: #9aa7b8; }

@media (max-width: 940px) {
    .lg-brand { display: none; }
    .lg-panel { padding: 28px 18px; }
}
</style>
</head>
<body>
<div class="lg-wrap">

    <div class="lg-brand">
        <div class="lg-brand-in">
            <div class="lg-mark">
                <img src="assets/clogo.png" alt="logo">
                <div>
                    <div class="nm"><?php echo htmlspecialchars($brName); ?></div>
                    <div class="sb">Bakery &amp; Sweets POS</div>
                </div>
            </div>
            <div class="lg-line"></div>
            <ul class="lg-feats">
                <li>Cake, lunch box &amp; sweets order management</li>
                <li>Live kitchen display and pickup queue</li>
                <li>Branch-wise invoicing, advances and ledger</li>
            </ul>
            <div class="lg-copy">&copy; <?php echo date('Y'); ?> BestPOS — Bakery &amp; Sweets Order Management System</div>
        </div>
    </div>

    <div class="lg-panel">
        <div class="lg-box">
            <div class="lg-eyebrow">Staff Sign In</div>
            <h1>Welcome back</h1>
            <p class="lg-sub">
                <?php echo htmlspecialchars($brName); ?>
                <?php if (!empty($brAddr)): ?> — <?php echo htmlspecialchars($brAddr); ?><?php endif; ?>
            </p>

            <div class="lg-card">
                <?php if ($error): ?><div class="login-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (isset($_GET['timeout'])): ?><div class="login-info">Session expired after 8 hours — please login again.</div><?php endif; ?>

                <form method="POST">
                    <div class="fld">
                        <label>Username</label>
                        <input class="inp" type="text" name="user" placeholder="Enter username" required autofocus>
                    </div>
                    <div class="fld">
                        <label>Password</label>
                        <input class="inp" type="password" name="mpass" placeholder="Enter password" required>
                    </div>
                    <button type="submit" class="lg-btn">Sign In</button>
                </form>
            </div>

            <div class="lg-keys">Shortcuts after login: <b>F1</b> help · <b>F2</b> search · <b>F4</b> customer · <b>F9</b> confirm · <b>F10</b> hold</div>
            <div class="lg-foot">BestPOS • Bakery &amp; Sweets Order Management</div>
        </div>
    </div>

</div>
</body>
</html>
