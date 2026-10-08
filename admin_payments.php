<?php
require_once 'db.php';
requireRole(array(3)); // Admin only

$dateFilter = isset($_GET['date']) ? esc($_GET['date']) : date('Y-m-d');
$typeFilter = isset($_GET['type']) ? esc($_GET['type']) : '';

//$where = "WHERE acc_code = '112000001'";
//if ($dateFilter) $where .= " AND date = '$dateFilter'";
//if ($typeFilter) $where .= " AND amt_type = '$typeFilter'";

$where = "WHERE acc_code = '112000001'";
if ($dateFilter) $where .= " AND date = '$dateFilter'";
if ($typeFilter) $where .= " AND v_type = '$typeFilter'";


$sql = "SELECT g.*, co.party_detail, co.cell_no
        FROM gledg g
        LEFT JOIN cake_order co ON co.bill_no = g.bill_no
        $where
        GROUP BY g.id
        ORDER BY g.id DESC LIMIT 200";

$res = mysqli_query($mysqli, $sql);
$payments = array();
/*
$totalCash = 0; $totalBank = 0; $totalRefund = 0;
while ($r = mysqli_fetch_assoc($res)) {
    $payments[] = $r;
    if ($r['amount'] < 0) $totalRefund += abs($r['amount']);
    else if ($r['amt_type'] == 'CR') $totalCash += $r['amount'];
    else if ($r['amt_type'] == 'BR') $totalBank += $r['amount'];
}
*/

$totalCash = 0; $totalBank = 0; $totalRefund = 0;
while ($r = mysqli_fetch_assoc($res)) {
    $payments[] = $r;
    if ($r['amt_type'] == 'DR') {
        $totalRefund += floatval($r['amount']);
    } else if ($r['v_type'] == 'CR') {
        $totalCash += floatval($r['amount']);
    } else if ($r['v_type'] == 'BR') {
        $totalBank += floatval($r['amount']);
    }
}

$pageTitle = 'Payments Management';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payments Management - Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

        <div class="stats-row" style="grid-template-columns:repeat(3,1fr);">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d4edda;">💵</div>
                <div class="stat-info">
                    <h2 style="color:#27ae60;">Rs. <?php echo number_format($totalCash); ?></h2>
                    <p>Cash Received</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;">🏦</div>
                <div class="stat-info">
                    <h2 style="color:#3498db;">Rs. <?php echo number_format($totalBank); ?></h2>
                    <p>Bank/Card Received</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee;">↩️</div>
                <div class="stat-info">
                    <h2 style="color:#e74c3c;">Rs. <?php echo number_format($totalRefund); ?></h2>
                    <p>Refunds</p>
                </div>
            </div>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display:flex;gap:10px;align-items:center;">
                <label>Date:</label>
                <input type="date" name="date" value="<?php echo $dateFilter; ?>">
                <label>Type:</label>
                <!-- <select name="type">
                    <option value="">All</option>
                    <option value="CR" <?php if($typeFilter=='CR') echo 'selected'; ?>>Cash (CR)</option>
                    <option value="BR" <?php if($typeFilter=='BR') echo 'selected'; ?>>Bank/Card (BR)</option>
                </select> -->

				<select name="type">
				<option value="">All Voucher Types</option>
				<option value="CR" <?php if($typeFilter=='CR') echo 'selected'; ?>>CR - Cash Receipt</option>
				<option value="BR" <?php if($typeFilter=='BR') echo 'selected'; ?>>BR - Bank Receipt</option>
				<option value="CP" <?php if($typeFilter=='CP') echo 'selected'; ?>>CP - Cash Payment</option>
				<option value="BP" <?php if($typeFilter=='BP') echo 'selected'; ?>>BP - Bank Payment</option>
			</select>
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                <a href="admin_payments.php" class="btn btn-outline btn-sm">Reset</a>
            </form>
        </div>

        <div class="data-card">
            <h4>Payment Transactions (Acc: 112000001)</h4>
            <table>
                <thead>
					<tr>
						<th>ID</th><th>V.No</th><th>Date</th><th>Ref/Bill #</th><th>Customer</th>
						<th>V.Type</th><th>Trans</th><th>Amount</th><th>Description</th>
						<th>User</th><th>Action</th>
					</tr>
				</thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="9" style="text-align:center;color:#999;padding:40px;">No payments found</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                    <!-- <tr style="<?php echo $p['amount']<0 ? 'background:#fff5f5;' : ''; ?>">
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($p['date'])); ?></td>
                        <td>
                            <?php if ($p['bill_no']): ?>
                            <a href="order_detail.php?bill=<?php echo $p['bill_no']; ?>" style="color:#6c3483;">#<?php echo $p['bill_no']; ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['party_detail']); ?><br>
                            <small><?php echo htmlspecialchars($p['cell_no']); ?></small></td>
                        <td>
                            <?php if ($p['amt_type'] == 'CR'): ?>
                            <span style="background:#27ae60;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">CR-Cash</span>
                            <?php else: ?>
                            <span style="background:#3498db;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">BR-Bank</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color:<?php echo $p['amount']<0 ? '#e74c3c' : '#27ae60'; ?>;font-size:14px;">
                                Rs. <?php echo number_format(abs($p['amount'])); ?>
                                <?php if ($p['amount'] < 0): ?> <small>(Refund)</small><?php endif; ?>
                            </strong>
                        </td>
                        <td><small><?php echo htmlspecialchars($p['desc']); ?></small></td>
                        <td><?php echo htmlspecialchars($p['user']); ?><br>
                            <small><?php echo date('h:i A', strtotime($p['dateent'])); ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $p['id']; ?>, <?php echo $p['bill_no']; ?>, <?php echo $p['amount']; ?>)">
                                🗑 Delete
                            </button>
                        </td>
                    </tr> -->


					<?php 
						$isRefund = ($p['amt_type'] == 'DR');
						$vTypeColors = array('CR'=>'#27ae60','BR'=>'#3498db','CP'=>'#e67e22','BP'=>'#9b59b6');
						$vTypeLabels = array('CR'=>'Cash Rcpt','BR'=>'Bank Rcpt','CP'=>'Cash Pay','BP'=>'Bank Pay');
						$vt = $p['v_type'];
						$vColor = isset($vTypeColors[$vt]) ? $vTypeColors[$vt] : '#999';
						$vLabel = isset($vTypeLabels[$vt]) ? $vTypeLabels[$vt] : $vt;
					?>
					<tr style="<?php echo $isRefund ? 'background:#fff5f5;' : ''; ?>">
						<td><?php echo $p['id']; ?></td>
						<td><strong style="color:#6c3483;"><?php echo $p['vno']; ?></strong></td>
						<td><?php echo date('d/m/Y', strtotime($p['date'])); ?></td>
						<td>
							<?php if ($p['ref_no']): ?>
							<a href="order_detail.php?bill=<?php echo $p['ref_no']; ?>" style="color:#6c3483;">#<?php echo $p['ref_no']; ?></a>
							<?php else: ?>—<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars($p['party_detail']); ?><br>
							<small><?php echo htmlspecialchars($p['cell_no']); ?></small></td>
						<td>
							<span style="background:<?php echo $vColor; ?>;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">
								<?php echo $vt; ?>
							</span><br>
							<small style="color:#888;font-size:10px;"><?php echo $vLabel; ?></small>
						</td>
						<td>
							<?php if ($isRefund): ?>
							<span style="background:#e74c3c;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">DR-Refund</span>
							<?php else: ?>
							<span style="background:#27ae60;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">CR-Receipt</span>
							<?php endif; ?>
						</td>
						<td>
							<strong style="color:<?php echo $isRefund ? '#e74c3c' : '#27ae60'; ?>;font-size:14px;">
								<?php echo $isRefund ? '−' : '+'; ?> Rs. <?php echo number_format(abs($p['amount'])); ?>
							</strong>
						</td>
						<td><small><?php echo htmlspecialchars($p['desc']); ?></small></td>
						<td><?php echo htmlspecialchars($p['user']); ?><br>
							<small><?php echo date('h:i A', strtotime($p['dateent'])); ?></small></td>
						<td>
							<button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $p['id']; ?>, <?php echo $p['ref_no']; ?>, <?php echo $p['amount']; ?>)">
								🗑 Delete
							</button>
						</td>
					</tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function deletePayment(id, billNo, amount) {
    if (!confirm('⚠ ADMIN ACTION: Delete payment ID #'+id+' of Rs. '+Math.abs(amount).toLocaleString()+'?\n\nThis will reverse the payment on Bill #'+billNo+' and update its status.')) return;
    
    var reason = prompt('Reason for deleting payment:');
    if (!reason) return;
    
    fetch('admin_delete_payment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, reason: reason})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) {
            showToast('Payment deleted', 'success');
            setTimeout(function(){ location.reload(); }, 1000);
        } else {
            showToast(res.message, 'error');
        }
    });
}

function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + type + ' show';
    setTimeout(function(){ t.classList.remove('show'); }, 3000);
}
</script>
</body>
</html>