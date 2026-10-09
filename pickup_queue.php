<?php
require_once 'db.php';

$sql = "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time,
    SUM(amount) AS total, MAX(status) AS status, MAX(dateent) AS dateent,
    GROUP_CONCAT(category SEPARATOR ', ') AS items
    FROM cake_order 
    WHERE status IN ('ready', 'delivered') AND ordercancel = 0
    GROUP BY bill_no
    ORDER BY FIELD(MAX(status), 'ready', 'delivered'), deliver_date ASC";

$res = mysqli_query($mysqli, $sql);
$pickups = array();
$readyCount = 0;
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $pickups[] = $r;
        if ($r['status'] == 'ready') $readyCount++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Pickup Queue</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php $branch = getBranchInfo(); ?>
<div class="topbar" style="background:#27ae60;">
     <h2><?php echo htmlspecialchars($branch['name']); ?> — Pickup Queue</h2>
    <div class="topbar-right">
        <span>Ready: <?php echo $readyCount; ?></span>
        <a href="dashboard.php">Dashboard</a>
        <a href="order_list.php">Orders</a>
        <a href="kitchen_display.php">Kitchen</a>
    </div>
</div>

<div class="layout">
    <nav class="sidebar-nav">
       
		<div class="brand">
			<h3 style="font-size:15px;line-height:1.2;"><?php echo htmlspecialchars($branch['name']); ?></h3>
			<?php if (!empty($branch['address'])): ?>
			<small style="color:#888;font-size:10px;display:block;margin-top:4px;line-height:1.3;">
				<?php echo htmlspecialchars($branch['address']); ?>
			</small>
			<?php endif; ?>
			<small style="color:#6c3483;font-size:11px;display:block;margin-top:6px;font-weight:600;">
				Role: <?php echo getRoleName(); ?>
			</small>
		</div>


        <a href="dashboard.php"><span class="label">Dashboard</span></a>
        <a href="index.php"><span class="label">New Order</span></a>
        <a href="order_list.php"><span class="label">Order List</span></a>
        <a href="kitchen_display.php"><span class="label">Kitchen</span></a>
        <a href="pickup_queue.php" class="active"><span class="label">Pickup Queue</span></a>
    </nav>

    <div class="main-content">
        <?php if (empty($pickups)): ?>
        <div style="text-align:center;padding:80px;color:#999;">
            <h3>No orders ready for pickup</h3>
        </div>
        <?php endif; ?>

        <div class="pickup-grid">
            <?php foreach ($pickups as $o): ?>
            <div class="pickup-card <?php echo $o['status']=='ready' ? 'ready' : ''; ?>">
                <div class="order-num">#<?php echo $o['bill_no']; ?></div>
                <div class="customer"><?php echo htmlspecialchars($o['party_detail'] ? $o['party_detail'] : 'Walk-in'); ?></div>
                <div style="margin:8px 0;"><?php echo getStatusBadge($o['status']); ?></div>
                <div style="font-size:12px;color:#555;margin:8px 0;"><?php echo htmlspecialchars($o['items']); ?></div>
                <div class="time"><?php echo $o['delivery_time']; ?> | <?php echo date('d M', strtotime($o['deliver_date'])); ?></div>
                <div style="margin-top:12px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                    <?php if ($o['status'] == 'ready'): ?>
                    <button class="btn btn-sm btn-success" onclick="markDelivered(<?php echo $o['bill_no']; ?>)">Delivered</button>
                    <?php endif; ?>
                    <a href="payment.php?bill=<?php echo $o['bill_no']; ?>" class="btn btn-sm btn-primary">Pay</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>
<script>
function markDelivered(billNo) {
    if (!confirm('Mark #' + billNo + ' as delivered?')) return;
    fetch('update_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bill_no: billNo, status: 'delivered'})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) { showToast('Delivered!', 'success'); setTimeout(function(){ location.reload(); }, 600); }
        else showToast(res.message, 'error');
    });
}
function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + type + ' show';
    setTimeout(function(){ t.classList.remove('show'); }, 4000);
}
setInterval(function(){ location.reload(); }, 60000);
</script>
</body>
</html>