<?php
require_once 'db.php';

// ============================================
// KITCHEN USER ACCESS CONTROL (unchanged)
// ============================================
if (isKitchen()) {
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $currentHost = $_SERVER['HTTP_HOST'];
    $isValidReferrer = false;
    if (!empty($referrer)) {
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        if ($referrerHost === $currentHost) {
            $referrerPath = parse_url($referrer, PHP_URL_PATH);
            if (strpos($referrerPath, 'dashboard.php') === false) $isValidReferrer = true;
        }
    }
    if ($isValidReferrer) header('Location: ' . $referrer);
    else header('Location: kitchen_display.php?msg=no_dashboard_access');
    exit;
}

$today = date('Y-m-d');
$weekLater = date('Y-m-d', strtotime('+7 days'));

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

// Upcoming 7 days
$res = mysqli_query($mysqli, "SELECT COUNT(DISTINCT bill_no) AS cnt FROM cake_order WHERE deliver_date BETWEEN '$today' AND '$weekLater' AND ordercancel = 0 AND status NOT IN ('delivered','paid','cancelled')");
$row = mysqli_fetch_assoc($res);
$upcomingOrders = $row['cnt'];

// Cash vs Card today (only receipts)
$res = mysqli_query($mysqli, "SELECT v_type, COALESCE(SUM(amount),0) AS total FROM gledg WHERE date = '$today' AND acc_code = '112000001' AND amt_type = 'CR' GROUP BY v_type");
$cashTotal = 0; $cardTotal = 0;
while ($r = mysqli_fetch_assoc($res)) {
    if ($r['v_type'] == 'CR') $cashTotal = $r['total'];
    else if ($r['v_type'] == 'BR') $cardTotal = $r['total'];
}

// Pending orders (priority sorted) — same query as before
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
if ($pendingRes) while ($p = mysqli_fetch_assoc($pendingRes)) $allPending[] = $p;

$pageTitle = 'Dashboard';
$pageKey   = 'dashboard';
include 'includes/app_shell.php';
?>

<?php if (isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
<div class="card" style="background:#fdecee;border-color:#f6c9cc;color:#c92a2f;margin-bottom:14px;">
    ⚠ Access Denied: You don't have permission to access that page.
</div>
<?php endif; ?>

<!-- ============ QUICK ORDER BUTTONS ============ -->
<?php if (isPOSUser() || isAdmin()): ?>
<div class="quick-grid">
    <a class="quick-card qc-blue" href="order_cake.php">
        <span class="q-arrow">→</span>
        <span class="q-ic">🎂</span>
        <span class="q-lb">Cake Order</span>
        <span class="q-sb">Category, flavour, shape, size &amp; design photo</span>
    </a>
    <a class="quick-card qc-green" href="order_box.php?type=lunchbox">
        <span class="q-arrow">→</span>
        <span class="q-ic">🍱</span>
        <span class="q-lb">Lunch Box Order</span>
        <span class="q-sb">Build box sets with per-box quantities</span>
    </a>
    <a class="quick-card qc-amber" href="order_box.php?type=sweetsbox">
        <span class="q-arrow">→</span>
        <span class="q-ic">🍬</span>
        <span class="q-lb">Sweets Box Order</span>
        <span class="q-sb">Sweet box sets &amp; combinations</span>
    </a>
    <a class="quick-card qc-pink" href="order_eatables.php">
        <span class="q-arrow">→</span>
        <span class="q-ic">🍽️</span>
        <span class="q-lb">Eatables</span>
        <span class="q-sb">Picture menu — tap to add</span>
    </a>
    <a class="quick-card qc-slate" href="index.php">
        <span class="q-arrow">→</span>
        <span class="q-ic">🧾</span>
        <span class="q-lb">Other (POS)</span>
        <span class="q-sb">Classic counter sale with barcode &amp; voice notes</span>
    </a>
</div>
<?php endif; ?>

<!-- ============ MINI STATS ============ -->
<div class="mini-stats">
    <div class="mini-stat">
        <span class="ic" style="background:#e7f7ef;">💰</span>
        <div>
            <div class="n" style="color:var(--green);">Rs. <?php echo number_format($revenueToday); ?></div>
            <div class="l">Revenue Today</div>
            <div class="s">Cash <?php echo number_format($cashTotal); ?> · Card <?php echo number_format($cardTotal); ?></div>
        </div>
    </div>
    <div class="mini-stat">
        <span class="ic" style="background:#eaf1fe;">🧁</span>
        <div><div class="n"><?php echo $ordersToday; ?></div><div class="l">Orders Today</div></div>
    </div>
    <div class="mini-stat">
        <span class="ic" style="background:#fdecee;">⏳</span>
        <div><div class="n" style="color:var(--red);"><?php echo $pendingOrders; ?></div><div class="l">Pending Orders</div></div>
    </div>
    <div class="mini-stat">
        <span class="ic" style="background:#eef1f6;">📅</span>
        <div><div class="n" style="color:var(--blue);"><?php echo $upcomingOrders; ?></div><div class="l">Upcoming (7 days)</div></div>
    </div>
</div>

<!-- ============ QUICK STATUS CHECK ============ -->
<?php if (isPOSUser() || isAdmin()): ?>
<div class="card mt16">
    <div class="card-title"><span class="ic">🔍</span> Quick Order Status Check <span class="muted" style="font-weight:400;">(for customer inquiries)</span></div>
    <div class="row" style="flex-wrap:nowrap;">
        <input class="inp" id="quickSearch" placeholder="Enter Bill #, customer name or phone..."
               onkeyup="if(event.key=='Enter') quickStatusCheck()">
        <button class="btn btn-primary" style="flex:0 0 auto;" onclick="quickStatusCheck()">🔍 Check Status</button>
    </div>
    <div id="quickResult" class="mt12"></div>
</div>
<?php endif; ?>

<!-- ============ PENDING ORDERS ============ -->
<div class="card mt16">
    <div class="card-title"><span class="ic">📋</span> Pending Orders (<?php echo count($allPending); ?>)
        <span class="spacer"></span>
        <div class="tabs">
            <button class="active priority-tab" onclick="filterPriority('all', this)">All</button>
            <button class="priority-tab" onclick="filterPriority('vip', this)" style="color:#b26a05;">⭐ VIP</button>
            <button class="priority-tab" onclick="filterPriority('urgent', this)" style="color:#c92a2f;">🔴 Urgent</button>
            <button class="priority-tab" onclick="filterPriority('normal', this)">📦 Normal</button>
        </div>
    </div>

    <table class="tbl">
        <thead>
            <tr><th>Bill</th><th>Customer</th><th>Items</th><th>Delivery</th><th>Amount</th><th>Balance</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php if (empty($allPending)): ?>
            <tr><td colspan="8" class="empty" style="padding:26px;">No pending orders 🎉</td></tr>
            <?php endif; ?>
            <?php foreach ($allPending as $o):
                $balance = $o['total'] - $o['disc'] - $o['advance'] - $o['paid'];
                $deliveryDays = (strtotime($o['deliver_date']) - strtotime(date('Y-m-d'))) / 86400;
                $deliveryAlert = '';
                if ($deliveryDays < 0) $deliveryAlert = '<span class="bdg cancelled">⚠ OVERDUE</span>';
                else if ($deliveryDays == 0) $deliveryAlert = '<span class="bdg pending">⏰ TODAY</span>';
                else if ($deliveryDays == 1) $deliveryAlert = '<span class="bdg confirmed">📅 TOMORROW</span>';
                $canEdit = !in_array($o['status'], array('ready', 'delivered', 'paid', 'cancelled'));
            ?>
            <tr class="pending-row" data-priority="<?php echo htmlspecialchars($o['priority']); ?>">
                <td>
                    <strong>#<?php echo $o['bill_no']; ?></strong><br>
                    <span class="bdg prio-<?php echo htmlspecialchars($o['priority']); ?>">
                        <?php echo $o['priority'] == 'vip' ? '⭐ VIP' : ($o['priority'] == 'urgent' ? '🔴 Urgent' : '📦 Normal'); ?>
                    </span>
                </td>
                <td><strong><?php echo htmlspecialchars($o['party_detail']); ?></strong><br><span class="muted" style="font-size:11px;">📱 <?php echo htmlspecialchars($o['cell_no']); ?></span></td>
                <td style="max-width:220px;"><span class="muted" style="font-size:12px;"><?php echo htmlspecialchars($o['items']); ?></span><br><span style="font-size:11px;color:var(--blue);font-weight:600;"><?php echo $o['item_count']; ?> items</span></td>
                <td><strong><?php echo date('d M Y', strtotime($o['deliver_date'])); ?></strong><br><span class="muted" style="font-size:11px;">🕐 <?php echo $o['delivery_time']; ?></span> <?php echo $deliveryAlert; ?></td>
                <td><strong>Rs. <?php echo number_format($o['total'] - $o['disc']); ?></strong><br><span class="muted" style="font-size:11px;color:var(--green);">Paid <?php echo number_format($o['advance'] + $o['paid']); ?></span></td>
                <td style="color:<?php echo $balance > 0 ? 'var(--red)' : 'var(--green)'; ?>;font-weight:700;">Rs. <?php echo number_format(max(0, $balance)); ?></td>
                <td><span class="bdg <?php echo htmlspecialchars($o['status']); ?>"><?php echo ucfirst($o['status']); ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a class="btn btn-sm btn-outline" title="View" href="order_detail.php?bill=<?php echo $o['bill_no']; ?>">👁</a>
                        <?php if ($canEdit && (isPOSUser() || isAdmin())): ?>
                        <button class="btn btn-sm btn-outline" title="Edit order" onclick="editOrder(<?php echo $o['bill_no']; ?>)">✎</button>
                        <button class="btn btn-sm btn-outline" title="Add advance" onclick="addMoreAdvance(<?php echo $o['bill_no']; ?>, <?php echo max(0, $balance); ?>, '<?php echo addslashes($o['party_detail']); ?>')">💰</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============ ADD MORE ADVANCE MODAL (same logic) ============ -->
<div class="modal-overlay" id="advanceModal">
    <div class="modal">
        <h3>💰 Add More Advance Payment</h3>
        <p class="muted" style="font-size:13px;margin-bottom:10px;">
            Order: <strong id="advBillNo"></strong> — Customer: <strong id="advCustomer"></strong>
        </p>
        <p style="color:var(--red);font-size:13px;margin-bottom:14px;">
            Current Balance: <strong id="advBalance"></strong>
        </p>
        <div class="form-group">
            <label>Amount to Add (Rs.)</label>
            <input class="inp" type="number" id="advAmount" min="1" placeholder="Enter amount..." style="font-size:18px;padding:12px;text-align:center;font-weight:bold;color:var(--blue);">
        </div>
        <div class="form-group">
            <label>Payment Method</label>
            <div class="grid-4 mt8">
                <button type="button" class="btn btn-ghost adv-method" data-method="cash" onclick="selectAdvMethod('cash', this)">💵 Cash</button>
                <button type="button" class="btn btn-ghost adv-method" data-method="bank" onclick="selectAdvMethod('bank', this)">🏦 Bank</button>
                <button type="button" class="btn btn-ghost adv-method" data-method="card" onclick="selectAdvMethod('card', this)">💳 Card</button>
                <button type="button" class="btn btn-ghost adv-method" data-method="easypaisa" onclick="selectAdvMethod('easypaisa', this)">📱 Easypaisa</button>
            </div>
        </div>
        <div class="form-group" id="advRefSection" style="display:none;">
            <label>Reference / Transaction ID</label>
            <input class="inp" type="text" id="advReference" placeholder="Enter reference number">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeAdvanceModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitAdvance()" id="advSubmitBtn">💰 Add Advance</button>
        </div>
    </div>
</div>

<script>
// ===== PRIORITY FILTER =====
function filterPriority(priority, btn) {
    document.querySelectorAll('.priority-tab').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var rows = document.querySelectorAll('.pending-row');
    for (var i = 0; i < rows.length; i++) {
        rows[i].style.display = (priority === 'all' || rows[i].dataset.priority === priority) ? '' : 'none';
    }
}

// ===== EDIT ORDER =====
function editOrder(billNo) { window.location.href = 'edit_order.php?bill=' + billNo; }

// ===== ADD MORE ADVANCE (same endpoint) =====
var advBillNoVal = 0, advMaxBalance = 0, selectedAdvMethodVal = '';

function addMoreAdvance(billNo, balance, customer) {
    advBillNoVal = billNo; advMaxBalance = balance; selectedAdvMethodVal = '';
    document.getElementById('advBillNo').textContent = '#' + billNo;
    document.getElementById('advCustomer').textContent = customer;
    document.getElementById('advBalance').textContent = 'Rs. ' + balance.toLocaleString();
    document.getElementById('advAmount').value = '';
    document.getElementById('advAmount').max = balance;
    document.getElementById('advReference').value = '';
    document.getElementById('advRefSection').style.display = 'none';
    document.querySelectorAll('.adv-method').forEach(function (b) { b.classList.remove('btn-primary'); b.classList.add('btn-ghost'); });
    document.getElementById('advanceModal').classList.add('show');
    setTimeout(function () { document.getElementById('advAmount').focus(); }, 200);
}
function selectAdvMethod(method, btn) {
    selectedAdvMethodVal = method;
    document.querySelectorAll('.adv-method').forEach(function (b) { b.classList.remove('btn-primary'); b.classList.add('btn-ghost'); });
    btn.classList.remove('btn-ghost'); btn.classList.add('btn-primary');
    document.getElementById('advRefSection').style.display = (method !== 'cash') ? 'block' : 'none';
}
function closeAdvanceModal() { document.getElementById('advanceModal').classList.remove('show'); }
function submitAdvance() {
    var amount = parseInt(document.getElementById('advAmount').value, 10) || 0;
    if (amount <= 0) { alert('Enter valid amount'); return; }
    if (amount > advMaxBalance) { alert('Amount exceeds balance of Rs. ' + advMaxBalance.toLocaleString()); return; }
    if (!selectedAdvMethodVal) { alert('Select payment method'); return; }
    if (!confirm('Add Rs. ' + amount.toLocaleString() + ' as advance via ' + selectedAdvMethodVal.toUpperCase() + ' to Order #' + advBillNoVal + '?')) return;

    var btn = document.getElementById('advSubmitBtn');
    btn.disabled = true; btn.textContent = '⏳ Processing...';

    fetch('add_more_advance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bill_no: advBillNoVal,
            amount: amount,
            method: selectedAdvMethodVal,
            reference: document.getElementById('advReference').value
        })
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.success) {
            alert('✅ Advance of Rs. ' + amount.toLocaleString() + ' added successfully!\nVoucher #' + res.vno);
            location.reload();
        } else {
            alert('❌ Error: ' + res.message);
            btn.disabled = false; btn.textContent = '💰 Add Advance';
        }
    })
    .catch(function () { alert('Network error'); btn.disabled = false; btn.textContent = '💰 Add Advance'; });
}

// ===== QUICK STATUS CHECK (same endpoint) =====
function quickStatusCheck() {
    var q = document.getElementById('quickSearch').value.trim();
    if (!q) { alert('Enter search term'); return; }
    var result = document.getElementById('quickResult');
    result.innerHTML = '<div class="empty">⏳ Searching...</div>';

    fetch('order_status_api.php?q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.found && res.orders.length > 0) {
                var html = '';
                for (var i = 0; i < res.orders.length; i++) {
                    var o = res.orders[i];
                    html += '<div class="cart-row"><span class="mid"><span class="nm">Bill #' + o.bill_no + ' — ' + escHtml(o.party_detail) + ' (' + escHtml(o.cell_no) + ')</span>' +
                        '<br><span class="sb">📦 ' + escHtml(o.items) + ' · 🚚 ' + escHtml(o.deliver_date) + ' ' + escHtml(o.delivery_time) +
                        ' · 💰 Rs. ' + o.total + ' (Paid ' + o.paid + ' / Balance ' + o.balance + ')</span></span>' +
                        '<span class="bdg ' + escHtml(String(o.status || '').toLowerCase()) + '">' + escHtml(o.status_badge.replace(/<[^>]+>/g, '')) + '</span>' +
                        '<a class="btn btn-sm btn-outline" href="order_detail.php?bill=' + o.bill_no + '">👁 View</a></div>';
                }
                result.innerHTML = html;
            } else {
                result.innerHTML = '<div class="card" style="background:#fdecee;border-color:#f6c9cc;color:#c92a2f;">❌ No orders found matching "' + escHtml(q) + '"</div>';
            }
        });
}

// Keyboard: F1 help, F2 quick search, F4 customer (n/a here)
initShortcuts({ searchId: 'quickSearch' });

// Auto refresh every 2 minutes (same as before)
setInterval(function () { location.reload(); }, 120000);
</script>

<?php include 'includes/app_footer.php'; ?>
