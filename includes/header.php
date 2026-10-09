<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/../company.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="topbar">
    <h2><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h2>
    <div class="topbar-right">
        <span style="font-size:13px;background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:12px;">
            <?php echo getRoleName(); ?>
        </span>
        <span style="font-size:13px;"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
        <a href="logout.php" style="background:#e74c3c;padding:6px 12px;border-radius:6px;">Logout</a>
    </div>
</div>

<div class="layout">
    <?php $branch = getBranchInfo(); ?>
    <nav class="sidebar-nav">
        <div class="brand">
            <?php if (company_logo_exists()): ?>
            <?php echo company_logo_html('brand-logo'); ?>
            <?php else: ?>
            <h3 style="font-size:15px;line-height:1.2;"><?php echo htmlspecialchars($branch['name']); ?></h3>
            <?php endif; ?>
            <?php if (!empty($branch['address'])): ?>
            <small style="color:#888;font-size:10px;display:block;margin-top:4px;line-height:1.3;">
                <?php echo htmlspecialchars($branch['address']); ?>
            </small>
            <?php endif; ?>
            <small style="color:#6c3483;font-size:11px;display:block;margin-top:6px;font-weight:600;">
                Role: <?php echo getRoleName(); ?>
            </small>
        </div>

        <a href="dashboard.php" class="<?php echo $currentPage=='dashboard.php' ? 'active' : ''; ?>">
            <span class="label">Dashboard</span>
        </a>

        <?php if (isPOSUser() || isAdmin()): ?>
        <a href="index.php" class="<?php echo $currentPage=='index.php' ? 'active' : ''; ?>">
            <span class="label">New Order</span>
        </a>
        <?php endif; ?>

        <a href="order_list.php" class="<?php echo $currentPage=='order_list.php' ? 'active' : ''; ?>">
            <span class="label">Order List</span>
        </a>

        <a href="order_status.php" class="<?php echo $currentPage=='order_status.php' ? 'active' : ''; ?>">
            <span class="label">Check Status</span>
        </a>

        <?php if (isKitchen() || isAdmin()): ?>
        <a href="kitchen_display.php" class="<?php echo $currentPage=='kitchen_display.php' ? 'active' : ''; ?>">
            <span class="label">Kitchen</span>
        </a>
        <?php endif; ?>

        <?php if (isPOSUser() || isAdmin()): ?>
        <a href="pickup_queue.php" class="<?php echo $currentPage=='pickup_queue.php' ? 'active' : ''; ?>">
            <span class="label">Pickup Queue</span>
        </a>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <a href="admin_payments.php" class="<?php echo $currentPage=='admin_payments.php' ? 'active' : ''; ?>">
            <span class="label">Payments</span>
        </a>
        <a href="admin_users.php" class="<?php echo $currentPage=='admin_users.php' ? 'active' : ''; ?>" style="display:none;">
            <span class="label">Users</span>
        </a>
        <a href="admin_ledger.php" class="<?php echo $currentPage=='admin_ledger.php' ? 'active' : ''; ?>">
            <span class="label">GL Ledger</span>
        </a>
        <a href="cake_products.php" class="<?php echo $currentPage=='cake_products.php' ? 'active' : ''; ?>">
            <span class="label">Cake Products</span>
        </a>
        <?php endif; ?>

        <a href="logout.php" class="logout">
            <span class="label">Logout</span>
        </a>
    </nav>

    <div class="main-content">
