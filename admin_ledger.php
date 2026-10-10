<?php
require_once 'db.php';
requireRole(array(3));


/*
$fromDate = isset($_GET['from']) ? esc($_GET['from']) : date('Y-m-01');
$toDate = isset($_GET['to']) ? esc($_GET['to']) : date('Y-m-d');
$vTypeFilter = isset($_GET['v_type']) ? esc($_GET['v_type']) : '';
$amtTypeFilter = isset($_GET['amt_type']) ? esc($_GET['amt_type']) : '';

$where = "WHERE date BETWEEN '$fromDate' AND '$toDate'";
if ($vTypeFilter) $where .= " AND v_type = '$vTypeFilter'";
if ($amtTypeFilter) $where .= " AND amt_type = '$amtTypeFilter'";

$sql = "SELECT * FROM gledg $where ORDER BY date DESC, id DESC LIMIT 500";
$res = mysqli_query($mysqli, $sql);
*/


$fromDate = isset($_GET['from']) ? esc($_GET['from']) : date('Y-m-01');
$toDate = isset($_GET['to']) ? esc($_GET['to']) : date('Y-m-d');
$vTypeFilter = isset($_GET['v_type']) ? esc($_GET['v_type']) : '';
$amtTypeFilter = isset($_GET['amt_type']) ? esc($_GET['amt_type']) : '';
$accFilter = isset($_GET['acc_code']) ? esc($_GET['acc_code']) : '112000001'; // Default cake account
$showAll = isset($_GET['all']) ? true : false;

// Build WHERE clause
$where = "WHERE ref_no>0 ";

if (!$showAll) {
    $where .= " AND date BETWEEN '$fromDate' AND '$toDate'";
}

if ($accFilter && $accFilter != 'ALL') {
    $where .= " AND acc_code = '$accFilter'";
}

if ($vTypeFilter) $where .= " AND v_type = '$vTypeFilter'";
if ($amtTypeFilter) $where .= " AND amt_type = '$amtTypeFilter'";

$sql = "SELECT * FROM gledg $where ORDER BY date DESC, gledg_id DESC LIMIT 500";


$res = mysqli_query($mysqli, $sql);

// Debug check
$totalRowsCheck = mysqli_query($mysqli, "SELECT COUNT(*) as cnt FROM gledg");
$totalRowsData = mysqli_fetch_assoc($totalRowsCheck);
$totalRowsInDB = intval($totalRowsData['cnt']);

// Error check
$queryError = mysqli_error($mysqli);


$entries = array();
$totals = array(
    'CR_receipt' => 0,  // Cash Received
    'BR_receipt' => 0,  // Bank Received
    'CP_refund' => 0,   // Cash Refunded
    'BP_refund' => 0    // Bank Refunded
);

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $entries[] = $r;
        $vt = $r['v_type'];
        $at = $r['amt_type'];
        $amt = floatval($r['amount']);
        
        if ($vt == 'CR' && $at == 'CR') $totals['CR_receipt'] += $amt;
        else if ($vt == 'BR' && $at == 'CR') $totals['BR_receipt'] += $amt;
        else if ($vt == 'CP' && $at == 'DR') $totals['CP_refund'] += $amt;
        else if ($vt == 'BP' && $at == 'DR') $totals['BP_refund'] += $amt;
    }
}

$totalReceived = $totals['CR_receipt'] + $totals['BR_receipt'];
$totalRefunded = $totals['CP_refund'] + $totals['BP_refund'];
$netRevenue = $totalReceived - $totalRefunded;

$pageTitle = 'General Ledger';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>General Ledger - Admin</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/app.css">
<style>
.v-type-badge {
    padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 600;
    color: #fff; display: inline-block; min-width: 60px; text-align: center;
}
.amt-type-badge {
    padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600;
    display: inline-block; margin-left: 4px;
}
</style>
</head>
<body>

<?php include 'includes/header.php'; ?>
		

        <!-- FILTERS 
        <div class="filter-bar">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <label>From:</label>
                <input type="date" name="from" value="<?php echo $fromDate; ?>">
                <label>To:</label>
                <input type="date" name="to" value="<?php echo $toDate; ?>">
                
                <select name="v_type">
                    <option value="">All Voucher Types</option>
                    <option value="CR" <?php if($vTypeFilter=='CR') echo 'selected'; ?>>CR - Cash Receipt</option>
                    <option value="BR" <?php if($vTypeFilter=='BR') echo 'selected'; ?>>BR - Bank Receipt</option>
                    <option value="CP" <?php if($vTypeFilter=='CP') echo 'selected'; ?>>CP - Cash Payment</option>
                    <option value="BP" <?php if($vTypeFilter=='BP') echo 'selected'; ?>>BP - Bank Payment</option>
                </select>
                
                <select name="amt_type">
                    <option value="">All Transactions</option>
                    <option value="CR" <?php if($amtTypeFilter=='CR') echo 'selected'; ?>>CR - Receipt (Incoming)</option>
                    <option value="DR" <?php if($amtTypeFilter=='DR') echo 'selected'; ?>>DR - Refund (Outgoing)</option>
                </select>
                
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                <a href="admin_ledger.php" class="btn btn-outline btn-sm">Reset</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
            </form>
        </div>
		-->

		<!-- DEBUG INFO -->
		<div style="background:#fff3e0;padding:10px;border-radius:6px;margin-bottom:12px;font-size:12px;border-left:4px solid #f39c12;">
			🔍 <strong>Debug Info:</strong> 
			Total entries in gledg table: <strong><?php echo $totalRowsInDB; ?></strong> | 
			Showing <strong><?php echo count($entries); ?></strong> with current filters
			<?php if ($queryError): ?>
			| <span style="color:#e74c3c;">SQL Error: <?php echo htmlspecialchars($queryError); ?></span>
			<?php endif; ?>
		</div>

		<!-- FILTERS -->
		<div class="filter-bar">
			<form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
				<label>Account:</label>
				<select name="acc_code">
					<option value="ALL" <?php if($accFilter=='ALL') echo 'selected'; ?>>All Accounts</option>
					<option value="112000001" <?php if($accFilter=='112000001') echo 'selected'; ?>>112000001 (Cake/POS)</option>
					<?php 
					// Get all unique acc_codes from database
					$accRes = mysqli_query($mysqli, "SELECT DISTINCT acc_code FROM gledg WHERE acc_code IS NOT NULL AND acc_code != '' ORDER BY acc_code");
					if ($accRes) {
						while ($a = mysqli_fetch_assoc($accRes)) {
							if ($a['acc_code'] != '112000001') {
								$sel = ($accFilter == $a['acc_code']) ? 'selected' : '';
								echo "<option value='".htmlspecialchars($a['acc_code'])."' $sel>".htmlspecialchars($a['acc_code'])."</option>";
							}
						}
					}
					?>
				</select>
				
				<label>From:</label>
				<input type="date" name="from" value="<?php echo $fromDate; ?>">
				<label>To:</label>
				<input type="date" name="to" value="<?php echo $toDate; ?>">
				
				<select name="v_type">
					<option value="">All Voucher Types</option>
					<option value="CR" <?php if($vTypeFilter=='CR') echo 'selected'; ?>>CR - Cash Receipt</option>
					<option value="BR" <?php if($vTypeFilter=='BR') echo 'selected'; ?>>BR - Bank Receipt</option>
					<option value="CP" <?php if($vTypeFilter=='CP') echo 'selected'; ?>>CP - Cash Payment</option>
					<option value="BP" <?php if($vTypeFilter=='BP') echo 'selected'; ?>>BP - Bank Payment</option>
				</select>
				
				<select name="amt_type">
					<option value="">All Transactions</option>
					<option value="CR" <?php if($amtTypeFilter=='CR') echo 'selected'; ?>>CR - Receipt</option>
					<option value="DR" <?php if($amtTypeFilter=='DR') echo 'selected'; ?>>DR - Refund</option>
				</select>
				
				<button class="btn btn-primary btn-sm" type="submit">Filter</button>
				<a href="admin_ledger.php" class="btn btn-outline btn-sm">Reset</a>
				<a href="?all=1&acc_code=ALL" class="btn btn-warning btn-sm">Show All Entries</a>
				<button type="button" class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
			</form>
		</div>
        <!-- STATS SUMMARY -->
        <div class="stats-row" style="grid-template-columns:repeat(4,1fr);">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d4edda;">💵</div>
                <div class="stat-info">
                    <h2 style="color:#27ae60;">Rs. <?php echo number_format($totals['CR_receipt']); ?></h2>
                    <p>Cash Received (CR)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;">🏦</div>
                <div class="stat-info">
                    <h2 style="color:#3498db;">Rs. <?php echo number_format($totals['BR_receipt']); ?></h2>
                    <p>Bank Received (BR)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee;">💸</div>
                <div class="stat-info">
                    <h2 style="color:#e74c3c;">Rs. <?php echo number_format($totalRefunded); ?></h2>
                    <p>Total Refunded</p>
                    <small style="color:#888;font-size:10px;">
                        CP: <?php echo number_format($totals['CP_refund']); ?> | 
                        BP: <?php echo number_format($totals['BP_refund']); ?>
                    </small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f5f0fa;">📊</div>
                <div class="stat-info">
                    <h2 style="color:#6c3483;">Rs. <?php echo number_format($netRevenue); ?></h2>
                    <p>Net Revenue</p>
                    <small style="color:#888;font-size:10px;">(Received - Refunded)</small>
                </div>
            </div>
        </div>

        <!-- LEDGER TABLE -->
        <div class="data-card">
            <h4>General Ledger - Account 112000001 (<?php echo count($entries); ?> entries)</h4>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>V.No</th>
                        <th>V.Type</th>
                        <th>Date</th>
                        <th>Ref/Bill #</th>
                        <th>Trans.</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>User</th>
                        <th>Entry Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                    <tr><td colspan="10" style="text-align:center;color:#999;padding:40px;">No entries found in selected range</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($entries as $e): 
                        // V_TYPE colors and labels
                        $vTypeColors = array(
                            'CR' => '#27ae60', // Cash Receipt - green
                            'BR' => '#3498db', // Bank Receipt - blue
                            'CP' => '#e67e22', // Cash Payment - orange
                            'BP' => '#9b59b6'  // Bank Payment - purple
                        );
                        $vTypeLabels = array(
                            'CR' => 'Cash Receipt',
                            'BR' => 'Bank Receipt',
                            'CP' => 'Cash Payment',
                            'BP' => 'Bank Payment'
                        );
                        $vt = $e['v_type'];
                        $vColor = isset($vTypeColors[$vt]) ? $vTypeColors[$vt] : '#999';
                        $vLabel = isset($vTypeLabels[$vt]) ? $vTypeLabels[$vt] : $vt;
                        
                        // AMT_TYPE
                        $at = $e['amt_type'];
                        $isRefund = ($at == 'DR');
                        $atColor = $isRefund ? '#e74c3c' : '#27ae60';
                        $atLabel = $isRefund ? 'Refund' : 'Receipt';
                        
                        // Row styling
                        $rowStyle = $isRefund ? 'background:#fff5f5;' : '';
                    ?>
                    <tr style="<?php echo $rowStyle; ?>">
                        <td><?php echo $e['id']; ?></td>
                        <td><strong style="color:#6c3483;font-size:14px;"><?php echo $e['vno']; ?></strong></td>
                        <td>
                            <span class="v-type-badge" style="background:<?php echo $vColor; ?>;" title="<?php echo $vLabel; ?>">
                                <?php echo $vt; ?>
                            </span>
                            <br><small style="color:#888;font-size:10px;"><?php echo $vLabel; ?></small>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($e['date'])); ?></td>
                        <td>
                            <?php if ($e['ref_no']): ?>
                            <a href="order_detail.php?bill=<?php echo $e['ref_no']; ?>" style="color:#6c3483;font-weight:600;">#<?php echo $e['ref_no']; ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <span class="amt-type-badge" style="background:<?php echo $atColor; ?>;color:#fff;">
                                <?php echo $at; ?>
                            </span>
                            <br><small style="color:<?php echo $atColor; ?>;font-size:10px;font-weight:600;">
                                <?php echo $atLabel; ?>
                            </small>
                        </td>
                        <td>
                            <strong style="color:<?php echo $atColor; ?>;font-size:14px;">
                                <?php echo $isRefund ? '−' : '+'; ?> Rs. <?php echo number_format(abs($e['amount'])); ?>
                            </strong>
                        </td>
                        <td><small><?php echo htmlspecialchars($e['desc']); ?></small></td>
                        <td><?php echo htmlspecialchars($e['user']); ?></td>
                        <td>
                            <?php if ($e['dateent']): ?>
                            <small><?php echo date('d M h:i A', strtotime($e['dateent'])); ?></small>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                
                <?php if (!empty($entries)): ?>
                <tfoot>
                    <tr style="background:#f5f0fa;font-weight:bold;">
                        <td colspan="6" style="text-align:right;padding:12px;">TOTAL:</td>
                        <td style="color:#6c3483;font-size:15px;">Rs. <?php echo number_format($netRevenue); ?></td>
                        <td colspan="3"><small>Received: Rs. <?php echo number_format($totalReceived); ?> | Refunded: Rs. <?php echo number_format($totalRefunded); ?></small></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- LEGEND -->
        <div class="data-card" style="margin-top:16px;">
            <h4>📖 Legend</h4>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-top:12px;font-size:13px;">
                <div>
                    <h5 style="color:#6c3483;margin-bottom:8px;">Voucher Type (v_type)</h5>
                    <div style="padding:4px 0;"><span class="v-type-badge" style="background:#27ae60;">CR</span> Cash Receipt — when customer pays cash</div>
                    <div style="padding:4px 0;"><span class="v-type-badge" style="background:#3498db;">BR</span> Bank Receipt — when customer pays via bank/card</div>
                    <div style="padding:4px 0;"><span class="v-type-badge" style="background:#e67e22;">CP</span> Cash Payment — when we refund cash</div>
                    <div style="padding:4px 0;"><span class="v-type-badge" style="background:#9b59b6;">BP</span> Bank Payment — when we refund via bank</div>
                </div>
                <div>
                    <h5 style="color:#6c3483;margin-bottom:8px;">Transaction (amt_type)</h5>
                    <div style="padding:4px 0;"><span class="amt-type-badge" style="background:#27ae60;color:#fff;">CR</span> Receipt — Payment received (incoming money)</div>
                    <div style="padding:4px 0;"><span class="amt-type-badge" style="background:#e74c3c;color:#fff;">DR</span> Refund — Payment refunded (outgoing money)</div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>