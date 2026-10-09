<?php
require_once 'db.php';
requireRole(array(1, 3)); // POS user and Admin

$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) { header('Location: dashboard.php'); exit; }

// Fetch order
$res = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 ORDER BY id");
$items = array();
while ($r = mysqli_fetch_assoc($res)) $items[] = $r;

if (empty($items)) { die("Order not found"); }

$first = $items[0];

// Permission check
if (in_array($first['status'], array('ready','delivered','paid','cancelled'))) {
    die('<div style="padding:40px;text-align:center;font-family:Arial;"><h2 style="color:#e74c3c;">❌ Cannot Edit</h2><p>Order is already <strong>' . strtoupper($first['status']) . '</strong>.</p><a href="dashboard.php">← Back to Dashboard</a></div>');
}

// Load products from inventory
$prodSql = "SELECT i.inv_id, i.prod_name, i.retail_price, i.manualbc AS barcode, i.packing AS uom
            FROM inventory i
            INNER JOIN cake_product c ON c.inv_id = i.inv_id
            WHERE i.manufacture = 'Finish Product' AND i.active = 1
            ORDER BY i.prod_name ASC LIMIT 1000";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) {
    while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;
}

$flavors = array('Vanilla', 'Chocolate', 'Strawberry', 'Red Velvet', 'Mango', 'Butterscotch', 'Pineapple', 'Coffee', 'Black Forest', 'Tiramisu');
$shapes = array('Round', 'Square', 'Heart', 'Rectangle', 'Number Shape', 'Custom Shape');
$uoms = array('pound', 'kg', 'pcs', 'dozen');
$priorities = array('normal', 'urgent', 'vip');

// Calculate totals
$currentTotal = 0;
foreach ($items as $it) $currentTotal += $it['amount'];
$currentNet = $currentTotal - $first['flat_disc'];
$currentBalance = $currentNet - $first['advance'] - $first['paid'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit Order #<?php echo $billNo; ?></title>
<link rel="stylesheet" href="style.css">
<style>
.edit-warning {
    background: #fff3e0; border-left: 5px solid #f39c12; 
    padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px;
}
.existing-item {
    background: #f5f0fa; padding: 10px; border-radius: 8px; margin-bottom: 6px;
    border: 1px solid #e0d6eb;
}
.existing-item .info { display: flex; justify-content: space-between; align-items: center; }
</style>
</head>
<body>

<?php $branch = getBranchInfo(); ?>
<div class="topbar">
    <h2>✎ <?php echo htmlspecialchars($branch['name']); ?> — Edit Order #<?php echo $billNo; ?></h2>
    <div class="topbar-right">
        <a href="dashboard.php">← Dashboard</a>
        <a href="order_detail.php?bill=<?php echo $billNo; ?>">View Details</a>
        <a href="logout.php" style="background:#e74c3c;padding:6px 12px;border-radius:6px;">🚪</a>
    </div>
</div>

<div class="pos-layout">
    <!-- LEFT: PRODUCT LIST FOR ADDING -->
    <div class="pos-sidebar">
        <input type="text" id="searchItems" class="search" placeholder="🔍 Search products to add...">
        <h4 class="quick-title">➕ ADD PRODUCTS</h4>
        <div class="product-list" id="productList" style="max-height: calc(100vh - 200px);">
            <?php foreach ($products as $p): ?>
            <div class="product-item" 
                 data-id="<?php echo $p['inv_id']; ?>"
                 data-name="<?php echo htmlspecialchars($p['prod_name'], ENT_QUOTES); ?>"
                 data-price="<?php echo $p['retail_price']; ?>"
                 data-uom="<?php echo htmlspecialchars($p['uom'], ENT_QUOTES); ?>"
                 onclick="addNewItem(this)">
                <div class="name"><?php echo htmlspecialchars($p['prod_name']); ?></div>
                <div class="price">Rs. <?php echo number_format($p['retail_price'], 0); ?></div>
                <div class="meta"><?php echo htmlspecialchars($p['uom']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN AREA -->
    <div class="pos-main">
        <div class="edit-warning" style="margin:8px;">
            ⚠ <strong>Editing Order:</strong> You can modify customer info, change priority/delivery, and add NEW items. 
            <br>Existing items <strong>cannot be removed</strong> (only by admin from order details page).
            <br>Status: <?php echo getStatusBadge($first['status']); ?>
        </div>
        
        <!-- CUSTOMER INFO -->
        <div class="customer-bar">
            <div class="field">
                <label>📱 Phone</label>
                <input type="text" id="custCell" value="<?php echo htmlspecialchars($first['cell_no']); ?>">
            </div>
            <div class="field">
                <label>👤 Customer Name</label>
                <input type="text" id="custName" value="<?php echo htmlspecialchars($first['party_detail']); ?>">
            </div>
            <div class="field">
                <label>📅 Delivery Date</label>
                <input type="date" id="deliverDate" value="<?php echo $first['deliver_date']; ?>" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="field">
                <label>🕐 Time</label>
                <input type="time" id="deliverTime" value="<?php echo $first['delivery_time']; ?>">
            </div>
            <div class="field">
                <label>⚡ Priority</label>
                <select id="priority">
                    <?php foreach ($priorities as $p): ?>
                    <option value="<?php echo $p; ?>" <?php if($first['priority']==$p) echo 'selected'; ?>><?php echo ucfirst($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="transaction-area">
            <div class="transaction-header">
                <div>
                    <h3>Order Items</h3>
                    <p>Bill #<?php echo $billNo; ?> • <span id="totalItems"><?php echo count($items); ?></span> existing items + <span id="newItemsCount">0</span> new</p>
                </div>
            </div>
            
            <!-- EXISTING ITEMS (Read-only summary) -->
            <h4 style="color:#6c3483;margin-bottom:8px;font-size:13px;">📦 Existing Items (locked):</h4>
            <?php foreach ($items as $idx => $item): ?>
            <div class="existing-item">
                <div class="info">
                    <div>
                        <strong><?php echo htmlspecialchars($item['category']); ?></strong>
                        <?php if ($item['flavor']): ?> 
                            <span style="color:#666;font-size:12px;">(<?php echo htmlspecialchars($item['flavor']); ?>)</span>
                        <?php endif; ?>
                        <?php if ($item['shape']): ?>
                            <span style="color:#666;font-size:12px;"> • <?php echo $item['shape']; ?></span>
                        <?php endif; ?>
                        <?php if ($item['cake_message']): ?>
                            <br><small style="color:#888;">✍ "<?php echo htmlspecialchars($item['cake_message']); ?>"</small>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:right;">
                        <strong style="color:#6c3483;"><?php echo $item['qty']; ?>x Rs. <?php echo number_format($item['retail_price']); ?></strong><br>
                        <small>= Rs. <?php echo number_format($item['amount']); ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <h4 style="color:#27ae60;margin:16px 0 8px;font-size:13px;">➕ New Items to Add:</h4>
            <div id="newItems" class="cart-items"></div>
            <div id="emptyNewMsg" style="text-align:center;padding:20px;color:#999;font-size:12px;">
                👈 Click products from the left to add new items
            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="footer-bar">
            <div class="footer-inner">
                <div class="totals">
                    <div class="row">Existing Total <span>Rs. <?php echo number_format($currentTotal); ?></span></div>
                    <div class="row">New Items <span id="newSubtotal">Rs. 0</span></div>
                    <div class="row">Discount <span>- Rs. <?php echo number_format($first['flat_disc']); ?></span></div>
                    <div class="row grand">New Total <span id="grandTotal">Rs. <?php echo number_format($currentNet); ?></span></div>
                    <div class="row" style="color:#3498db;font-size:12px;">Already Paid <span>Rs. <?php echo number_format($first['advance'] + $first['paid']); ?></span></div>
                    <div class="row" style="color:#e74c3c;font-size:12px;font-weight:bold;">New Balance <span id="newBalance">Rs. <?php echo number_format(max(0, $currentBalance)); ?></span></div>
                </div>
                <div class="action-btns">
                    <button class="btn-hold" onclick="window.location.href='dashboard.php'">✕ Cancel</button>
                    <button class="btn-confirm" onclick="saveChanges()">💾 Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var FLAVORS = <?php echo json_encode($flavors); ?>;
var SHAPES = <?php echo json_encode($shapes); ?>;
var UOMS = <?php echo json_encode($uoms); ?>;
var BILL_NO = <?php echo $billNo; ?>;
var EXISTING_TOTAL = <?php echo $currentTotal; ?>;
var DISCOUNT = <?php echo $first['flat_disc']; ?>;
var ALREADY_PAID = <?php echo $first['advance'] + $first['paid']; ?>;
var newItemCounter = 0;

function addNewItem(el) {
    var invId = el.dataset.id;
    var name = el.dataset.name;
    var price = parseFloat(el.dataset.price);
    var uom = el.dataset.uom;
    
    document.getElementById('emptyNewMsg').style.display = 'none';
    
    newItemCounter++;
    var id = 'new_' + newItemCounter;
    var cart = document.getElementById('newItems');
    
    var flavorOpts = '<option value="">Flavor...</option>';
    for (var i = 0; i < FLAVORS.length; i++) flavorOpts += '<option value="' + FLAVORS[i] + '">' + FLAVORS[i] + '</option>';
    
    var shapeOpts = '<option value="">Shape...</option>';
    for (var j = 0; j < SHAPES.length; j++) shapeOpts += '<option value="' + SHAPES[j] + '">' + SHAPES[j] + '</option>';
    
    var uomOpts = '';
    for (var k = 0; k < UOMS.length; k++) {
        var sel = (UOMS[k] === uom) ? 'selected' : '';
        uomOpts += '<option value="' + UOMS[k] + '" ' + sel + '>' + UOMS[k] + '</option>';
    }

    var div = document.createElement('div');
    div.className = 'cart-item fade-in';
    div.id = id;
    div.dataset.invId = invId;
    div.dataset.price = price;
    div.dataset.name = name;
    
    div.innerHTML = 
        '<div class="item-image"><span class="placeholder">🆕</span></div>' +
        '<div class="item-details">' +
            '<div class="item-top">' +
                '<h4>' + name + '</h4>' +
                '<input type="number" class="price-edit" value="' + price + '" min="0" ' +
                    'style="width:90px;padding:4px;border:1px solid #ddd;border-radius:4px;font-weight:bold;color:#6c3483;text-align:right;" ' +
                    'onchange="updateNewPrice(\'' + id + '\', this.value)">' +
                '<button class="btn-remove" onclick="removeNewItem(\'' + id + '\')">✕</button>' +
            '</div>' +
            '<div class="item-options">' +
                '<select class="flavor" style="flex:1;min-width:100px;">' + flavorOpts + '</select>' +
                '<select class="shape" style="flex:1;min-width:100px;">' + shapeOpts + '</select>' +
                '<div class="qty-control">' +
                    '<button onclick="changeNewQty(\'' + id + '\',-1)">−</button>' +
                    '<input type="number" class="qty-input" value="1" min="1" style="width:55px;padding:3px;text-align:center;border:1px solid #ddd;border-radius:4px;font-weight:bold;" onchange="calcNewTotals()">' +
                    '<button onclick="changeNewQty(\'' + id + '\',1)">+</button>' +
                '</div>' +
            '</div>' +
            '<div class="item-options">' +
                '<select class="uom" style="width:90px;">' + uomOpts + '</select>' +
                '<input type="number" class="tiers" value="1" min="1" max="5" style="width:55px;" title="Tiers">' +
                '<input type="text" class="cake-msg" placeholder="✍ Cake message..." style="flex:1;min-width:150px;">' +
            '</div>' +
            '<input type="text" class="note" placeholder="📝 Special Instructions...">' +
        '</div>';
    
    cart.appendChild(div);
    calcNewTotals();
    showToast('Added: ' + name, 'success');
}

function removeNewItem(id) {
    var el = document.getElementById(id);
    if (el) el.remove();
    calcNewTotals();
    var newCount = document.querySelectorAll('#newItems .cart-item').length;
    if (newCount === 0) {
        document.getElementById('emptyNewMsg').style.display = 'block';
    }
}

function changeNewQty(id, delta) {
    var item = document.getElementById(id);
    var input = item.querySelector('.qty-input');
    var q = parseInt(input.value) + delta;
    if (q < 1) { removeNewItem(id); return; }
    input.value = q;
    calcNewTotals();
}

function updateNewPrice(id, newPrice) {
    document.getElementById(id).dataset.price = parseFloat(newPrice) || 0;
    calcNewTotals();
}

function calcNewTotals() {
    var newSubtotal = 0;
    var items = document.querySelectorAll('#newItems .cart-item');
    for (var i = 0; i < items.length; i++) {
        var price = parseFloat(items[i].dataset.price);
        var qty = parseInt(items[i].querySelector('.qty-input').value) || 1;
        newSubtotal += price * qty;
    }
    
    document.getElementById('newSubtotal').textContent = 'Rs. ' + newSubtotal.toLocaleString();
    document.getElementById('newItemsCount').textContent = items.length;
    
    var grandTotal = EXISTING_TOTAL + newSubtotal - DISCOUNT;
    var newBal = grandTotal - ALREADY_PAID;
    
    document.getElementById('grandTotal').textContent = 'Rs. ' + grandTotal.toLocaleString();
    document.getElementById('newBalance').textContent = 'Rs. ' + Math.max(0, newBal).toLocaleString();
}

function collectNewItems() {
    var items = [];
    var elements = document.querySelectorAll('#newItems .cart-item');
    for (var i = 0; i < elements.length; i++) {
        var item = elements[i];
        items.push({
            inv_id: item.dataset.invId,
            name: item.dataset.name,
            category: item.dataset.name,
            price: parseFloat(item.dataset.price),
            qty: parseInt(item.querySelector('.qty-input').value) || 1,
            flavor: item.querySelector('.flavor').value,
            shape: item.querySelector('.shape').value,
            uom: item.querySelector('.uom').value,
            tiers: parseInt(item.querySelector('.tiers').value) || 1,
            cake_message: item.querySelector('.cake-msg').value,
            note: item.querySelector('.note').value
        });
    }
    return items;
}

function saveChanges() {
    var data = {
        bill_no: BILL_NO,
        party_detail: document.getElementById('custName').value,
        cell_no: document.getElementById('custCell').value,
        deliver_date: document.getElementById('deliverDate').value,
        delivery_time: document.getElementById('deliverTime').value,
        priority: document.getElementById('priority').value,
        new_items: collectNewItems()
    };
    
    if (!confirm('Save changes to Order #' + BILL_NO + '?\n\n• Customer info will be updated\n• ' + data.new_items.length + ' new item(s) will be added')) return;
    
    var btn = document.querySelector('.btn-confirm');
    btn.disabled = true;
    btn.textContent = '⏳ Saving...';
    
    fetch('save_order_edit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.success) {
            alert('✅ Order updated successfully!');
            window.location.href = 'dashboard.php';
        } else {
            alert('❌ Error: ' + res.message);
            btn.disabled = false;
            btn.textContent = '💾 Save Changes';
        }
    });
}

function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + type + ' show';
    setTimeout(function(){ t.classList.remove('show'); }, 3000);
}

document.getElementById('searchItems').addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    var items = document.querySelectorAll('.product-item');
    for (var i = 0; i < items.length; i++) {
        items[i].style.display = items[i].textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
    }
});
</script>
</body>
</html>