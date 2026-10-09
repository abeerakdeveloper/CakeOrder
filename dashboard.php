<?php
require_once 'db.php';
require_once 'order_lines.php';


// ============================================
// KITCHEN USER ACCESS CONTROL
// Kitchen role cannot access dashboard - send them back
// ============================================
if (isKitchen()) {
    // Try to get the referring URL (previous page)
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    
    // Get current host to validate referrer is from same site
    $currentHost = $_SERVER['HTTP_HOST'];
    $isValidReferrer = false;
    
    if (!empty($referrer)) {
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        // Only allow same-domain referrers (security)
        if ($referrerHost === $currentHost) {
            // Also check the referrer is not the dashboard itself (avoid loop)
            $referrerPath = parse_url($referrer, PHP_URL_PATH);
            if (strpos($referrerPath, 'dashboard.php') === false) {
                $isValidReferrer = true;
            }
        }
    }
    
    if ($isValidReferrer) {
        // Go back to where they came from
        header('Location: ' . $referrer);
    } else {
        // No valid referrer (direct URL access) - send to kitchen display
        header('Location: kitchen_display.php?msg=no_dashboard_access');
    }
    exit;
}




$today = date('Y-m-d');
$weekLater = date('Y-m-d', strtotime('+7 days'));
/*
// Revenue Today (from gledg)
$res = mysqli_query($mysqli, "SELECT COALESCE(SUM(amount),0) AS revenue FROM gledg WHERE date = '$today' AND acc_code = '112000001'");
$row = mysqli_fetch_assoc($res);
$revenueToday = $row['revenue'];
*/


// Revenue Today (from gledg) - only receipts (CR), exclude refunds (DR)
$res = mysqli_query($mysqli, "SELECT COALESCE(SUM(amount),0) AS revenue FROM gledg WHERE date = '$today' AND acc_code = '112000001' AND amt_type = 'CR'");
$row = mysqli_fetch_assoc($res);
$revenueToday = $row['revenue'];


// Orders Today
$res = mysqli_query($mysqli, "SELECT COUNT(DISTINCT bill_no) AS cnt FROM cake_order WHERE inv_date = '$today' AND ordercancel = 0");
$row = mysqli_fetch_assoc($res);
$ordersToday = $row['cnt'];

// Pending
$res = mysqli_query($mysqli, "SELECT COUNT(DISTINCT bill_no) AS cnt FROM cake_order WHERE status IN ('pending','confirmed') AND ordercancel = 0");
$row = mysqli_fetch_assoc($res);
$pendingOrders = $row['cnt'];

// Upcoming
$res = mysqli_query($mysqli, "SELECT COUNT(DISTINCT bill_no) AS cnt FROM cake_order WHERE deliver_date BETWEEN '$today' AND '$weekLater' AND ordercancel = 0 AND status NOT IN ('delivered','paid','cancelled')");
$row = mysqli_fetch_assoc($res);
$upcomingOrders = $row['cnt'];

/*
// Cash vs Card breakdown today
$res = mysqli_query($mysqli, "SELECT amt_type, COALESCE(SUM(amount),0) AS total FROM gledg WHERE date = '$today' AND acc_code = '112000001' GROUP BY amt_type");
$cashTotal = 0; $cardTotal = 0;
while ($r = mysqli_fetch_assoc($res)) {
    if ($r['amt_type'] == 'CR') $cashTotal = $r['total'];
    else if ($r['amt_type'] == 'BR') $cardTotal = $r['total'];
}
*/


// Cash vs Card breakdown today (only receipts)
$res = mysqli_query($mysqli, "SELECT v_type, COALESCE(SUM(amount),0) AS total FROM gledg WHERE date = '$today' AND acc_code = '112000001' AND amt_type = 'CR' GROUP BY v_type");
$cashTotal = 0; $cardTotal = 0;
while ($r = mysqli_fetch_assoc($res)) {
    if ($r['v_type'] == 'CR') $cashTotal = $r['total'];
    else if ($r['v_type'] == 'BR') $cardTotal = $r['total'];
}



// Status counts
$statusCounts = array();
$res = mysqli_query($mysqli, "SELECT status, COUNT(DISTINCT bill_no) AS cnt FROM cake_order WHERE ordercancel = 0 GROUP BY status");
while ($r = mysqli_fetch_assoc($res)) $statusCounts[$r['status']] = intval($r['cnt']);

// Top 5 Flavors
$res = mysqli_query($mysqli, "SELECT flavor, COUNT(*) AS cnt FROM cake_order WHERE flavor != '' AND ordercancel = 0 GROUP BY flavor ORDER BY cnt DESC LIMIT 5");
$topFlavors = array();
while ($r = mysqli_fetch_assoc($res)) $topFlavors[] = $r;
$maxFlavorCount = !empty($topFlavors) ? $topFlavors[0]['cnt'] : 1;

// Latest pending
$res = mysqli_query($mysqli, "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time, MAX(status) AS status, MAX(priority) AS priority,
    GROUP_CONCAT(category SEPARATOR ', ') AS items, SUM(amount) AS total
    FROM cake_order WHERE status IN ('pending','confirmed','preparing','ready') AND ordercancel = 0 
    GROUP BY bill_no ORDER BY deliver_date ASC, delivery_time ASC LIMIT 10");
$latestPending = array();
while ($r = mysqli_fetch_assoc($res)) $latestPending[] = $r;

$pageTitle = 'Dashboard';
$branchForTitle = getBranchInfo();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Dashboard - <?php echo htmlspecialchars($branchForTitle['name']); ?> BestPOS</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
        <div style="background:#fee;color:#c0392b;padding:12px;border-radius:8px;margin-bottom:16px;">
             Access Denied: You don't have permission to access that page.
        </div>
        <?php endif; ?>
        
        <?php if (isPOSUser() || isAdmin()): ?>
        <!-- START A NEW ORDER: one tile per order type -->
        <div class="section-label">Start a new order</div>
        <div class="type-tiles">
            <a class="type-tile" href="index.php?type=cake"><strong>Cake</strong><span>Cake by weight, with flavour, shape and message</span></a>
            <a class="type-tile" href="index.php?type=lunch"><strong>Lunch box</strong><span>Boxes with items. One or more box groups</span></a>
            <a class="type-tile" href="index.php?type=sweet"><strong>Sweet box</strong><span>Boxes with items, priced after weighing</span></a>
            <a class="type-tile" href="index.php?type=eatable"><strong>Eatable picture</strong><span>Printed picture with size and price</span></a>
            <a class="type-tile" href="index.php?type=other"><strong>Other</strong><span>Any other item</span></a>
        </div>
        <?php endif; ?>

        <!-- STATS ROW -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;"></div>
                <div class="stat-info">
                    <h2 style="color:#27ae60;">Rs. <?php echo number_format($revenueToday); ?></h2>
                    <p>Revenue Today</p>
                    <small style="color:#888;font-size:10px;">Cash: <?php echo number_format($cashTotal); ?> | Card: <?php echo number_format($cardTotal); ?></small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;"></div>
                <div class="stat-info">
                    <h2><?php echo $ordersToday; ?></h2>
                    <p>Orders Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce7f3;"></div>
                <div class="stat-info">
                    <h2 style="color:#e74c3c;"><?php echo $pendingOrders; ?></h2>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0e7ff;"></div>
                <div class="stat-info">
                    <h2 style="color:#6c3483;"><?php echo $upcomingOrders; ?></h2>
                    <p>Upcoming (7 Days)</p>
                </div>
            </div>
        </div>

        <!-- QUICK SEARCH BAR FOR USER -->
        <?php if (isPOSUser() || isAdmin()): ?>
        <div class="data-card" style="margin-bottom:20px;">
            <h4> Quick Order Status Check (for Customer Inquiries)</h4>
            <div style="display:flex;gap:10px;margin-top:12px;">
                <input type="text" id="quickSearch" placeholder="Enter Bill #, Customer Name or Phone..." 
                       style="flex:1;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;"
                       onkeyup="if(event.key=='Enter') quickStatusCheck()">
                <button class="btn btn-primary" onclick="quickStatusCheck()"> Check Status</button>
            </div>
            <div id="quickResult" style="margin-top:12px;"></div>
        </div>
        <?php endif; ?>

        <!-- CHARTS -->
        <div class="charts-row">
            <div class="chart-card">
                <h4>Order Status Overview</h4>
                <canvas id="donutChart" width="300" height="250"></canvas>
                <div class="donut-legend" id="donutLegend"></div>
            </div>

            <div class="chart-card">
                <h4>Top 5 Popular Flavors</h4>
                <div class="bar-chart">
                    <?php foreach ($topFlavors as $f): ?>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo htmlspecialchars($f['flavor']); ?></div>
                        <div class="bar-fill" style="width: <?php echo ($f['cnt']/$maxFlavorCount)*60; ?>%;"></div>
                        <span class="bar-value"><?php echo $f['cnt']; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($topFlavors)): ?>
                    <p style="color:#999;text-align:center;">No flavor data yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
	

		<!-- 
        LATEST PENDING
        <div class="data-card">
            <h4>Active Orders (Pending → Ready)</h4>
            <table>
                <thead>
                    <tr><th>Bill #</th><th>Customer</th><th>Items</th><th>Delivery</th><th>Amount</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($latestPending)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#999;">No active orders</td></tr>
                    <?php endif; ?>
                    <?php foreach ($latestPending as $o): ?>
                    <tr>
                        <td><strong>#<?php echo $o['bill_no']; ?></strong>
                            <?php if ($o['priority']=='urgent'): ?><br><small style="color:#e74c3c;"></small><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($o['party_detail']); ?><br>
                            <small style="color:#888;"> <?php echo htmlspecialchars($o['cell_no']); ?></small></td>
                        <td><small><?php echo htmlspecialchars($o['items']); ?></small></td>
                        <td><?php echo date('d M', strtotime($o['deliver_date'])); ?><br>
                            <small><?php echo $o['delivery_time']; ?></small></td>
                        <td>Rs. <?php echo number_format($o['total']); ?></td>
                        <td><?php echo getStatusBadge($o['status']); ?></td>
                        <td><a href="order_detail.php?bill=<?php echo $o['bill_no']; ?>" class="btn btn-sm btn-info"> View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div> -->

		
		<!-- ALL PENDING ORDERS (Sorted by Priority + Delivery Date) -->
		<?php
		// Fetch ALL pending orders with priority sorting
		$pendingSql = "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time, 
			MAX(status) AS status, MAX(priority) AS priority,
			GROUP_CONCAT(category SEPARATOR ', ') AS items,
			SUM(amount) AS total, MAX(advance) AS advance, MAX(paid) AS paid, 
			MAX(flat_disc) AS disc, COUNT(*) AS item_count
			FROM cake_order 
			WHERE status IN ('pending','confirmed','preparing','ready') 
			AND ordercancel = 0 
			GROUP BY bill_no 
			ORDER BY 
				FIELD(MAX(priority), 'vip', 'urgent', 'normal'),
				deliver_date ASC, 
				delivery_time ASC";

		$pendingRes = mysqli_query($mysqli, $pendingSql);
		$allPending = array();
		$vipCount = 0; $urgentCount = 0; $normalCount = 0;
		if ($pendingRes) {
			while ($p = mysqli_fetch_assoc($pendingRes)) {
				$allPending[] = $p;
				if ($p['priority'] == 'vip') $vipCount++;
				else if ($p['priority'] == 'urgent') $urgentCount++;
				else $normalCount++;
			}
		}
		?>

		<div class="data-card">
			<h4>
				 Pending Orders (<?php echo count($allPending); ?>)
				<span style="font-size:12px;font-weight:normal;color:#888;margin-left:10px;">
					<span style="color:#f39c12;"> VIP: <?php echo $vipCount; ?></span> &nbsp;|&nbsp;
					<span style="color:#e74c3c;"> Urgent: <?php echo $urgentCount; ?></span> &nbsp;|&nbsp;
					<span style="color:#3498db;"> Normal: <?php echo $normalCount; ?></span>
				</span>
				
				<!-- Priority Filter Tabs -->
				<div style="float:right;display:flex;gap:4px;">
					<button class="btn btn-sm btn-outline priority-tab active" onclick="filterPriority('all', this)">All</button>
					<button class="btn btn-sm btn-outline priority-tab" onclick="filterPriority('vip', this)" style="color:#f39c12;"> VIP</button>
					<button class="btn btn-sm btn-outline priority-tab" onclick="filterPriority('urgent', this)" style="color:#e74c3c;"> Urgent</button>
					<button class="btn btn-sm btn-outline priority-tab" onclick="filterPriority('normal', this)"> Normal</button>
				</div>
			</h4>
			
			<table>
				<thead>
					<tr>
						<th>Priority</th>
						<th>Bill #</th>
						<th>Customer</th>
						<th>Items</th>
						<th>Delivery Date ↓</th>
						<th>Amount</th>
						<th>Balance</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody id="pendingTbody">
					<?php if (empty($allPending)): ?>
					<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">No pending orders</td></tr>
					<?php endif; ?>
					<?php foreach ($allPending as $o): 
						$balance = $o['total'] - $o['disc'] - $o['advance'] - $o['paid'];
						$rowBg = '';
						$priorityIcon = '';
						$priorityColor = '#3498db';
						$priorityLabel = 'Normal';
						
						if ($o['priority'] == 'vip') {
							$rowBg = 'background:#fff8e1;';
							$priorityIcon = '';
							$priorityColor = '#f39c12';
							$priorityLabel = 'VIP';
						} else if ($o['priority'] == 'urgent') {
							$rowBg = 'background:#ffebee;';
							$priorityIcon = '';
							$priorityColor = '#e74c3c';
							$priorityLabel = 'Urgent';
						}
						
						// Check if delivery is today or overdue
						$deliveryDays = (strtotime($o['deliver_date']) - strtotime(date('Y-m-d'))) / 86400;
						$deliveryAlert = '';
						if ($deliveryDays < 0) $deliveryAlert = '<span style="color:#e74c3c;font-weight:bold;font-size:10px;"> OVERDUE</span>';
						else if ($deliveryDays == 0) $deliveryAlert = '<span style="color:#f39c12;font-weight:bold;font-size:10px;"> TODAY</span>';
						else if ($deliveryDays == 1) $deliveryAlert = '<span style="color:#3498db;font-weight:bold;font-size:10px;"> TOMORROW</span>';
						
						// Permission to edit
						$canEdit = ot_editable_status($o['status']);
					?>
					<tr class="pending-row" data-priority="<?php echo $o['priority']; ?>" style="<?php echo $rowBg; ?>">
						<td>
							<span style="background:<?php echo $priorityColor; ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">
								<?php echo $priorityIcon; ?> <?php echo $priorityLabel; ?>
							</span>
						</td>
						<td><strong style="font-size:14px;">#<?php echo $o['bill_no']; ?></strong></td>
						<td>
							<strong><?php echo htmlspecialchars($o['party_detail']); ?></strong><br>
							<small style="color:#888;"> <?php echo htmlspecialchars($o['cell_no']); ?></small>
						</td>
						<td>
							<small><?php echo htmlspecialchars($o['items']); ?></small><br>
							<small style="color:#6c3483;font-weight:600;">(<?php echo $o['item_count']; ?> items)</small>
						</td>
						<td>
							<strong><?php echo date('d M Y', strtotime($o['deliver_date'])); ?></strong><br>
							<small> <?php echo $o['delivery_time']; ?></small>
							<?php if ($deliveryAlert): ?><br><?php echo $deliveryAlert; ?><?php endif; ?>
						</td>
						<td><strong>Rs. <?php echo number_format($o['total'] - $o['disc']); ?></strong><br>
							<small style="color:#27ae60;">Paid: <?php echo number_format($o['advance'] + $o['paid']); ?></small>
						</td>
						<td style="color:<?php echo $balance > 0 ? '#e74c3c' : '#27ae60'; ?>;font-weight:bold;">
							Rs. <?php echo number_format(max(0, $balance)); ?>
						</td>
						<td><?php echo getStatusBadge($o['status']); ?></td>
						<td>
							<div style="display:flex;gap:4px;flex-wrap:wrap;">
								<a href="order_detail.php?bill=<?php echo $o['bill_no']; ?>" class="btn btn-sm btn-info" title="View Details"></a>
								
								<?php if ($canEdit && (isPOSUser() || isAdmin())): ?>
								<button class="btn btn-sm btn-warning" 
										onclick="editOrder(<?php echo $o['bill_no']; ?>)" 
										title="Edit Order (Add items, customer info, etc.)">
									Edit
								</button>
								<button class="btn btn-sm btn-success" 
										onclick="addMoreAdvance(<?php echo $o['bill_no']; ?>, <?php echo max(0, $balance); ?>, '<?php echo addslashes($o['party_detail']); ?>')" 
										title="Add More Advance Payment">
									 +Advance
								</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ADD MORE ADVANCE MODAL -->
		<div class="modal-overlay" id="advanceModal">
			<div class="modal">
				<h3> Add More Advance Payment</h3>
				<p style="color:#666;font-size:13px;margin-bottom:12px;">
					Order: <strong id="advBillNo"></strong> — Customer: <strong id="advCustomer"></strong>
				</p>
				<p style="color:#e74c3c;font-size:13px;margin-bottom:16px;">
					Current Balance: <strong id="advBalance"></strong>
				</p>
				
				<div class="form-group">
					<label>Amount to Add (Rs.)</label>
					<input type="number" id="advAmount" min="1" placeholder="Enter amount..." 
						   style="font-size:18px;padding:12px;text-align:center;font-weight:bold;color:#6c3483;">
				</div>
				
				<div class="form-group">
					<label>Payment Method</label>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
						<button type="button" class="btn btn-outline adv-method" data-method="cash" onclick="selectAdvMethod('cash', this)"> Cash</button>
						<button type="button" class="btn btn-outline adv-method" data-method="bank" onclick="selectAdvMethod('bank', this)"> Bank</button>
						<button type="button" class="btn btn-outline adv-method" data-method="card" onclick="selectAdvMethod('card', this)"> Card</button>
						<button type="button" class="btn btn-outline adv-method" data-method="easypaisa" onclick="selectAdvMethod('easypaisa', this)"> Easypaisa</button>
					</div>
				</div>
				
				<div class="form-group" id="advRefSection" style="display:none;">
					<label>Reference / Transaction ID</label>
					<input type="text" id="advReference" placeholder="Enter reference number">
				</div>
				
				<div class="modal-actions">
					<button class="btn btn-outline" onclick="closeAdvanceModal()">Cancel</button>
					<button class="btn btn-success" onclick="submitAdvance()" id="advSubmitBtn"> Add Advance</button>
				</div>
			</div>
		</div>

		<style>
		.priority-tab.active {
			background: #6c3483 !important;
			color: #fff !important;
			border-color: #6c3483 !important;
		}
		.pending-row {
			transition: opacity 0.2s;
		}
		</style>

		<script>
		// ===== PRIORITY FILTER =====
		function filterPriority(priority, btn) {
			document.querySelectorAll('.priority-tab').forEach(function(b){ b.classList.remove('active'); });
			btn.classList.add('active');
			
			var rows = document.querySelectorAll('.pending-row');
			for (var i = 0; i < rows.length; i++) {
				if (priority === 'all' || rows[i].dataset.priority === priority) {
					rows[i].style.display = '';
				} else {
					rows[i].style.display = 'none';
				}
			}
		}

		// ===== EDIT ORDER =====
		function editOrder(billNo) {
			window.location.href = 'index.php?bill=' + billNo;
		}

		// ===== ADD MORE ADVANCE =====
		var advBillNoVal = 0;
		var advMaxBalance = 0;
		var selectedAdvMethodVal = '';

		function addMoreAdvance(billNo, balance, customer) {
			advBillNoVal = billNo;
			advMaxBalance = balance;
			selectedAdvMethodVal = '';
			
			document.getElementById('advBillNo').textContent = '#' + billNo;
			document.getElementById('advCustomer').textContent = customer;
			document.getElementById('advBalance').textContent = 'Rs. ' + balance.toLocaleString();
			document.getElementById('advAmount').value = '';
			document.getElementById('advAmount').max = balance;
			document.getElementById('advReference').value = '';
			document.getElementById('advRefSection').style.display = 'none';
			
			// Reset method buttons
			document.querySelectorAll('.adv-method').forEach(function(b){
				b.classList.remove('btn-warning');
				b.classList.add('btn-outline');
			});
			
			document.getElementById('advanceModal').classList.add('show');
			setTimeout(function(){ document.getElementById('advAmount').focus(); }, 200);
		}

		function selectAdvMethod(method, btn) {
			selectedAdvMethodVal = method;
			document.querySelectorAll('.adv-method').forEach(function(b){
				b.classList.remove('btn-warning');
				b.classList.add('btn-outline');
			});
			btn.classList.remove('btn-outline');
			btn.classList.add('btn-warning');
			
			// Show reference for non-cash
			document.getElementById('advRefSection').style.display = (method !== 'cash') ? 'block' : 'none';
		}

		function closeAdvanceModal() {
			document.getElementById('advanceModal').classList.remove('show');
		}

		function submitAdvance() {
			var amount = parseInt(document.getElementById('advAmount').value) || 0;
			
			if (amount <= 0) {
				alert('Enter valid amount');
				return;
			}
			
			if (amount > advMaxBalance) {
				alert('Amount exceeds balance of Rs. ' + advMaxBalance.toLocaleString());
				return;
			}
			
			if (!selectedAdvMethodVal) {
				alert('Select payment method');
				return;
			}
			
			if (!confirm('Add Rs. ' + amount.toLocaleString() + ' as advance via ' + selectedAdvMethodVal.toUpperCase() + ' to Order #' + advBillNoVal + '?')) return;
			
			var btn = document.getElementById('advSubmitBtn');
			btn.disabled = true;
			btn.textContent = ' Processing...';
			
			fetch('add_more_advance.php', {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					bill_no: advBillNoVal,
					amount: amount,
					method: selectedAdvMethodVal,
					reference: document.getElementById('advReference').value
				})
			})
			.then(function(r){ return r.json(); })
			.then(function(res) {
				if (res.success) {
					alert(' Advance of Rs. ' + amount.toLocaleString() + ' added successfully!\nVoucher #' + res.vno);
					location.reload();
				} else {
					alert(' Error: ' + res.message);
					btn.disabled = false;
					btn.textContent = ' Add Advance';
				}
			})
			.catch(function() {
				alert('Network error');
				btn.disabled = false;
				btn.textContent = ' Add Advance';
			});
		}
		</script>


    </div>
</div>

<script>
var statusData = <?php echo json_encode($statusCounts); ?>;
var colors = {'pending':'#f39c12','confirmed':'#3498db','preparing':'#e67e22','ready':'#27ae60','delivered':'#2ecc71','paid':'#1abc9c','cancelled':'#e74c3c','hold':'#95a5a6'};

function drawDonut() {
    var canvas = document.getElementById('donutChart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var data = [];
    for (var k in statusData) data.push([k, statusData[k]]);
    var total = 0;
    for (var i = 0; i < data.length; i++) total += data[i][1];
    if (total === 0) { ctx.fillText('No data', 130, 120); return; }
    var cx = 150, cy = 120, r = 90, inner = 55;
    var startAngle = -Math.PI / 2;
    var legendHtml = '';
    for (var j = 0; j < data.length; j++) {
        var status = data[j][0], count = data[j][1];
        var slice = (count / total) * Math.PI * 2;
        var color = colors[status] || '#999';
        ctx.beginPath();
        ctx.arc(cx, cy, r, startAngle, startAngle + slice);
        ctx.arc(cx, cy, inner, startAngle + slice, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();
        legendHtml += '<div class="legend-item"><div class="legend-dot" style="background:'+color+'"></div>'+status.charAt(0).toUpperCase()+status.slice(1)+': '+count+'</div>';
        startAngle += slice;
    }
    document.getElementById('donutLegend').innerHTML = legendHtml;
}
drawDonut();

function quickStatusCheck() {
    var q = document.getElementById('quickSearch').value.trim();
    if (!q) { alert('Enter search term'); return; }
    
    var result = document.getElementById('quickResult');
    result.innerHTML = '<div style="text-align:center;padding:10px;"> Searching...</div>';
    
    fetch('order_status_api.php?q=' + encodeURIComponent(q))
    .then(function(r){return r.json();})
    .then(function(res) {
        if (res.found && res.orders.length > 0) {
            var html = '<div style="background:#f5f0fa;padding:12px;border-radius:8px;">';
            html += '<h4 style="color:#6c3483;margin-bottom:8px;">Found '+res.orders.length+' order(s):</h4>';
            for (var i=0; i<res.orders.length; i++) {
                var o = res.orders[i];
                html += '<div style="background:#fff;padding:10px;border-radius:6px;margin-bottom:6px;border-left:4px solid #6c3483;">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                html += '<div><strong>Bill #'+o.bill_no+'</strong> — '+o.party_detail+' ('+o.cell_no+')</div>';
                html += '<div>'+o.status_badge+'</div>';
                html += '</div>';
                html += '<div style="margin-top:4px;font-size:12px;color:#666;">';
                html += ' '+o.items+' |  '+o.deliver_date+' '+o.delivery_time;
                html += ' |  Rs. '+o.total+' (Paid: '+o.paid+' | Balance: '+o.balance+')';
                html += '</div>';
                html += '<div style="margin-top:6px;"><a href="order_detail.php?bill='+o.bill_no+'" class="btn btn-sm btn-info"> View Full Details</a></div>';
                html += '</div>';
            }
            html += '</div>';
            result.innerHTML = html;
        } else {
            result.innerHTML = '<div style="background:#fee;color:#c0392b;padding:12px;border-radius:8px;"> No orders found matching "'+q+'"</div>';
        }
    });
}

// Auto refresh every 60 seconds
setInterval(function(){ location.reload(); }, 120000);
</script>
</body>
</html>