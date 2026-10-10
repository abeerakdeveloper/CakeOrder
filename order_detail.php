<?php
require_once 'db.php';
// TEMPORARY DEBUG - Remove after fixing
error_reporting(E_ALL);
ini_set('display_errors', 1);


$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) { header('Location: order_list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $partyDetail = esc($_POST['party_detail']);
    $cellNo = esc($_POST['cell_no']);
    $deliverDate = esc($_POST['deliver_date']);
    $deliveryTime = esc($_POST['delivery_time']);
    $priority = esc($_POST['priority']);
    $flatDisc = intval($_POST['flat_disc']);
    $newStatus = esc($_POST['status']);

    mysqli_query($mysqli, "UPDATE cake_order SET 
        party_detail='$partyDetail', cell_no='$cellNo', deliver_date='$deliverDate', 
        delivery_time='$deliveryTime', priority='$priority', flat_disc=$flatDisc, 
        status='$newStatus', return_date=NOW()
        WHERE bill_no = $billNo");
    header("Location: order_detail.php?bill=$billNo&saved=1");
    exit;
}

$res = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo ORDER BY id");
if (!$res) {
    die("SQL Error: " . mysqli_error($mysqli));
}
$items = array();
while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
if (empty($items)) { die("Order not found"); }

$first = $items[0];
$totalAmount = 0;
foreach ($items as $it) $totalAmount += $it['amount'];

// Get GL entries for this order
$glRes = mysqli_query($mysqli, "SELECT * FROM gledg WHERE ref_no = $billNo ORDER BY gledg_id DESC");
if (!$res) {
    die("SQL Error: " . mysqli_error($mysqli));
}

$glEntries = array();
while ($r = mysqli_fetch_assoc($glRes)) $glEntries[] = $r;

$pageTitle = "Order #$billNo Details";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Order #<?php echo $billNo; ?></title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

        <?php if (isset($_GET['saved'])): ?>
        <div style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:16px;">✅ Saved!</div>
        <?php endif; ?>

        <?php if ($first['ordercancel']): ?>
        <div style="background:#fee;color:#c0392b;padding:16px;border-radius:8px;margin-bottom:16px;border-left:5px solid #e74c3c;">
            ❌ <strong>This order is CANCELLED</strong>
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="data-card">
                <h4>Order Information 
                    <?php if(isAdmin()) echo '<small style="color:#e74c3c;">(Admin: Editable)</small>'; ?>
                </h4>
                <?php if (isAdmin()): ?>
                <form method="POST" style="margin-top:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div><label style="font-size:11px;font-weight:600;color:#6c3483;">Customer</label>
                            <input type="text" name="party_detail" value="<?php echo htmlspecialchars($first['party_detail']); ?>" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;"></div>
                        <div><label style="font-size:11px;font-weight:600;color:#6c3483;">Phone</label>
                            <input type="text" name="cell_no" value="<?php echo htmlspecialchars($first['cell_no']); ?>" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;"></div>
                        <div><label style="font-size:11px;font-weight:600;color:#6c3483;">Delivery Date</label>
                            <input type="date" name="deliver_date" value="<?php echo $first['deliver_date']; ?>" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;"></div>
                        <div><label style="font-size:11px;font-weight:600;color:#6c3483;">Delivery Time</label>
                            <input type="text" name="delivery_time" value="<?php echo $first['delivery_time']; ?>" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;"></div>
                        <div><label style="font-size:11px;font-weight:600;color:#6c3483;">Priority</label>
                            <select name="priority" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;">
                                <?php foreach (array('normal','urgent','vip') as $p): ?>
                                <option value="<?php echo $p; ?>" <?php if($first['priority']==$p) echo 'selected'; ?>><?php echo ucfirst($p); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div><label style="font-size:11px;font-weight:600;color:#6c3483;">Discount</label>
                            <input type="number" name="flat_disc" value="<?php echo $first['flat_disc']; ?>" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;"></div>
                        <div style="grid-column:span 2;"><label style="font-size:11px;font-weight:600;color:#6c3483;">Status (Admin Override)</label>
                            <select name="status" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;">
                                <?php foreach(array('pending','confirmed','hold','preparing','ready','delivered','paid','cancelled') as $s): ?>
                                <option value="<?php echo $s; ?>" <?php if($first['status']==$s) echo 'selected'; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:12px;">💾 Save Changes</button>
                </form>
                <?php else: ?>
                <table style="margin-top:12px;">
                    <tr><td><strong>Customer:</strong></td><td><?php echo htmlspecialchars($first['party_detail']); ?></td></tr>
                    <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($first['cell_no']); ?></td></tr>
                    <tr><td><strong>Delivery:</strong></td><td><?php echo date('d M Y', strtotime($first['deliver_date'])); ?> at <?php echo $first['delivery_time']; ?></td></tr>
                    <tr><td><strong>Status:</strong></td><td><?php echo getStatusBadge($first['status']); ?></td></tr>
                    <tr><td><strong>Priority:</strong></td><td><?php echo ucfirst($first['priority']); ?></td></tr>
                    <tr><td><strong>Order By:</strong></td><td><?php echo htmlspecialchars($first['order_taker']); ?></td></tr>
                </table>
                <?php endif; ?>
            </div>

            <div class="data-card">
                <h4>Payment Summary</h4>
                <div class="pay-row"><span>Subtotal:</span><strong>Rs. <?php echo number_format($totalAmount); ?></strong></div>
                <div class="pay-row"><span>Discount:</span><span>Rs. <?php echo number_format($first['flat_disc']); ?></span></div>
                <div class="pay-row" style="font-size:16px;font-weight:bold;border-top:2px solid #6c3483;padding-top:8px;">
                    <span>Net:</span><span>Rs. <?php echo number_format($totalAmount - $first['flat_disc']); ?></span></div>
                <div class="pay-row"><span>Advance:</span><span>Rs. <?php echo number_format($first['advance']); ?></span></div>
                <div class="pay-row"><span>Paid:</span><span>Rs. <?php echo number_format($first['paid']); ?></span></div>
                <?php $bal = $totalAmount - $first['flat_disc'] - $first['advance'] - $first['paid']; ?>
                <div class="pay-row" style="font-size:16px;font-weight:bold;color:#e74c3c;">
                    <span>Balance:</span><span>Rs. <?php echo number_format(max(0, $bal)); ?></span>
                </div>

                <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                    <?php if (!$first['ordercancel'] && $bal > 0 && in_array($first['status'], array('ready','delivered'))): ?>
                    <a href="payment.php?bill=<?php echo $billNo; ?>" class="btn btn-success">💰 Receive Payment</a>
                    <?php endif; ?>
                    <a href="receipt.php?bill=<?php echo $billNo; ?>" class="btn btn-info">🖨 Print</a>
                    <?php 
                    $canCancel = false;
                    if (isAdmin()) $canCancel = !$first['ordercancel'];
                    else if (isPOSUser()) $canCancel = !$first['ordercancel'] && $first['paid'] == 0 && !in_array($first['status'], array('ready','delivered','paid'));
                    if ($canCancel): ?>
                    <button class="btn btn-danger" onclick="cancelThis()">✕ Cancel</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ORDER ITEMS -->
        <?php
        $extraImgs = array();
        $er = @mysqli_query($mysqli, "SELECT id FROM order_extra_images WHERE bill_no = " . intval($billNo) . " ORDER BY id");
        if ($er) while ($e = mysqli_fetch_assoc($er)) $extraImgs[] = $e['id'];
        if (!empty($extraImgs)): ?>
        <div class="data-card" style="margin-top:16px;">
            <h4>📷 More Order Photos (<?php echo count($extraImgs); ?>)</h4>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach ($extraImgs as $x): ?>
                <a href="show_image.php?type=extra&id=<?php echo $x; ?>" target="_blank">
                    <img src="show_image.php?type=extrathumb&id=<?php echo $x; ?>" style="width:90px;height:70px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="data-card" style="margin-top:16px;">
            <h4>Order Items (<?php echo count($items); ?>)</h4>
            <table>
                <thead>
                    <tr><th>#</th><th>Image</th><th>Item</th><th>Flavor</th><th>Shape</th><th>Weight</th><th>Qty</th><th>Rate</th><th>Amount</th><th>Message</th><th>Audio</th></tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <?php if (!empty($item['thumb_data'])): ?>
                            <img src="show_image.php?type=thumb&id=<?php echo $item['id']; ?>" style="width:40px;height:40px;border-radius:4px;cursor:pointer;" 
                                 onclick="window.open('show_image.php?type=image&id=<?php echo $item['id']; ?>')">
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($item['category']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['flavor']); ?></td>
                        <td><?php echo htmlspecialchars($item['shape']); ?></td>
                        <td><?php echo $item['tiers']; ?> <?php echo $item['uom']; ?></td>
                        
                        <td><?php echo $item['qty']; ?></td>
                        <td>Rs. <?php echo number_format($item['retail_price']); ?></td>
                        <td><strong>Rs. <?php echo number_format($item['amount']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['cake_message']); ?></td>
                        <td>
                            <?php if (!empty($item['audio_data'])): ?>
                            <audio controls style="height:30px;width:120px;"><source src="show_image.php?type=audio&id=<?php echo $item['id']; ?>"></audio>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- GL ENTRIES -->
        <?php if (!empty($glEntries)): ?>
        <div class="data-card" style="margin-top:16px;">
            <h4>📒 GL Entries / Payment History</h4>
            <!-- <table>
                <thead>
                    <tr><th>ID</th><th>VNo</th><th>Date</th><th>Type</th><th>Amount</th><th>Description</th><th>User</th><?php if(isAdmin()) echo '<th>Action</th>'; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($glEntries as $g): ?>
                    <tr>
                        <td><?php echo $g['id']; ?></td>
            						<td><strong><?php echo $g['vno']; ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($g['date'])); ?></td>
                        <td>
                            <?php 
                            $tn = array('CR' => 'Cash', 'BR' => 'Bank', 'ADJ' => 'Adjustment');
                            $tc = array('CR' => '#27ae60', 'BR' => '#3498db', 'ADJ' => '#f39c12');
                            ?>
                            <span style="background:<?php echo isset($tc[$g['amt_type']]) ? $tc[$g['amt_type']] : '#999'; ?>;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;">
                                <?php echo $g['amt_type']; ?>
                            </span>
                        </td>
                        <td><strong style="color:<?php echo $g['amount']<0 ? '#e74c3c' : '#27ae60'; ?>;">Rs. <?php echo number_format(abs($g['amount'])); ?></strong></td>
                        <td><small><?php echo htmlspecialchars($g['desc']); ?></small></td>
                        <td><?php echo htmlspecialchars($g['user']); ?></td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $g['id']; ?>, <?php echo $g['amount']; ?>)">🗑</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table> -->

			<table>
				<thead>
					<tr>
						<!-- <th>ID</th> --><th>V.No</th><th>V.Type</th><th>Date</th>
						<th>Trans</th><th>Amount</th><th>Description</th><th>User</th>
						<?php if(isAdmin()) echo '<th>Action</th>'; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($glEntries as $g): 
						$vTypeColors = array('CR'=>'#27ae60','BR'=>'#3498db','CP'=>'#e67e22','BP'=>'#9b59b6');
						$vTypeLabels = array('CR'=>'Cash Rcpt','BR'=>'Bank Rcpt','CP'=>'Cash Pay','BP'=>'Bank Pay');
						$vt = $g['v_type'];
						$vColor = isset($vTypeColors[$vt]) ? $vTypeColors[$vt] : '#999';
						$vLabel = isset($vTypeLabels[$vt]) ? $vTypeLabels[$vt] : $vt;
						$isRefund = ($g['amt_type'] == 'DR');
					?>
					<tr style="<?php echo $isRefund ? 'background:#fff5f5;' : ''; ?>">
						<!-- <td><?php echo $g['gledg_id']; ?></td> -->
						<td><strong style="color:#6c3483;"><?php echo $g['vno']; ?></strong></td>
						<td>
							<span style="background:<?php echo $vColor; ?>;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;">
								<?php echo $vt; ?>
							</span>
							<br><small style="color:#888;font-size:10px;"><?php echo $vLabel; ?></small>
						</td>
						<td><?php echo date('d/m/Y', strtotime($g['date'])); ?></td>
						<td>
							<?php if ($isRefund): ?>
							<span style="background:#e74c3c;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">DR-Refund</span>
							<?php else: ?>
							<span style="background:#27ae60;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">CR-Receipt</span>
							<?php endif; ?>
						</td>
						<td><strong style="color:<?php echo $isRefund ? '#e74c3c' : '#27ae60'; ?>;font-size:14px;">
							<?php echo $isRefund ? '−' : '+'; ?> Rs. <?php echo number_format(abs($g['amount'])); ?>
						</strong></td>
						<td><small><?php echo htmlspecialchars($g['desc']); ?></small></td>
						<td><?php echo htmlspecialchars($g['user']); ?><br>
							<?php if ($g['dateent']): ?>
							<small><?php echo date('h:i A', strtotime($g['dateent'])); ?></small>
							<?php endif; ?>
						</td>
						<?php if (isAdmin()): ?>
						<td>
							<button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $g['id']; ?>, <?php echo $g['amount']; ?>)">🗑</button>
						</td>
						<?php endif; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function cancelThis() {
    var reason = prompt('Reason:');
    if (reason === null) return;
    fetch('cancel_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bill_no: <?php echo $billNo; ?>, reason: reason})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) { alert('Cancelled'); location.reload(); }
        else alert(res.message);
    });
}

function deletePayment(id, amount) {
    if (!confirm('Delete payment of Rs. ' + Math.abs(amount).toLocaleString() + '?')) return;
    var reason = prompt('Reason:');
    if (!reason) return;
    fetch('admin_delete_payment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, reason: reason})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) { alert('Deleted'); location.reload(); }
        else alert(res.message);
    });
}
</script>
</body>
</html>