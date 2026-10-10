<?php
/**
 * App body shell — navy topbar + sidebar + <main> opener.
 * Every page includes this (legacy pages keep their own <head>,
 * new pages use includes/app_shell.php which prints the head first).
 * Pages close with:  </main></div>   (or includes/app_footer.php)
 */
if (!isset($_SESSION)) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
$shellBranch = getBranchInfo();
$shellInitial = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $_SESSION['user']) !== '' ? preg_replace('/[^a-zA-Z]/', '', $_SESSION['user']) : 'U', 0, 1));
$shellTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';
function shellActive($file) { global $currentPage; return $currentPage === $file ? 'active' : ''; }
function shellActiveBox($t) { global $currentPage;
    return ($currentPage === 'order_box.php' && (isset($_GET['type']) ? $_GET['type'] : 'lunchbox') === $t) ? 'active' : ''; }
?>
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
        <a class="app-nav-item <?php echo shellActive('dashboard.php'); ?>" href="dashboard.php"><span class="ic">📊</span> Dashboard</a>
        <a class="app-nav-item <?php echo shellActive('order_list.php'); ?>" href="order_list.php"><span class="ic">🔍</span> Search Orders</a>
        <a class="app-nav-item <?php echo shellActive('order_status.php'); ?>" href="order_status.php"><span class="ic">🕐</span> Check Status</a>

        <div class="grp">New Order</div>
        <a class="app-nav-item <?php echo shellActive('order_cake.php'); ?>" href="order_cake.php"><span class="ic">🎂</span> Cake Order</a>
        <a class="app-nav-item <?php echo shellActiveBox('lunchbox'); ?>" href="order_box.php?type=lunchbox"><span class="ic">🍱</span> Lunch Box</a>
        <a class="app-nav-item <?php echo shellActiveBox('sweetsbox'); ?>" href="order_box.php?type=sweetsbox"><span class="ic">🍬</span> Sweets Box</a>
        <a class="app-nav-item <?php echo shellActive('order_eatables.php'); ?>" href="order_eatables.php"><span class="ic">🍽️</span> Eatables</a>
        <a class="app-nav-item <?php echo shellActive('index.php'); ?>" href="index.php"><span class="ic">🧾</span> Other (POS)</a>

        <?php if (isKitchen() || isAdmin()): ?>
        <div class="grp">Production</div>
        <a class="app-nav-item <?php echo shellActive('kitchen_display.php'); ?>" href="kitchen_display.php"><span class="ic">👨‍🍳</span> Kitchen</a>
        <?php endif; ?>

        <?php if (isPOSUser() || isAdmin()): ?>
        <a class="app-nav-item <?php echo shellActive('pickup_queue.php'); ?>" href="pickup_queue.php"><span class="ic">🚚</span> Pickup Queue</a>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <div class="grp">Admin</div>
        <a class="app-nav-item <?php echo shellActive('admin_payments.php'); ?>" href="admin_payments.php"><span class="ic">💰</span> Payments</a>
        <a class="app-nav-item <?php echo shellActive('admin_ledger.php'); ?>" href="admin_ledger.php"><span class="ic">📒</span> GL Ledger</a>
        <a class="app-nav-item <?php echo shellActive('admin_users.php'); ?>" href="admin_users.php"><span class="ic">👥</span> Users</a>
        <?php endif; ?>

        <div class="love">
            <span class="big">🧁</span>
            Freshly Baked<br>With Love ♥
        </div>
    </nav>

    <main class="app-main">
