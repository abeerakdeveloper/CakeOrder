<?php
require_once 'db.php';
require_once 'company.php';

$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) { header('Location: order_list.php'); exit; }

$sql = "SELECT bill_no, party_detail, cell_no, deliver_date, delivery_time,
    SUM(amount) AS total_amount, MAX(advance) AS advance, MAX(paid) AS paid,
    MAX(status) AS status, MAX(flat_disc) AS flat_disc,
    MAX(CASE WHEN sale_type = 'sweet' THEN 1 ELSE 0 END) AS has_sweet,
    MAX(CASE WHEN sale_type = 'weighed' THEN 1 ELSE 0 END) AS has_weighed,
    SUM(CASE WHEN sale_type = 'weighed' THEN amount ELSE 0 END) AS weighed_amount,
    GROUP_CONCAT(CONCAT(qty, 'x ', category, 
        CASE WHEN flavor != '' THEN CONCAT(' (', flavor, ')') ELSE '' END
    ) SEPARATOR '\n') AS items
    FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 GROUP BY bill_no";

$res = mysqli_query($mysqli, $sql);
$order = mysqli_fetch_assoc($res);

if (!$order) { die("Order not found"); }

$totalAmount = $order['total_amount'] - $order['flat_disc'];
$alreadyPaid = $order['advance'] + $order['paid'];
$balance = $totalAmount - $alreadyPaid;
$canPay = in_array($order['status'], array('delivered', 'ready', 'confirmed', 'preparing'));
// Sweet boxes are priced after weighing: no balance is final until the weighed amount is entered
$waiting = ($order['has_sweet'] && !$order['has_weighed']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment - Order #<?php echo $billNo; ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="topbar">
    <h2> Payment - Order #<?php echo $billNo; ?></h2>
    <div class="topbar-right">
        <a href="order_list.php">← Back to Orders</a>
        <a href="dashboard.php">Dashboard</a>
    </div>
</div>

<div class="layout">
    <nav class="sidebar-nav">
        <div class="brand"><?php echo company_logo_html('brand-logo'); ?></div>
        <a href="dashboard.php"><span class="label">Dashboard</span></a>
        <a href="index.php"><span class="label">New Order</span></a>
        <a href="order_list.php"><span class="label">Order List</span></a>
        <a href="kitchen_display.php"><span class="label">Kitchen</span></a>
        <a href="pickup_queue.php"><span class="label">Pickup Queue</span></a>
    </nav>

    <div class="main-content">
        <div class="stepper" style="background:#fff;border-radius:10px;margin-bottom:16px;">
            <div class="step done"><span>1</span> Draft</div><div class="line"></div>
            <div class="step done"><span>2</span> Order</div><div class="line"></div>
            <div class="step active"><span>3</span> Payment</div><div class="line"></div>
            <div class="step"><span>4</span> Receipt</div>
        </div>

        <div class="payment-layout">
            <div class="payment-card">
                <h3> Order Summary</h3>
                <div class="pay-row"><span>Customer:</span><strong><?php echo htmlspecialchars($order['party_detail'] ? $order['party_detail'] : 'Walk-in'); ?></strong></div>
                <div class="pay-row"><span>Phone:</span><span><?php echo htmlspecialchars($order['cell_no']); ?></span></div>
                <div class="pay-row"><span>Delivery:</span><span><?php echo date('d M Y', strtotime($order['deliver_date'])); ?> at <?php echo $order['delivery_time']; ?></span></div>
                <div class="pay-row"><span>Status:</span><?php echo getStatusBadge($order['status']); ?></div>

                <h4 style="margin:16px 0 8px;color:#555;">Items:</h4>
                <div style="background:#f5f0fa;padding:12px;border-radius:8px;margin-bottom:16px;">
                    <?php foreach (explode("\n", $order['items']) as $line): ?>
                    <div style="padding:4px 0;font-size:13px;border-bottom:1px solid #e8e0f0;"><?php echo htmlspecialchars(trim($line)); ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="pay-row"><span>Subtotal:</span><span>Rs. <?php echo number_format($order['total_amount']); ?></span></div>
                <div class="pay-row"><span>Discount:</span><span style="color:#27ae60;">- Rs. <?php echo number_format($order['flat_disc']); ?></span></div>
                <div class="pay-row"><span>Advance:</span><span style="color:#3498db;">- Rs. <?php echo number_format($order['advance']); ?></span></div>
                <?php if ($order['paid'] > 0): ?>
                <div class="pay-row"><span>Previously Paid:</span><span style="color:#27ae60;">- Rs. <?php echo number_format($order['paid']); ?></span></div>
                <?php endif; ?>
                <div class="pay-row total"><span>Balance Due:</span><span><?php if ($waiting): ?>After weighing<?php else: ?>Rs. <?php echo number_format(max(0, $balance)); ?><?php endif; ?></span></div>
            </div>

            <div class="payment-card">
                <h3> Payment</h3>
                <?php if ($waiting): ?>
                <div class="weigh-box">
                    <h3>Sweet boxes: enter the weighed amount</h3>
                    <p class="muted">Sweet boxes are priced after weighing. Enter the total, then take the payment.</p>
                    <label>Total after weighing (Rs)</label>
                    <input type="number" id="weighAmount" min="1" step="1" class="weigh-input">
                    <button type="button" class="btn btn-primary" style="width:100%;margin-top:12px;" id="weighBtn" onclick="saveWeight()">Save weighed amount</button>
                </div>
                <?php elseif ($balance <= 0): ?>
                <div style="text-align:center;padding:40px;">
                    <h3 style="color:#27ae60;">Fully Paid!</h3>
                    <a href="receipt.php?bill=<?php echo $billNo; ?>" class="btn btn-primary" style="margin-top:16px;"> Print Receipt</a>
                </div>
                <?php elseif (!$canPay): ?>
                <div style="text-align:center;padding:40px;">
                    <h3 style="color:#f39c12;">Order Not Ready</h3>
                    <p>Current status: <?php echo getStatusBadge($order['status']); ?></p>
                </div>
                <?php else: ?>

                <label style="font-size:13px;font-weight:600;color:#555;">Payment Method</label>
                <div class="pay-methods">
                    <div class="pay-method" data-method="cash" onclick="selectMethod('cash')">
                        Cash
                    </div>
                    <div class="pay-method" data-method="bank" onclick="selectMethod('bank')">
                        Bank Transfer
                    </div>
                    <div class="pay-method" data-method="card" onclick="selectMethod('card')">
                        Card
                    </div>
                    <div class="pay-method" data-method="easypaisa" onclick="selectMethod('easypaisa')">
                        Easypaisa/JazzCash
                    </div>
                </div>

                <label style="font-size:13px;font-weight:600;color:#555;">Amount to Pay</label>
                <input type="number" id="payAmount" value="<?php echo max(0, $balance); ?>" 
                       style="width:100%;padding:12px;font-size:18px;border:2px solid #6c3483;border-radius:8px;text-align:center;"
                       max="<?php echo max(0, $balance); ?>">

                <div id="cashSection" style="display:none;margin-top:12px;">
                    <label>Cash Received</label>
                    <input type="number" id="cashReceived" value="0" 
                           style="width:100%;padding:12px;font-size:18px;border:1px solid #ddd;border-radius:8px;text-align:center;"
                           oninput="calcChange()">
                    <div id="changeAmount" style="text-align:center;margin-top:8px;font-size:18px;font-weight:bold;color:#27ae60;"></div>
                </div>

                <div id="refSection" style="display:none;margin-top:12px;">
                    <label>Reference / Transaction ID</label>
                    <input type="text" id="payRef" placeholder="Enter reference" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                </div>

                <button class="btn btn-primary" style="width:100%;padding:14px;font-size:16px;margin-top:16px;" 
                        onclick="processPayment()" id="payBtn"> Process Payment</button>

                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button class="btn btn-outline" style="flex:1;" onclick="window.location.href='order_list.php'">← Back</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var selectedMethod = '';
var balance = <?php echo max(0, $balance); ?>;

function selectMethod(method) {
    selectedMethod = method;
    var methods = document.querySelectorAll('.pay-method');
    for (var i = 0; i < methods.length; i++) {
        methods[i].classList.toggle('selected', methods[i].dataset.method === method);
    }
    document.getElementById('cashSection').style.display = method === 'cash' ? 'block' : 'none';
    document.getElementById('refSection').style.display = (method === 'bank' || method === 'card' || method === 'easypaisa') ? 'block' : 'none';
    if (method === 'cash') {
        document.getElementById('cashReceived').value = balance;
        calcChange();
    }
}

function calcChange() {
    var received = parseInt(document.getElementById('cashReceived').value) || 0;
    var paying = parseInt(document.getElementById('payAmount').value) || 0;
    var change = received - paying;
    var el = document.getElementById('changeAmount');
    if (change >= 0) { el.textContent = 'Change: Rs. ' + change.toLocaleString(); el.style.color = '#27ae60'; }
    else { el.textContent = 'Short: Rs. ' + Math.abs(change).toLocaleString(); el.style.color = '#e74c3c'; }
}

function processPayment() {
    if (!selectedMethod) { showToast('Select payment method', 'error'); return; }
    var amount = parseInt(document.getElementById('payAmount').value) || 0;
    if (amount <= 0) { showToast('Enter valid amount', 'error'); return; }
    if (amount > balance) { showToast('Amount exceeds balance', 'error'); return; }

    if (selectedMethod === 'cash') {
        var received = parseInt(document.getElementById('cashReceived').value) || 0;
        if (received < amount) { showToast('Insufficient cash', 'error'); return; }
    }

    if (!confirm('Process Rs. ' + amount.toLocaleString() + ' via ' + selectedMethod.toUpperCase() + '?')) return;

    document.getElementById('payBtn').disabled = true;
    document.getElementById('payBtn').textContent = ' Processing...';

    fetch('process_payment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            bill_no: <?php echo $billNo; ?>,
            amount: amount,
            method: selectedMethod,
            reference: document.getElementById('payRef') ? document.getElementById('payRef').value : ''
        })
    }).then(function(r){ return r.json(); }).then(function(res) {
        if (res.success) {
            showToast('Payment received!', 'success');
            setTimeout(function(){ window.location.href = 'receipt.php?bill=<?php echo $billNo; ?>'; }, 1500);
        } else {
            showToast(res.message, 'error');
            document.getElementById('payBtn').disabled = false;
            document.getElementById('payBtn').textContent = ' Process Payment';
        }
    });
}

function saveWeight() {
    var amt = parseInt(document.getElementById('weighAmount').value) || 0;
    if (amt <= 0) { showToast('Enter the weighed amount', 'error'); return; }
    document.getElementById('weighBtn').disabled = true;
    fetch('save_weight.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bill_no: <?php echo $billNo; ?>, amount: amt})
    }).then(function(r){ return r.json(); }).then(function(res) {
        if (res.success) {
            showToast('Weighed amount saved', 'success');
            setTimeout(function(){ location.reload(); }, 700);
        } else {
            showToast(res.message, 'error');
            document.getElementById('weighBtn').disabled = false;
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