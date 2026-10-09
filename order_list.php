<?php
require_once 'db.php';
require_once 'order_lines.php';

date_default_timezone_set('Asia/Karachi');

$statusFilter = isset($_GET['status']) ? esc($_GET['status']) : '';
$dateFilter = isset($_GET['date']) ? esc($_GET['date']) : '';
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$priorityFilter = isset($_GET['priority']) ? esc($_GET['priority']) : '';
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'pending'; // pending | all

// Quick filters: today's deliveries, VIP, Urgent
$quick = (isset($_GET['quick']) && is_string($_GET['quick'])) ? $_GET['quick'] : '';
if ($quick === 'today') $dateFilter = date('Y-m-d');
if ($quick === 'vip' || $quick === 'urgent') $priorityFilter = $quick;

$where = "WHERE 1=1";

// Admin sees cancelled too in 'all' view
if (!isAdmin() || $viewMode == 'pending') {
    $where .= " AND ordercancel = 0";
}

// Default view: only pending/active orders
if ($viewMode == 'pending') {
    $where .= " AND status IN ('pending','confirmed','preparing','ready','hold')";
}

if ($statusFilter) $where .= " AND status = '$statusFilter'";
if ($dateFilter) $where .= " AND deliver_date = '$dateFilter'";
if ($priorityFilter) $where .= " AND priority = '$priorityFilter'";
if ($search) {
    $where .= " AND (party_detail LIKE '%$search%' OR bill_no LIKE '%$search%' OR cell_no LIKE '%$search%')";
}

// NEWEST ENTRY FIRST (entry date, then bill number)
$sql = "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time,
    SUM(amount) AS total_amount, MAX(advance) AS advance, MAX(paid) AS paid,
    MAX(status) AS status, MAX(priority) AS priority, MAX(flat_disc) AS flat_disc,
    MAX(payment_method) AS payment_method, MAX(dateent) AS dateent, MAX(ordercancel) AS cancelled,
    GROUP_CONCAT(CASE WHEN sale_type IN ('lunch','sweet','charge','weighed') THEN NULL ELSE category END SEPARATOR ', ') AS items,
    COUNT(*) AS item_count,
    MAX(CASE WHEN sale_type IN ('lunch','sweet') THEN 1 ELSE 0 END) AS has_boxes
    FROM cake_order $where
    GROUP BY bill_no
    ORDER BY 
        dateent DESC,
        bill_no DESC
    LIMIT 500";

$res = mysqli_query($mysqli, $sql);
$orders = array();
$vipCount = 0; $urgentCount = 0; $normalCount = 0;
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $orders[] = $r;
        if ($r['priority'] == 'vip') $vipCount++;
        else if ($r['priority'] == 'urgent') $urgentCount++;
        else $normalCount++;
    }
}

// Box summary per bill: "Lunch boxes: 12 (2 groups)"
$boxSummary = array();
$boxBillIds = array();
foreach ($orders as $o) {
    if (intval($o['has_boxes']) === 1) $boxBillIds[] = intval($o['bill_no']);
}
if (!empty($boxBillIds)) {
    $bxRes = mysqli_query($mysqli, "SELECT bill_no, box_group, MAX(box_qty) AS boxes, MAX(sale_type) AS sale_type
        FROM cake_order WHERE bill_no IN (" . implode(',', $boxBillIds) . ")
        AND sale_type IN ('lunch','sweet') AND ordercancel = 0
        GROUP BY bill_no, box_group");
    if ($bxRes) {
        while ($bx = mysqli_fetch_assoc($bxRes)) {
            $bn = intval($bx['bill_no']);
            if (!isset($boxSummary[$bn])) $boxSummary[$bn] = array('boxes' => 0, 'groups' => 0, 'type' => $bx['sale_type']);
            $boxSummary[$bn]['boxes'] += intval($bx['boxes']);
            $boxSummary[$bn]['groups'] += 1;
        }
    }
}

$pageTitle = 'Order List';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Order List</title>
<link rel="stylesheet" href="style.css">
<style>
.priority-tab.active {
    background: #6c3483 !important;
    color: #fff !important;
    border-color: #6c3483 !important;
}
.view-tab {
    padding: 8px 16px; cursor: pointer; border: none; background: transparent;
    font-size: 13px; font-weight: 600; color: #888; border-bottom: 3px solid transparent;
    margin-bottom: -2px;
}
.view-tab.active { color: #6c3483; border-bottom-color: #6c3483; }
.pending-row { transition: opacity 0.2s; }
</style>
</head>
<body>

<?php include 'includes/header.php'; ?>

        <!-- VIEW MODE TABS -->
        <div style="background:#fff;padding:0 16px;border-radius:8px;margin-bottom:12px;border:1px solid #e8e0f0;">
            <div style="display:flex;gap:0;border-bottom:2px solid #e8e0f0;">
                <a href="?view=pending" class="view-tab <?php if($viewMode=='pending') echo 'active'; ?>">
                     Pending Orders
                </a>
                <a href="?view=all" class="view-tab <?php if($viewMode=='all') echo 'active'; ?>">
                     All Orders
                </a>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-bar">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                
                <input type="text" name="search" placeholder=" Bill#, customer, phone" value="<?php echo htmlspecialchars($search); ?>" style="width:220px;">
                
                <?php if ($viewMode == 'all'): ?>
                <select name="status">
                    <option value="">All Status</option>
                    <?php foreach (array('pending','confirmed','hold','preparing','ready','delivered','paid','cancelled') as $s): ?>
                    <option value="<?php echo $s; ?>" <?php if($statusFilter==$s) echo 'selected'; ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                <a href="?view=<?php echo $viewMode; ?>" class="btn btn-outline btn-sm">Reset</a>
            </form>
            <div class="chip-row">
                <a class="chip <?php echo $quick === '' ? 'active' : ''; ?>" href="?view=<?php echo $viewMode; ?>">All orders</a>
                <a class="chip <?php echo $quick === 'today' ? 'active' : ''; ?>" href="?view=<?php echo $viewMode; ?>&amp;quick=today">Deliver today</a>
                <a class="chip <?php echo $quick === 'vip' ? 'active' : ''; ?>" href="?view=<?php echo $viewMode; ?>&amp;quick=vip">VIP</a>
                <a class="chip <?php echo $quick === 'urgent' ? 'active' : ''; ?>" href="?view=<?php echo $viewMode; ?>&amp;quick=urgent">Urgent</a>
            </div>
        </div>

        <!-- ORDER TABLE WITH PRIORITY SORTING -->
        <div class="data-card">
            <h4>
                <?php if ($viewMode == 'pending'): ?>
                 Pending Orders (<?php echo count($orders); ?>)
                <?php else: ?>
                 All Orders (<?php echo count($orders); ?> shown)
                <?php endif; ?>
                
                <?php if ($viewMode == 'pending'): ?>
                <span style="font-size:12px;font-weight:normal;color:#888;margin-left:10px;">
                    <span style="color:#f39c12;"> VIP: <?php echo $vipCount; ?></span> &nbsp;|&nbsp;
                    <span style="color:#e74c3c;"> Urgent: <?php echo $urgentCount; ?></span> &nbsp;|&nbsp;
                    <span style="color:#3498db;"> Normal: <?php echo $normalCount; ?></span>
                </span>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <small style="color:#e74c3c;font-weight:normal;">- Admin Mode</small>
                <?php endif; ?>
                
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
                        <th>Delivery Date ↑</th>
                        <th>Amount</th>
                        <th>Advance</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ordersTbody">
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="10" style="text-align:center;padding:40px;color:#999;">No orders found</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($orders as $o): 
                        $balance = $o['total_amount'] - $o['flat_disc'] - $o['advance'] - $o['paid'];
                        
                        // Priority styling
                        $rowBg = '';
                        $priorityIcon = '';
                        $priorityColor = '#3498db';
                        $priorityLabel = 'Normal';
                        
                        if ($o['priority'] == 'vip') {
                            $rowBg = 'background:linear-gradient(90deg,#fff8e1,#fff);';
                            $priorityIcon = '';
                            $priorityColor = '#f39c12';
                            $priorityLabel = 'VIP';
                        } else if ($o['priority'] == 'urgent') {
                            $rowBg = 'background:linear-gradient(90deg,#ffebee,#fff);';
                            $priorityIcon = '';
                            $priorityColor = '#e74c3c';
                            $priorityLabel = 'Urgent';
                        }
                        
                        // Cancelled rows
                        if ($o['cancelled']) {
                            $rowBg = 'background:#fff5f5;opacity:0.6;';
                        }
                        
                        // Delivery alerts
                        $deliveryDays = (strtotime($o['deliver_date']) - strtotime(date('Y-m-d'))) / 86400;
                        $deliveryAlert = '';
                        if (!$o['cancelled'] && !in_array($o['status'], array('delivered','paid'))) {
                            if ($deliveryDays < 0) $deliveryAlert = '<span style="color:#e74c3c;font-weight:bold;font-size:10px;"> OVERDUE</span>';
                            else if ($deliveryDays == 0) $deliveryAlert = '<span style="color:#f39c12;font-weight:bold;font-size:10px;"> TODAY</span>';
                            else if ($deliveryDays == 1) $deliveryAlert = '<span style="color:#3498db;font-weight:bold;font-size:10px;"> TOMORROW</span>';
                        }
                        
                        // Permission logic
                        $canCancel = false;
                        if (isAdmin()) {
                            $canCancel = !$o['cancelled'];
                        } else if (isPOSUser()) {
                            $canCancel = !$o['cancelled'] && $o['paid'] == 0 && !in_array($o['status'], array('ready','delivered','paid'));
                        }
                        
                        $canEdit = !$o['cancelled'] && (isPOSUser() || isAdmin()) && !in_array($o['status'], array('ready','delivered','paid','cancelled'));
                        $canAddAdvance = !$o['cancelled'] && (isPOSUser() || isAdmin()) && $balance > 0 && !in_array($o['status'], array('paid','cancelled'));
                        $canPay = !$o['cancelled'] && $balance > 0 && in_array($o['status'], array('delivered','ready'));
                        $canDeliver = !$o['cancelled'] && (isPOSUser() || isAdmin()) && $o['status'] == 'ready';
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
                            <?php if (intval($o['has_boxes']) === 1 && isset($boxSummary[intval($o['bill_no'])])): $bsum = $boxSummary[intval($o['bill_no'])]; ?>
                            <small style="color:#6c3483;font-weight:600;"><?php echo $bsum['type'] === 'sweet' ? 'Sweet boxes' : 'Lunch boxes'; ?>: <?php echo $bsum['boxes']; ?> boxes<?php echo $bsum['groups'] > 1 ? ' (' . $bsum['groups'] . ' groups)' : ''; ?></small><br>
                            <?php endif; ?>
                            <?php if ($o['items'] !== null && $o['items'] !== ''): ?>
                            <small><?php echo htmlspecialchars($o['items']); ?></small><br>
                            <?php endif; ?>
                            <small style="color:#6c3483;font-weight:600;">(<?php echo $o['item_count']; ?> items)</small>
                        </td>
                        <td>
                            <strong><?php echo date('d M Y', strtotime($o['deliver_date'])); ?></strong><br>
                            <small> <?php echo $o['delivery_time']; ?></small>
                            <?php if ($deliveryAlert): ?><br><?php echo $deliveryAlert; ?><?php endif; ?>
                        </td>
                        <td>
                            <strong>Rs. <?php echo number_format($o['total_amount'] - $o['flat_disc']); ?></strong>
                            <?php if ($o['flat_disc'] > 0): ?>
                            <br><small style="color:#27ae60;">Disc: <?php echo number_format($o['flat_disc']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            Rs. <?php echo number_format($o['advance']); ?>
                            <?php if ($o['paid'] > 0): ?>
                            <br><small style="color:#27ae60;">+Paid: <?php echo number_format($o['paid']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="color:<?php echo $balance > 0 ? '#e74c3c' : '#27ae60'; ?>;font-weight:bold;">
                            Rs. <?php echo number_format(max(0, $balance)); ?>
                        </td>
                        <td><?php echo getStatusBadge($o['status']); ?></td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <a href="order_detail.php?bill=<?php echo $o['bill_no']; ?>" class="btn btn-sm btn-info" title="View Details"></a>
                                <a href="invoice.php?bill=<?php echo $o['bill_no']; ?>" target="_blank" class="btn btn-sm btn-outline" title="Print invoice">Invoice</a>
                                
                                <?php if ($canEdit): ?>
                                <button class="btn btn-sm btn-warning" 
                                        onclick="editOrder(<?php echo $o['bill_no']; ?>)" 
                                        title="Edit Order">✎</button>
                                <?php endif; ?>
                                
                                <?php if ($canAddAdvance): ?>
                                <button class="btn btn-sm btn-success" 
                                        onclick="addMoreAdvance(<?php echo $o['bill_no']; ?>, <?php echo max(0, $balance); ?>, '<?php echo addslashes($o['party_detail']); ?>')" 
                                        title="Add Advance">+</button>
                                <?php endif; ?>
                                
                                <?php if ($canPay): ?>
                                <a href="payment.php?bill=<?php echo $o['bill_no']; ?>" class="btn btn-sm btn-success" title="Receive Payment"></a>
                                <?php endif; ?>
                                
                                <?php if ($canDeliver): ?>
                                <button class="btn btn-sm btn-success" 
                                        onclick="updateStatus(<?php echo $o['bill_no']; ?>, 'delivered')" 
                                        title="Mark Delivered"></button>
                                <?php endif; ?>
                                
                                <?php if ($canCancel): ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="cancelOrder(<?php echo $o['bill_no']; ?>)" 
                                        title="Cancel Order">✕</button>
                                <?php endif; ?>
                                
                                <?php if (isAdmin() && !$o['cancelled']): ?>
                                <button class="btn btn-sm btn-warning" 
                                        onclick="adminDelete(<?php echo $o['bill_no']; ?>)" 
                                        title="Admin: Permanent Delete" 
                                        style="background:#8b0000;"></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
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

<div class="toast" id="toast"></div>

<script>
var isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;

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
    window.location.href = 'edit_order.php?bill=' + billNo;
}

// ===== STATUS UPDATE =====
function updateStatus(billNo, newStatus) {
    if (!confirm('Change order #' + billNo + ' to "' + newStatus + '"?')) return;
    fetch('update_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bill_no: billNo, status: newStatus})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) { showToast('Updated!', 'success'); setTimeout(function(){ location.reload(); }, 600); }
        else showToast(res.message, 'error');
    });
}

// ===== CANCEL ORDER =====
function cancelOrder(billNo) {
    var msg = isAdminUser ? 
        ' ADMIN: Cancel order #' + billNo + '?\n(Will refund payment if exists)' : 
        'Cancel order #' + billNo + '?';
    if (!confirm(msg)) return;
    var reason = prompt('Reason for cancellation:');
    if (reason === null) return;
    fetch('cancel_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bill_no: billNo, reason: reason})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) { 
            var msg = 'Cancelled';
            if (res.refund_processed) msg += ' (Refund processed)';
            showToast(msg, 'info'); 
            setTimeout(function(){ location.reload(); }, 1000); 
        }
        else showToast(res.message, 'error');
    });
}

// ===== ADMIN DELETE =====
function adminDelete(billNo) {
    if (!confirm(' ADMIN: PERMANENTLY DELETE order #' + billNo + '?\n\nThis cannot be undone!')) return;
    var reason = prompt('Reason for deletion:');
    if (!reason) return;
    fetch('admin_delete_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bill_no: billNo, reason: reason})
    }).then(function(r){return r.json();}).then(function(res) {
        if (res.success) { showToast('Order deleted', 'success'); setTimeout(function(){ location.reload(); }, 1000); }
        else showToast(res.message, 'error');
    });
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
    
    document.getElementById('advRefSection').style.display = (method !== 'cash') ? 'block' : 'none';
}

function closeAdvanceModal() {
    document.getElementById('advanceModal').classList.remove('show');
}

function submitAdvance() {
    var amount = parseInt(document.getElementById('advAmount').value) || 0;
    
    if (amount <= 0) { alert('Enter valid amount'); return; }
    if (amount > advMaxBalance) {
        alert('Amount exceeds balance of Rs. ' + advMaxBalance.toLocaleString());
        return;
    }
    if (!selectedAdvMethodVal) { alert('Select payment method'); return; }
    
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

function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + type + ' show';
    setTimeout(function(){ t.classList.remove('show'); }, 4000);
}

// Auto refresh every 30 seconds
setInterval(function(){ location.reload(); }, 60000);
</script>
</body>
</html>