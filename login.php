<?php
session_start();

require_once 'db.php';
require_once 'company.php';

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
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login - BestPOS</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
body {
    background: #6c3483;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.login-container {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    width: 380px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.logo {
    text-align: center;
    margin-bottom: 30px;
}
.logo h1 {
    color: #6c3483;
    font-size: 28px;
    margin-bottom: 4px;
}
.logo p {
    color: #888;
    font-size: 13px;
}
.logo-mark {
    margin-bottom: 8px;
}
.login-logo {
    display: block;
    margin: 0 auto;
    max-height: 72px;
    max-width: 220px;
}
.login-logo-text {
    display: block;
    color: #6c3483;
    font-size: 20px;
    font-weight: bold;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #6c3483;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
}
.form-group input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e0d6eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}
.form-group input:focus {
    outline: none;
    border-color: #6c3483;
    box-shadow: 0 0 0 3px rgba(108,52,131,0.1);
}
.btn-login {
    width: 100%;
    padding: 14px;
    background: #6c3483;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}
.btn-login:hover { background: #7d3c98; }
.error {
    background: #fee;
    color: #c0392b;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 16px;
    border-left: 4px solid #e74c3c;
}
.footer {
    text-align: center;
    margin-top: 24px;
    font-size: 11px;
    color: #aaa;
}
.role-info {
    margin-top: 20px;
    padding: 12px;
    background: #f5f0fa;
    border-radius: 8px;
    font-size: 11px;
    color: #666;
}
.role-info strong { color: #6c3483; }
</style>
</head>
<body>

<div class="login-container">
    


	<?php
		// Get branch info for display
		$branchRes = mysqli_query($mysqli, "SELECT branch_name, address1 FROM branch LIMIT 1");
		$brName = 'BestPOS';
		$brAddr = '';
		if ($branchRes && $brRow = mysqli_fetch_assoc($branchRes)) {
			$brName = $brRow['branch_name'];
			$brAddr = $brRow['address1'];
		}
		?>

       <div class="logo">
			<div class="logo-mark"><?php echo company_logo_html('login-logo'); ?></div>
			<h1><?php echo htmlspecialchars($brName); ?></h1>
			<?php if (!empty($brAddr)): ?>
			<p style="font-size:12px;color:#888;line-height:1.4;"><?php echo htmlspecialchars($brAddr); ?></p>
			<?php endif; ?>
			<p style="margin-top:4px;">POS System</p>
		</div>
    
    <?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
		


    <form method="POST">
        <div class="form-group">
            <label>USERNAME</label>
            <input type="text" name="user" placeholder="Enter username" required autofocus>
        </div>
        <div class="form-group">
            <label>PASSWORD</label>
            <input type="password" name="mpass" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn-login">LOGIN</button>
    </form>
    
	<div style="
			background:#f5f7fa;
			border:2px solid #0d6efd;
			border-radius:10px;
			padding:20px;
			margin:20px auto;
			text-align:center;
			max-width:700px;
			box-shadow:0 2px 10px rgba(0,0,0,0.1);
		">

			<h2 style="
				margin-top:0;
				color:#0d6efd;
				font-family:Arial;
			">
				BestPOS Help & Training
			</h2>

			<p style="
				font-size:16px;
				color:#333;
				margin-bottom:20px;
			">
				New users can quickly access the presentation and training manual below.
			</p>

			<a href="https://ZeeSOL.co.uk/BestPOS/presentation/presentation.html"
			   target="_blank"
			   style="
					display:inline-block;
					background:#0d6efd;
					color:#fff;
					padding:12px 25px;
					margin:10px;
					text-decoration:none;
					border-radius:6px;
					font-size:16px;
					font-weight:bold;
			   ">
				View Presentation
			</a>

			<a href="https://ZeeSOL.co.uk/BestPOS/presentation/training_manual.html"
			   target="_blank"
			   style="
					display:inline-block;
					background:#198754;
					color:#fff;
					padding:12px 25px;
					margin:10px;
					text-decoration:none;
					border-radius:6px;
					font-size:16px;
					font-weight:bold;
			   ">
				Training Manual
			</a>

		</div>

    <div class="role-info" style="display:none;">
        <strong>User Roles:</strong><br>
        <strong>1</strong> = POS User (Take orders, view dashboard, cancel before ready)<br>
        <strong>2</strong> = Kitchen Staff (Manage cooking workflow)<br>
        <strong>3</strong> = Admin (Full access, modify/delete anything)
    </div>
    
    <div class="footer">
        © <?php echo date('Y'); ?> https://ZeeSOL.co.uk - All Rights Reserved
    </div>
</div>

</body>
</html>