<?php
require_once 'db.php';
require_once 'order_lines.php';
require_once 'order_store.php';


$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) { header('Location: order_list.php'); exit; }

// Admin override: corrects the customer, phone, date, time, priority, discount and status of the whole bill.
// The edit lock does not apply (that is its purpose), but every change is written to the change history.
// Cancelled orders are not changed here: use Cancel on the order list, which also refunds any payment.
function override_back($billNo, $message = '', $flag = '') {
    $url = 'order_detail.php?bill=' . intval($billNo);
    if ($message !== '') {
        $url .= '&err=' . urlencode($message);
    }
    if ($flag !== '') {
        $url .= '&' . $flag;
    }
    header('Location: ' . $url);
    exit;
}

function override_post($key) {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

$overrideStatuses = array('pending', 'confirmed', 'hold', 'preparing', 'ready', 'delivered', 'paid');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $rows = ot_load_bill($mysqli, $billNo);
    if (empty($rows)) {
        override_back($billNo, 'This order is cancelled, so it cannot be changed here.');
    }
    $first = $rows[0];
    $total = 0;
    $advance = 0;
    $paid = 0;
    foreach ($rows as $r) {
        $total += (int) round(ot_num($r['amount']));
        $advance = max($advance, (int) round(ot_num($r['advance'])));
        $paid = max($paid, (int) round(ot_num($r['paid'])));
    }
    $oldDisc = max(0, (int) round(ot_num($first['flat_disc'])));
    $oldHeader = array(
        'party_detail' => (string) $first['party_detail'],
        'cell_no' => (string) $first['cell_no'],
        'deliver_date' => substr((string) $first['deliver_date'], 0, 10),
        'delivery_time' => (string) $first['delivery_time'],
        'priority' => (string) $first['priority'],
        'flat_disc' => $oldDisc,
    );
    $newPriority = in_array(override_post('priority'), array('normal', 'urgent', 'vip'), true)
        ? override_post('priority') : $oldHeader['priority'];
    $newHeader = array(
        'party_detail' => override_post('party_detail'),
        'cell_no' => override_post('cell_no'),
        'deliver_date' => substr(override_post('deliver_date'), 0, 10),
        'delivery_time' => override_post('delivery_time'),
        'priority' => $newPriority,
        'flat_disc' => max(0, (int) round(ot_num(override_post('flat_disc')))),
    );
    $newStatus = override_post('status');
    if ($newStatus === 'cancelled') {
        override_back($billNo, 'To cancel an order, use Cancel on the order list. It also refunds any payment.');
    }
    if (!in_array($newStatus, $overrideStatuses, true)) {
        override_back($billNo, 'Choose a valid status.');
    }

    // The discount may not make the total negative, or drop it below what is already received
    $oldDue = $total - $oldDisc;
    $newDue = $total - $newHeader['flat_disc'];
    if ($newHeader['flat_disc'] > $oldDisc) {
        if ($newDue < 0) {
            override_back($billNo, 'The discount is more than the total.');
        }
        if ($newDue < $advance + $paid) {
            override_back($billNo, 'The new total, Rs. ' . ot_money($newDue) . ', would be less than the Rs. '
                . ot_money($advance + $paid) . ' already received.');
        }
    }

    $lines = ot_header_changes($oldHeader, $newHeader);
    if ($newStatus !== (string) $first['status']) {
        $lines[] = 'Status: ' . (string) $first['status'] . ' changed to ' . $newStatus;
    }
    if (empty($lines)) {
        override_back($billNo, '', 'nochange=1');
    }

    mysqli_autocommit($mysqli, false);
    try {
        $sql = "UPDATE cake_order SET party_detail = " . ot_sql_str($newHeader['party_detail'])
            . ", cell_no = " . ot_sql_str($newHeader['cell_no'])
            . ", deliver_date = " . ot_sql_str($newHeader['deliver_date'])
            . ", delivery_time = " . ot_sql_str($newHeader['delivery_time'])
            . ", priority = " . ot_sql_str($newHeader['priority'])
            . ", flat_disc = " . intval($newHeader['flat_disc'])
            . ", status = " . ot_sql_str($newStatus)
            . ", return_date = NOW()"
            . " WHERE bill_no = " . intval($billNo) . " AND ordercancel = 0";
        if (!mysqli_query($mysqli, $sql)) {
            throw new Exception('The order could not be updated.');
        }
        ot_write_log($mysqli, $billNo, $_SESSION['user'], $oldDue, $newDue,
            implode("\n", array_merge(array('Admin override'), $lines)));
        mysqli_commit($mysqli);
        mysqli_autocommit($mysqli, true);
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        mysqli_autocommit($mysqli, true);
        override_back($billNo, 'The change could not be saved. Nothing was changed.');
    }
    override_back($billNo, '', 'saved=1');
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
if (!$glRes) {
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
</head>
<body>

<?php include 'includes/header.php'; ?>

        <?php if (isset($_GET['saved'])): ?>
        <div style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:16px;">Saved.</div>
        <?php endif; ?>

        <?php if (isset($_GET['nochange'])): ?>
        <div style="background:#eef2f7;color:#34495e;padding:12px;border-radius:8px;margin-bottom:16px;">No changes to save.</div>
        <?php endif; ?>

        <?php if (isset($_GET['err'])): ?>
        <div style="background:#fee;color:#c0392b;padding:12px;border-radius:8px;margin-bottom:16px;"><?php echo htmlspecialchars($_GET['err']); ?></div>
        <?php endif; ?>

        <?php if ($first['ordercancel']): ?>
        <div style="background:#fee;color:#c0392b;padding:16px;border-radius:8px;margin-bottom:16px;border-left:5px solid #e74c3c;">
            <strong>This order is cancelled.</strong>
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
                                <?php foreach(array('pending','confirmed','hold','preparing','ready','delivered','paid') as $s): ?>
                                <option value="<?php echo $s; ?>" <?php if($first['status']==$s) echo 'selected'; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:#888;">To cancel an order, use Cancel on the order list.</small></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:12px;">Save changes</button>
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
                    <a href="payment.php?bill=<?php echo $billNo; ?>" class="btn btn-success">Receive payment</a>
                    <?php endif; ?>
                    <?php if (!$first['ordercancel'] && ot_editable_status($first['status']) && (isPOSUser() || isAdmin())): ?>
                    <a href="index.php?bill=<?php echo $billNo; ?>" class="btn btn-outline">Edit order</a>
                    <?php endif; ?>
                    <a href="receipt.php?bill=<?php echo $billNo; ?>" class="btn btn-info">Print receipt</a>
                    <?php 
                    $canCancel = false;
                    if (isAdmin()) $canCancel = !$first['ordercancel'];
                    else if (isPOSUser()) $canCancel = !$first['ordercancel'] && $first['paid'] == 0 && !in_array($first['status'], array('ready','delivered','paid'));
                    if ($canCancel): ?>
                    <button class="btn btn-danger" onclick="cancelThis()">Cancel order</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ORDER ITEMS -->
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

        <!-- CHANGE HISTORY: each saved edit, with who, when, the totals and what changed -->
        <?php $changeLog = ot_load_log($mysqli, $billNo); ?>
        <div class="data-card" style="margin-top:16px;">
            <h4>Change history</h4>
            <?php if (empty($changeLog)): ?>
            <p class="muted">No changes have been recorded for this order.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>When</th><th>By</th><th>Total before</th><th>Total after</th><th>What changed</th></tr></thead>
                <tbody>
                <?php foreach ($changeLog as $ch): ?>
                <tr>
                    <td><?php echo date('d M Y, h:i A', strtotime($ch['changed_at'])); ?></td>
                    <td><?php echo htmlspecialchars($ch['changed_by']); ?></td>
                    <td>Rs. <?php echo number_format((float) $ch['old_total']); ?></td>
                    <td>Rs. <?php echo number_format((float) $ch['new_total']); ?></td>
                    <td><small><?php echo nl2br(htmlspecialchars($ch['details'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- GL ENTRIES -->
        <?php if (!empty($glEntries)): ?>
        <div class="data-card" style="margin-top:16px;">
            <h4>Payment history</h4>
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
                            <button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $g['id']; ?>, <?php echo $g['amount']; ?>)">Delete</button>
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
							<button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $g['id']; ?>, <?php echo $g['amount']; ?>)">Delete</button>
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