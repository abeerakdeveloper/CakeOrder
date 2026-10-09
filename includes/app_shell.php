<?php
/**
 * New clean app shell — topbar + sidebar.
 * Include AFTER db.php. Set $pageTitle and $pageKey before including.
 * Page then outputs its content and finishes with includes/app_footer.php
 */
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$shellBranch = getBranchInfo();
$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';
$pageKey   = isset($pageKey) ? $pageKey : '';
$shellInitial = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $_SESSION['user']) ?: 'U', 0, 1));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars($shellBranch['name']); ?></title>
<?php if (!empty($preCss)): foreach ((array)$preCss as $css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
<?php endforeach; endif; ?>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>

<div class="app-topbar">
    <div class="app-brand">
        <div class="logo">🧁</div>
        <div>
            <h1><?php echo htmlspecialchars($shellBranch['name']); ?></h1>
            <small>Fresh Cakes &bull; Sweet Moments</small>
        </div>
    </div>

    <form class="app-topsearch" action="order_list.php" method="get">
        <span class="ic">🔍</span>
        <input type="text" name="search" placeholder="Search orders, customers...">
    </form>

    <div class="app-top-right">
        <span class="app-chip"><?php echo htmlspecialchars(getRoleName()); ?></span>
        <span class="app-avatar"><?php echo $shellInitial; ?></span>
        <span class="app-chip"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
        <a class="app-btn-logout" href="logout.php">⎋ Logout</a>
    </div>
</div>

<div class="app-layout">
    <nav class="app-sidebar">
        <div class="grp">Overview</div>
        <a class="app-nav-item <?php echo $pageKey=='dashboard'?'active':''; ?>" href="dashboard.php"><span class="ic">📊</span> Dashboard</a>
        <a class="app-nav-item <?php echo $pageKey=='orders'?'active':''; ?>" href="order_list.php"><span class="ic">🔍</span> Search Orders</a>
        <a class="app-nav-item <?php echo $pageKey=='status'?'active':''; ?>" href="order_status.php"><span class="ic">🕐</span> Check Status</a>

        <div class="grp">New Order</div>
        <a class="app-nav-item <?php echo $pageKey=='cake'?'active':''; ?>" href="order_cake.php"><span class="ic">🎂</span> Cake Order</a>
        <a class="app-nav-item <?php echo $pageKey=='lunch'?'active':''; ?>" href="order_box.php?type=lunchbox"><span class="ic">🍱</span> Lunch Box</a>
        <a class="app-nav-item <?php echo $pageKey=='sweets'?'active':''; ?>" href="order_box.php?type=sweetsbox"><span class="ic">🍬</span> Sweets Box</a>
        <a class="app-nav-item <?php echo $pageKey=='eatables'?'active':''; ?>" href="order_eatables.php"><span class="ic">🍽️</span> Eatables</a>
        <a class="app-nav-item <?php echo $pageKey=='pos'?'active':''; ?>" href="index.php"><span class="ic">🧾</span> Other (POS)</a>

        <?php if (isKitchen() || isAdmin()): ?>
        <div class="grp">Production</div>
        <a class="app-nav-item <?php echo $pageKey=='kitchen'?'active':''; ?>" href="kitchen_display.php"><span class="ic">👨‍🍳</span> Kitchen</a>
        <?php endif; ?>

        <?php if (isPOSUser() || isAdmin()): ?>
        <a class="app-nav-item <?php echo $pageKey=='pickup'?'active':''; ?>" href="pickup_queue.php"><span class="ic">🚚</span> Pickup Queue</a>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <div class="grp">Admin</div>
        <a class="app-nav-item <?php echo $pageKey=='payments'?'active':''; ?>" href="admin_payments.php"><span class="ic">💰</span> Payments</a>
        <a class="app-nav-item <?php echo $pageKey=='ledger'?'active':''; ?>" href="admin_ledger.php"><span class="ic">📒</span> GL Ledger</a>
        <?php endif; ?>

        <div class="love">
            <span class="big">🧁</span>
            Freshly Baked<br>With Love ♥
        </div>
    </nav>

    <main class="app-main">
