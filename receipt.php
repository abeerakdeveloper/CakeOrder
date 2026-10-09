<?php
require_once 'db.php';
require_once 'order_lines.php';
require_once 'company.php';

$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) { header('Location: order_list.php'); exit; }

$res = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 ORDER BY id");
$items = array();
while ($r = mysqli_fetch_assoc($res)) $items[] = $r;

if (empty($items)) { die("Not found"); }

$first = $items[0];
$totalAmount = 0;
foreach ($items as $i) $totalAmount += $i['amount'];
$netTotal = $totalAmount - $first['flat_disc'];
// Sweet boxes are priced after weighing
$hasSweetRow = false; $hasWeighedRow = false;
foreach ($items as $i) { if ($i['sale_type'] === 'sweet') $hasSweetRow = true; if ($i['sale_type'] === 'weighed') $hasWeighedRow = true; }
$waitingWeigh = $hasSweetRow && !$hasWeighedRow;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt #<?php echo $billNo; ?></title>
<style>
body { font-family: 'Courier New', monospace; max-width: 350px; margin: 20px auto; color: #333; }
.receipt { border: 1px dashed #999; padding: 20px; }
.center { text-align: center; }
.line { border-top: 1px dashed #999; margin: 10px 0; }
.row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; }
.bold { font-weight: bold; }
.big { font-size: 16px; }
.no-print { margin-top: 20px; text-align: center; }
@media print { .no-print { display: none; } body { margin: 0; } }
</style>
</head>
<body>

<div class="receipt">
    
    <?php $branch = getBranchInfo(); ?>
	<div class="center">
		<?php if (company_logo_exists()): ?>
		<img src="clogo.png" alt="" style="max-width:200px;max-height:80px;">
		<?php else: ?>
		<h2><?php echo htmlspecialchars(strtoupper($branch['name'])); ?></h2>
		<?php endif; ?>
		<?php if (!empty($branch['address'])): ?>
		<p style="font-size:11px;margin:4px 0;line-height:1.3;"><?php echo htmlspecialchars($branch['address']); ?></p>
		<?php endif; ?>
	</div>
    
    <div class="line"></div>
    <div class="row"><span>Bill No:</span><strong>#<?php echo $billNo; ?></strong></div>
    <div class="row"><span>Date:</span><span><?php echo date('d/m/Y', strtotime($first['inv_date'])); ?></span></div>
    <div class="row"><span>Customer:</span><span><?php echo htmlspecialchars($first['party_detail']); ?></span></div>
    <div class="row"><span>Phone:</span><span><?php echo htmlspecialchars($first['cell_no']); ?></span></div>
    <div class="row"><span>Delivery:</span><span><?php echo date('d/m/Y', strtotime($first['deliver_date'])); ?> <?php echo $first['delivery_time']; ?></span></div>
    
    <div class="line"></div>
    <div class="row bold"><span>Item</span><span>Qty</span><span>Amount</span></div>
    <div class="line"></div>
    
    <?php foreach ($items as $item): ?>
    <div class="row">
        <span style="flex:2;"><?php echo htmlspecialchars($item['category']); ?>
            <?php if ($item['flavor']): ?><br><small>(<?php echo $item['flavor']; ?>)</small><?php endif; ?><?php if ($item['sale_type'] === null || $item['sale_type'] === 'cake'): ?>  <small>(<?php echo ot_clean_number($item['tiers']); ?> <?php echo htmlspecialchars($item['uom']); ?>)</small><?php endif; ?>      </span>
        <span style="flex:0.5;text-align:center;"><?php echo $item['qty']; ?></span>
        <span style="flex:1;text-align:right;">Rs.<?php echo number_format($item['amount']); ?></span>
    </div>
    <?php endforeach; ?>
    
    <div class="line"></div>
    <div class="row"><span>Subtotal:</span><span>Rs. <?php echo number_format($totalAmount); ?></span></div>
    <?php if ($first['flat_disc'] > 0): ?>
    <div class="row"><span>Discount:</span><span>- Rs. <?php echo number_format($first['flat_disc']); ?></span></div>
    <?php endif; ?>
    <div class="row bold big"><span>Total:</span><span>Rs. <?php echo number_format($netTotal); ?></span></div>
    
    <div class="line"></div>
    <div class="row"><span>Advance:</span><span>Rs. <?php echo number_format($first['advance']); ?></span></div>
    <div class="row"><span>Paid:</span><span>Rs. <?php echo number_format($first['paid']); ?></span></div>
    <div class="row bold"><span>Balance:</span><span><?php if ($waitingWeigh): ?>After weighing<?php else: ?>Rs. <?php echo number_format(max(0, $netTotal - $first['advance'] - $first['paid'])); ?><?php endif; ?></span></div>
    <?php if ($first['payment_method']): ?>
    <div class="row"><span>Payment:</span><span><?php echo ucfirst($first['payment_method']); ?></span></div>
    <?php endif; ?>
    
    <div class="line"></div>
    <div class="center" style="font-size:11px;">
        <p>Status: <?php echo strtoupper($first['status']); ?></p>
        <p>Served by: <?php echo htmlspecialchars($first['user']); ?></p>
        <p><?php echo date('d/m/Y h:i A'); ?></p>
        <p style="margin-top:10px;">Thank you for your order!</p>
		<p><?php echo htmlspecialchars($COMPANY['name']); ?></p>
    </div>
</div>

<div class="no-print">
    <button onclick="window.print()" style="padding:12px 24px;background:#6c3483;color:#fff;border:none;border-radius:8px;cursor:pointer;"> Print</button>
    <a href="order_list.php" style="padding:12px 24px;background:#fff;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:#555;margin-left:8px;">← Back</a>
    <a href="index.php" style="padding:12px 24px;background:#27ae60;color:#fff;border-radius:8px;text-decoration:none;margin-left:8px;">+ New Order</a>
</div>
</body>
</html>