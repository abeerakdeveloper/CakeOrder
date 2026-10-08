<?php
require_once 'db.php';
requireRole(array(1, 3));

date_default_timezone_set('Asia/Karachi');

// Preview next bill no using retvchno
if(false){

$res = mysqli_query($mysqli, "SELECT retvchno('CAK') AS next_bill");
$row = mysqli_fetch_assoc($res);
$nextBillNo = $row && $row['next_bill'] ? intval($row['next_bill']) : 0;
}
$nextBillNo=0;

// Load products from inventory
$prodSql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom 
            FROM inventory 
            WHERE manufacture = 'Finish Product' AND active = 1 
            ORDER BY prod_name ASC LIMIT 500";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) {
    while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;
}

// Top selling products (last 30 days)
$topRes = mysqli_query($mysqli, "SELECT inv_id, category, retail_price, COUNT(*) AS cnt 
    FROM cake_order WHERE inv_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ordercancel = 0 
    GROUP BY inv_id, category ORDER BY cnt DESC LIMIT 8");
$topSelling = array();
if ($topRes) while ($t = mysqli_fetch_assoc($topRes)) $topSelling[] = $t;

$flavors = array('Vanilla', 'Chocolate', 'Strawberry', 'Red Velvet', 'Mango', 'Butterscotch', 'Pineapple', 'Coffee', 'Black Forest', 'Tiramisu');
$shapes = array('Round', 'Square', 'Heart', 'Rectangle', 'Number Shape', 'Custom Shape');
$uoms = array('pound', 'kg', 'pcs', 'dozen');
$priorities = array('normal', 'urgent', 'vip');
$occasions = array('Birthday', 'Anniversary', 'Wedding', 'Engagement', 'Baby Shower', 'Graduation', 'Corporate', 'Other');
$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>BestPOS</title>
<link rel="stylesheet" href="style.css">
<style>
/* Customer suggestion dropdown */
.suggest-box {
    position: absolute; background: #fff; border: 1px solid #6c3483;
    border-radius: 6px; max-height: 300px; overflow-y: auto; z-index: 100;
    min-width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.suggest-item {
    padding: 10px 12px; border-bottom: 1px solid #f0f0f0; cursor: pointer;
    transition: background 0.15s;
}
.suggest-item:hover { background: #f5f0fa; }
.suggest-item .name { font-weight: 600; color: #333; font-size: 13px; }
.suggest-item .meta { font-size: 11px; color: #888; margin-top: 2px; }
.badge-vip { background: #f39c12; color: #fff; padding: 1px 6px; border-radius: 8px; font-size: 9px; margin-left: 4px; }
.badge-loyal { background: #27ae60; color: #fff; padding: 1px 6px; border-radius: 8px; font-size: 9px; margin-left: 4px; }

.tab-row { display: flex; gap: 4px; border-bottom: 2px solid #e8e0f0; margin-bottom: 12px; }
.tab-btn {
    padding: 8px 16px; cursor: pointer; border: none; background: transparent;
    font-size: 12px; font-weight: 600; color: #888; border-bottom: 2px solid transparent;
    margin-bottom: -2px;
}
.tab-btn.active { color: #6c3483; border-bottom-color: #6c3483; }

.section-header {
    font-size: 11px; color: #888; letter-spacing: 1px; font-weight: 700;
    margin: 12px 0 6px; text-transform: uppercase;
}

.customer-info-card {
    background: linear-gradient(135deg, #f5f0fa 0%, #fff 100%);
    border-left: 4px solid #6c3483; padding: 8px 12px; border-radius: 6px;
    margin-top: 8px; font-size: 12px; display: none;
}
.customer-info-card.show { display: block; }
.customer-info-card strong { color: #6c3483; }

.history-item {
    padding: 8px; background: #fff; border-radius: 6px; margin-bottom: 4px;
    border: 1px solid #e8e0f0; font-size: 12px; cursor: pointer;
}
.history-item:hover { border-color: #6c3483; }

.advance-section {
    background: #fff8e1; padding: 10px; border-radius: 6px; margin-top: 10px;
    border: 1px dashed #f39c12; display: none;
}
.advance-section.show { display: block; }

.kbd-hint { font-size: 10px; color: #aaa; }

.product-tag {
    display: inline-block; background: #f5f0fa; padding: 2px 8px; border-radius: 10px;
    font-size: 10px; color: #6c3483; margin-right: 4px;
}

.barcode-input-area {
    background: #fff; padding: 10px; border-radius: 8px;
    border: 2px dashed #6c3483; margin-bottom: 8px; text-align: center;
}
</style>
</head>
<body>

<div class="topbar">
	<?php $branch = getBranchInfo(); ?>
	<h2>🧁 <?php echo htmlspecialchars($branch['name']); ?> — New Order</h2>
    <div class="topbar-right">
        <span style="font-size:13px;background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:12px;">
            <?php echo getRoleName(); ?>
        </span>
        <a href="dashboard.php">Dashboard</a>
        <a href="order_list.php">Orders</a>
        <a href="logout.php" style="background:#e74c3c;padding:6px 12px;border-radius:6px;">🚪</a>
    </div>
</div>

<div class="pos-layout">
    <!-- SIDEBAR WITH TABS -->
    <div class="pos-sidebar">
        <div class="barcode-input-area">
            <input type="text" id="barcodeInput" placeholder="📷 Scan Barcode..." 
                   style="width:100%;padding:6px;border:none;background:transparent;text-align:center;font-size:13px;"
                   onkeyup="if(event.key=='Enter') scanBarcode()">
            <div class="kbd-hint">Press ENTER after scan</div>
        </div>
        
        <input type="text" id="searchItems" class="search" placeholder="🔍 Search products...">
        
        <div class="tab-row">
            <button class="tab-btn active" onclick="switchTab('all', this)">📋 All</button>
            <button class="tab-btn" onclick="switchTab('top', this)">⭐ Top</button>
        </div>
        
        <!-- ALL PRODUCTS -->
        <div id="tab-all" class="product-list" style="max-height: calc(100vh - 280px);">
            <?php foreach ($products as $p): ?>
            <div class="product-item" 
                 data-id="<?php echo $p['inv_id']; ?>"
                 data-name="<?php echo htmlspecialchars($p['prod_name'], ENT_QUOTES); ?>"
                 data-price="<?php echo $p['retail_price']; ?>"
                 data-uom="<?php echo htmlspecialchars($p['uom'], ENT_QUOTES); ?>"
                 data-barcode="<?php echo htmlspecialchars($p['barcode'], ENT_QUOTES); ?>"
                 onclick="addItemFromProduct(this)">
                <div class="name"><?php echo htmlspecialchars($p['prod_name']); ?></div>
                <div class="price">Rs. <?php echo number_format($p['retail_price'], 0); ?></div>
                <div class="meta">
                    <?php echo htmlspecialchars($p['uom']); ?>
                    <?php if (!empty($p['barcode'])): ?> | 🏷 <?php echo htmlspecialchars($p['barcode']); ?><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <div style="text-align:center;padding:20px;color:#999;font-size:12px;">
                No products. Check inventory table.
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TOP SELLING -->
        <div id="tab-top" class="product-list" style="display:none;max-height: calc(100vh - 280px);">
            <?php if (empty($topSelling)): ?>
            <p style="text-align:center;color:#999;font-size:12px;padding:20px;">No sales data yet</p>
            <?php endif; ?>
            <?php foreach ($topSelling as $idx => $t): ?>
            <div class="product-item"
                 data-id="<?php echo $t['inv_id']; ?>"
                 data-name="<?php echo htmlspecialchars($t['category'], ENT_QUOTES); ?>"
                 data-price="<?php echo $t['retail_price']; ?>"
                 data-uom="pcs"
                 onclick="addItemFromProduct(this)">
                <div class="name">
                    <?php if ($idx < 3): ?>
                    <span style="color:#f39c12;">🏆</span>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($t['category']); ?>
                </div>
                <div class="price">Rs. <?php echo number_format($t['retail_price'], 0); ?></div>
                <div class="meta">Sold <?php echo $t['cnt']; ?>x in last 30 days</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN AREA -->
    <div class="pos-main">
        <div class="stepper">
            <div class="step active"><span>1</span> Draft</div>
            <div class="line"></div>
            <div class="step"><span>2</span> Order</div>
            <div class="line"></div>
            <div class="step"><span>3</span> Payment</div>
            <div class="line"></div>
            <div class="step"><span>4</span> Receipt</div>
        </div>

        <!-- CUSTOMER BAR ENHANCED -->
        <div class="customer-bar">
            <div class="field" style="position:relative;">
                <label>📱 Phone (search by number)</label>
                <input type="text" id="custCell" placeholder="0300-0000000" autocomplete="off"
                       oninput="searchCustomer(this.value)" onblur="setTimeout(hideSuggest, 200)">
                <div id="suggestBox" class="suggest-box" style="display:none;"></div>
            </div>
            <div class="field">
                <label>👤 Customer Name</label>
                <input type="text" id="custName" placeholder="Walk-in" value="Walk-in">
            </div>
            <div class="field">
                <label>📅 Delivery Date</label>
                <input type="date" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="field">
                <label>🕐 Time</label>
                <input type="time" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
	    </div>
            <div class="field">
                <label>⚡ Priority</label>
                <select id="priority">
                    <?php foreach ($priorities as $p): ?>
                    <option value="<?php echo $p; ?>"><?php echo ucfirst($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>🎉 Occasion</label>
                <select id="occasion">
                    <option value="">-- None --</option>
                    <?php foreach ($occasions as $o): ?>
                    <option value="<?php echo $o; ?>"><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>📍 Source</label>
                <select id="orderSource">
                    <?php foreach ($sources as $k => $v): ?>
                    <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>🚚 Type</label>
                <select id="deliveryType" onchange="toggleDeliveryAddress()">
                    <option value="pickup">Pickup</option>
                    <option value="delivery">Home Delivery</option>
                </select>
            </div>
        </div>
        
        <!-- DELIVERY ADDRESS ROW -->
        <div id="deliveryAddrRow" style="background:#fff;margin:0 8px 8px;padding:10px 16px;border-radius:8px;border:1px solid #e8e0f0;display:none;">
            <div class="field">
                <label style="font-size:12px;font-weight:600;color:#6c3483;">📍 Delivery Address</label>
                <input type="text" id="deliveryAddress" placeholder="Full delivery address..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
            </div>
        </div>

        <!-- CUSTOMER INFO CARD -->
        <div id="customerInfoCard" class="customer-info-card" style="margin:0 8px 8px;">
            <span id="customerInfoText"></span>
            <button class="btn btn-sm btn-outline" style="float:right;margin-left:8px;" onclick="showCustomerHistory()">📜 View History</button>
        </div>

        <div class="transaction-area">
            <div class="transaction-header">
                <div>
                    <h3>Current Transaction</h3>
                    <p>Order #<span id="billNo"><?php echo $nextBillNo; ?></span> • Customer: <span id="custDisplay">Walk-in</span></p>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline" onclick="showCustomerHistory()" id="historyBtn" style="display:none;">📜 Previous Orders</button>
                    <button class="btn-clear" onclick="clearAll()">🗑 Clear All</button>
                </div>
            </div>
            <div id="cartItems" class="cart-items"></div>
            <div id="emptyMsg" style="text-align:center;padding:40px;color:#999;">
                👈 Click products from the left, scan barcode, or search by customer phone
            </div>
        </div>

        <div class="footer-bar">
            <div class="footer-inner">
                <div class="totals">
                    <div class="row">Subtotal <span id="subtotal">Rs. 0</span></div>
                    <div class="row">Discount <span id="discountDisplay">Rs. 0</span></div>
                    <div class="row grand">Total <span id="total">Rs. 0</span></div>
                    <div class="row" style="color:#27ae60;font-size:12px;">Advance <span id="advanceDisplay">Rs. 0</span></div>
                    <div class="row" style="color:#e74c3c;font-size:12px;font-weight:bold;">Balance <span id="balanceDisplay">Rs. 0</span></div>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <div class="field" style="display:flex;flex-direction:column;gap:2px;">
                        <label style="font-size:11px;color:#6c3483;font-weight:600;">Discount %</label>
                        <input type="number" id="discPercent" value="0" min="0" max="100" style="width:60px;padding:6px;border:1px solid #ddd;border-radius:6px;" onchange="applyDiscPercent()">
                    </div>
                    <div class="field" style="display:flex;flex-direction:column;gap:2px;">
                        <label style="font-size:11px;color:#6c3483;font-weight:600;">Flat Disc</label>
                        <input type="number" id="flatDisc" value="0" min="0" style="width:80px;padding:6px;border:1px solid #ddd;border-radius:6px;" onchange="calcTotals()">
                    </div>
                    <div class="field" style="display:flex;flex-direction:column;gap:2px;">
                        <label style="font-size:11px;color:#6c3483;font-weight:600;">Advance Rs.</label>
                        <input type="number" id="advance" value="0" min="0" style="width:80px;padding:6px;border:1px solid #ddd;border-radius:6px;" onchange="toggleAdvanceMethod()">
                    </div>
                </div>
                <div class="action-btns">
                    <button class="btn-hold" onclick="saveOrder('hold')">⏸ Hold</button>
                    <button class="btn-confirm" onclick="saveOrder('confirmed')">Confirm Order →</button>
                </div>
            </div>
            
            <!-- ADVANCE PAYMENT METHOD -->
            <div id="advanceSection" class="advance-section">
                <label style="font-size:12px;font-weight:600;color:#f39c12;">💰 Advance Payment Method</label>
                <div style="display:flex;gap:6px;margin-top:6px;">
                    <button type="button" class="btn btn-sm btn-outline" data-method="cash" onclick="setAdvanceMethod('cash', this)">💵 Cash</button>
                    <button type="button" class="btn btn-sm btn-outline" data-method="bank" onclick="setAdvanceMethod('bank', this)">🏦 Bank</button>
                    <button type="button" class="btn btn-sm btn-outline" data-method="card" onclick="setAdvanceMethod('card', this)">💳 Card</button>
                    <button type="button" class="btn btn-sm btn-outline" data-method="easypaisa" onclick="setAdvanceMethod('easypaisa', this)">📱 Easypaisa</button>
                </div>
                <small style="display:block;margin-top:4px;color:#888;">Advance will be recorded in GL ledger automatically</small>
            </div>
        </div>
    </div>
</div>

<!-- IMAGE MODAL -->
<div class="modal-overlay" id="imageModal">
    <div class="modal">
        <h3>📷 Attach Image</h3>
        <!-- <input type="file" id="imageFile" accept="image/*" capture="environment" onchange="previewImage(this)" style="width:100%;padding:10px;border:1px dashed #6c3483;border-radius:8px;"> -->
		<input type="file" id="imageFile" accept="image/jpeg,image/jpg,image/png,.jpg,.jpeg,.png" capture="environment" onchange="previewImage(this)" style="width:100%;padding:10px;border:1px dashed #6c3483;border-radius:8px;">
		<small style="display:block;margin-top:6px;color:#888;font-size:11px;">
			📌 Only JPG/PNG accepted. Images auto-resized to 1024px and converted to JPG.
		</small>
        <img id="imgPreview" class="img-preview" style="display:none;max-height:300px;margin-top:10px;">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeImageModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveImage()">Save Image</button>
        </div>
    </div>
</div>

<!-- AUDIO MODAL -->
<div class="modal-overlay" id="audioModal">
    <div class="modal">
        <h3>🎤 Voice Instructions</h3>
        <p style="margin-bottom:12px;color:#888;font-size:13px;">Record voice note for chef</p>
        <div style="text-align:center;margin:20px 0;">
            <button class="btn btn-danger" id="recordBtn" onclick="toggleRecord()" style="width:60px;height:60px;border-radius:50%;font-size:24px;">🎤</button>
            <p id="recordStatus" style="margin-top:8px;font-size:12px;color:#888;">Click to record</p>
        </div>
        <audio id="audioPlayback" controls style="width:100%;display:none;margin:10px 0;"></audio>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeAudioModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveAudio()">Save</button>
        </div>
    </div>
</div>

<!-- CUSTOMER HISTORY MODAL -->
<div class="modal-overlay" id="historyModal">
    <div class="modal" style="max-width:650px;">
        <h3>📜 Customer Order History</h3>
        <div id="historyContent" style="max-height:400px;overflow-y:auto;"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="document.getElementById('historyModal').classList.remove('show')">Close</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var FLAVORS = <?php echo json_encode($flavors); ?>;
var SHAPES = <?php echo json_encode($shapes); ?>;
var UOMS = <?php echo json_encode($uoms); ?>;
var itemCounter = 0;
var currentImageItem = null;
var currentAudioItem = null;
var mediaRecorder = null;
var audioChunks = [];
var tempImageData = '';
var selectedAdvanceMethod = '';
var currentCustomerCell = '';

// ===== TABS =====
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('tab-all').style.display = tab === 'all' ? '' : 'none';
    document.getElementById('tab-top').style.display = tab === 'top' ? '' : 'none';
}

// ===== BARCODE SCANNER =====
function scanBarcode() {
    var code = document.getElementById('barcodeInput').value.trim();
    if (!code) return;
    
    var found = false;
    var items = document.querySelectorAll('.product-item');
    for (var i = 0; i < items.length; i++) {
        if (items[i].dataset.barcode === code) {
            addItemFromProduct(items[i]);
            found = true;
            break;
        }
    }
    
    if (!found) showToast('Barcode not found: ' + code, 'error');
    document.getElementById('barcodeInput').value = '';
    document.getElementById('barcodeInput').focus();
}

// ===== CUSTOMER LOOKUP =====
var searchTimer = null;
function searchCustomer(q) {
    clearTimeout(searchTimer);
    if (q.length < 2) { hideSuggest(); return; }
    
    searchTimer = setTimeout(function() {
        fetch('customer_lookup.php?action=search&q=' + encodeURIComponent(q))
        .then(function(r){return r.json();})
        .then(function(res) {
            var box = document.getElementById('suggestBox');
            if (!res.customers || res.customers.length === 0) {
                hideSuggest();
                return;
            }
            
            var html = '';
            for (var i = 0; i < res.customers.length; i++) {
                var c = res.customers[i];
                html += '<div class="suggest-item" onclick="selectCustomer(' + 
                    "'" + c.cell.replace(/'/g, "\\'") + "', '" + c.name.replace(/'/g, "\\'") + "'" + ')">';
                html += '<div class="name">' + c.name;
                if (c.is_vip) html += '<span class="badge-vip">VIP</span>';
                if (c.is_loyal) html += '<span class="badge-loyal">LOYAL</span>';
                html += '</div>';
                html += '<div class="meta">📱 ' + c.cell + ' • ' + c.orders + ' orders • Spent Rs. ' + c.spent.toLocaleString() + '</div>';
                if (c.fav_flavors) html += '<div class="meta">🍰 Likes: ' + c.fav_flavors + '</div>';
                html += '</div>';
            }
            box.innerHTML = html;
            box.style.display = 'block';
        });
    }, 300);
}

function selectCustomer(cell, name) {
    document.getElementById('custCell').value = cell;
    document.getElementById('custName').value = name;
    document.getElementById('custDisplay').textContent = name;
    currentCustomerCell = cell;
    hideSuggest();
    showCustomerSummary(cell);
}

function hideSuggest() {
    document.getElementById('suggestBox').style.display = 'none';
}

function showCustomerSummary(cell) {
    fetch('customer_lookup.php?action=search&q=' + encodeURIComponent(cell))
    .then(function(r){return r.json();})
    .then(function(res) {
        if (res.customers && res.customers.length > 0) {
            var c = res.customers[0];
            var html = '🎉 <strong>' + c.name + '</strong> — ';
            html += c.orders + ' previous orders, Total spent: <strong>Rs. ' + c.spent.toLocaleString() + '</strong>';
            if (c.fav_flavors) html += ' • Favorite flavors: ' + c.fav_flavors;
            if (c.is_vip) html += ' <span class="badge-vip">VIP Customer</span>';
            if (c.last_order) html += ' • Last: ' + c.last_order;
            document.getElementById('customerInfoText').innerHTML = html;
            document.getElementById('customerInfoCard').classList.add('show');
            document.getElementById('historyBtn').style.display = 'inline-block';
        }
    });
}

function showCustomerHistory() {
    var cell = document.getElementById('custCell').value;
    if (!cell) { showToast('Enter customer phone first', 'error'); return; }
    
    fetch('customer_lookup.php?action=history&cell=' + encodeURIComponent(cell))
    .then(function(r){return r.json();})
    .then(function(res) {
        var html = '';
        if (!res.orders || res.orders.length === 0) {
            html = '<p style="text-align:center;color:#999;padding:20px;">No previous orders</p>';
        } else {
            for (var i = 0; i < res.orders.length; i++) {
                var o = res.orders[i];
                html += '<div class="history-item">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                html += '<strong>Bill #' + o.bill_no + '</strong>' + o.status_badge;
                html += '</div>';
                html += '<div style="margin-top:4px;color:#666;">📅 ' + o.date + ' • Rs. ' + o.total + '</div>';
                html += '<div style="margin-top:2px;font-size:11px;color:#888;">📦 ' + o.items + '</div>';
                if (o.flavors) html += '<div style="font-size:11px;color:#888;">🍰 ' + o.flavors + '</div>';
                html += '<div style="margin-top:6px;">';
                html += '<button class="btn btn-sm btn-primary" onclick="duplicateOrder(' + o.bill_no + ')">📋 Re-order Same Items</button>';
                html += '</div>';
                html += '</div>';
            }
        }
        document.getElementById('historyContent').innerHTML = html;
        document.getElementById('historyModal').classList.add('show');
    });
}

function duplicateOrder(billNo) {
    if (!confirm('Add all items from order #' + billNo + ' to current cart?')) return;
    
    fetch('customer_lookup.php?action=duplicate&bill_no=' + billNo)
    .then(function(r){return r.json();})
    .then(function(res) {
        if (res.items && res.items.length > 0) {
            for (var i = 0; i < res.items.length; i++) {
                var it = res.items[i];
                addItem(it.inv_id, it.name, it.price, it.uom || 'pcs');
                // Set qty, flavor etc on last added item
                setTimeout(function(item){
                    return function() {
                        var lastItem = document.querySelector('.cart-item:last-child');
                        if (lastItem) {
                            lastItem.querySelector('.qty-input').value = item.qty || 1;
                            if (item.flavor) lastItem.querySelector('.flavor').value = item.flavor;
                            if (item.shape) lastItem.querySelector('.shape').value = item.shape;
                            if (item.tiers !== undefined && item.tiers !== null) {
                                lastItem.querySelector('.tiers').value = item.tiers;
                                var t = parseFloat(item.tiers);
                                if (t > 0) {
                                    lastItem.dataset.price = parseFloat(lastItem.dataset.price) / t;
                                }
                            }
                            if (item.cake_message) lastItem.querySelector('.cake-msg').value = item.cake_message;
                            calcTotals();
                        }
                    };
                }(it), i * 50);
            }
            document.getElementById('historyModal').classList.remove('show');
            showToast('Re-ordered ' + res.items.length + ' items', 'success');
        }
    });
}

// ===== DELIVERY TOGGLE =====
function toggleDeliveryAddress() {
    var type = document.getElementById('deliveryType').value;
    document.getElementById('deliveryAddrRow').style.display = type === 'delivery' ? '' : 'none';
}

// ===== CUSTOMER NAME UPDATE =====
document.getElementById('custName').addEventListener('input', function() {
    document.getElementById('custDisplay').textContent = this.value || 'Walk-in';
});

// ===== ADD ITEM =====
function addItemFromProduct(el) {
    addItem(el.dataset.id, el.dataset.name, parseFloat(el.dataset.price), el.dataset.uom);
}

function addItem(invId, name, price, uom) {
    document.getElementById('emptyMsg').style.display = 'none';
    
    itemCounter++;
    var id = 'item_' + itemCounter;
    var cart = document.getElementById('cartItems');
    
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
    div.dataset.image = '';
    div.dataset.audio = '';
    
    div.innerHTML = 
        '<div class="item-image" onclick="openImageModal(\'' + id + '\')" id="imgArea_' + id + '">' +
            '<span class="placeholder">📷</span>' +
            '<span class="upload-icon">+</span>' +
        '</div>' +
        '<div class="item-details">' +
            '<div class="item-top">' +
                '<h4>' + name + '</h4>' +
                '<button class="btn-play" onclick="openAudioModal(\'' + id + '\')" id="audioBtn_' + id + '">🎤</button>' +
                '<button class="btn-play" style="display:none;" onclick="playAudio(\'' + id + '\')" id="audioPlay_' + id + '">▶</button>' +
                '<input type="number" class="price-edit" value="' + price + '" min="0" step="1" ' +
                    'style="width:90px;padding:4px;border:1px solid #ddd;border-radius:4px;font-weight:bold;color:#6c3483;text-align:right;" ' +
                    'onchange="updatePrice(\'' + id + '\', this.value)" title="Edit price">' +
                '<button class="btn-remove" onclick="removeItem(\'' + id + '\')">✕</button>' +
            '</div>' +
            '<div class="item-options">' +
                '<select class="flavor" style="flex:1;min-width:100px;">' + flavorOpts + '</select>' +
                '<select class="shape" style="flex:1;min-width:100px;">' + shapeOpts + '</select>' +
                '<div class="qty-control">' +
                    '<button onclick="changeQty(\'' + id + '\',-1)">−</button>' +
                    '<input type="number" class="qty-input" value="1" min="1" style="width:55px;padding:3px;text-align:center;border:1px solid #ddd;border-radius:4px;font-weight:bold;" onchange="updateQtyDirect(\'' + id + '\', this.value)">' +
                    '<button onclick="changeQty(\'' + id + '\',1)">+</button>' +
                '</div>' +
            '</div>' +
            '<div class="item-options">' +
                '<select class="uom" style="width:90px;">' + uomOpts + '</select>' +
                '<input type="number" class="tiers" value="1" min="0" style="width:55px;" title="UOM QTY" oninput="calcTotals()">' +
                '<input type="text" class="cake-msg" placeholder="✍ Cake message (will be written on cake)..." style="flex:1;min-width:150px;">' +
            '</div>' +
            '<input type="text" class="note" placeholder="📝 Special Instructions (decorations, allergies, color, etc.)...">' +
        '</div>';
    
    cart.appendChild(div);
    calcTotals();
    showToast('Added: ' + name, 'success');
    
    // Auto-focus on flavor of newly added item
    setTimeout(function() {
        var newItem = document.getElementById(id);
        if (newItem) newItem.querySelector('.flavor').focus();
    }, 100);
}

function updatePrice(id, newPrice) {
    var item = document.getElementById(id);
    var newP = parseFloat(newPrice) || 0;
    var tInput = item.querySelector('.tiers');
    var tiers = (tInput && tInput.value !== '') ? parseFloat(tInput.value) : 1;
    item.dataset.price = tiers > 0 ? newP / tiers : newP;
    calcTotals();
}

function updateQtyDirect(id, newQty) {
    var q = parseInt(newQty) || 1;
    if (q < 1) q = 1;
    var item = document.getElementById(id);
    item.querySelector('.qty-input').value = q;
    calcTotals();
}

function removeItem(id) {
    var el = document.getElementById(id);
    if (el) el.remove();
    calcTotals();
    if (document.querySelectorAll('.cart-item').length === 0) {
        document.getElementById('emptyMsg').style.display = 'block';
    }
}

function changeQty(id, delta) {
    var item = document.getElementById(id);
    var input = item.querySelector('.qty-input');
    var q = parseInt(input.value) + delta;
    if (q < 1) { removeItem(id); return; }
    input.value = q;
    calcTotals();
}

// ===== CALCULATIONS =====
function calcTotals() {
    var subtotal = 0;
    var items = document.querySelectorAll('.cart-item');
    for (var i = 0; i < items.length; i++) {
        var basePrice = parseFloat(items[i].dataset.price) || 0;
        var tInput = items[i].querySelector('.tiers');
        var tiers = (tInput && tInput.value !== '') ? parseFloat(tInput.value) : 1;
        var totalItemPrice = basePrice * tiers;
        
        var priceEdit = items[i].querySelector('.price-edit');
        if (priceEdit) {
            priceEdit.value = totalItemPrice;
        }

        var qty = parseInt(items[i].querySelector('.qty-input').value) || 1;
        subtotal += totalItemPrice * qty;
    }
    var discount = parseInt(document.getElementById('flatDisc').value) || 0;
    var advance = parseInt(document.getElementById('advance').value) || 0;
    var total = subtotal - discount;
    var balance = total - advance;
    
    document.getElementById('subtotal').textContent = 'Rs. ' + subtotal.toLocaleString();
    document.getElementById('discountDisplay').textContent = 'Rs. ' + discount.toLocaleString();
    document.getElementById('total').textContent = 'Rs. ' + Math.max(0, total).toLocaleString();
    document.getElementById('advanceDisplay').textContent = 'Rs. ' + advance.toLocaleString();
    document.getElementById('balanceDisplay').textContent = 'Rs. ' + Math.max(0, balance).toLocaleString();
}

function applyDiscPercent() {
    var pct = parseFloat(document.getElementById('discPercent').value) || 0;
    var subtotal = 0;
    var items = document.querySelectorAll('.cart-item');
    for (var i = 0; i < items.length; i++) {
        var basePrice = parseFloat(items[i].dataset.price) || 0;
        var tInput = items[i].querySelector('.tiers');
        var tiers = (tInput && tInput.value !== '') ? parseFloat(tInput.value) : 1;
        var totalItemPrice = basePrice * tiers;
        subtotal += totalItemPrice * (parseInt(items[i].querySelector('.qty-input').value) || 1);
    }
    var disc = Math.round(subtotal * pct / 100);
    document.getElementById('flatDisc').value = disc;
    calcTotals();
}

function toggleAdvanceMethod() {
    var adv = parseInt(document.getElementById('advance').value) || 0;
    document.getElementById('advanceSection').classList.toggle('show', adv > 0);
    calcTotals();
}

function setAdvanceMethod(method, btn) {
    selectedAdvanceMethod = method;
    document.querySelectorAll('#advanceSection .btn').forEach(function(b){ 
        b.classList.remove('btn-warning'); b.classList.add('btn-outline'); 
    });
    btn.classList.remove('btn-outline'); btn.classList.add('btn-warning');
}

function clearAll() {
    if (!confirm('Clear all items?')) return;
    document.getElementById('cartItems').innerHTML = '';
    document.getElementById('emptyMsg').style.display = 'block';
    document.getElementById('advance').value = 0;
    document.getElementById('flatDisc').value = 0;
    document.getElementById('discPercent').value = 0;
    document.getElementById('advanceSection').classList.remove('show');
    selectedAdvanceMethod = '';
    calcTotals();
}

// ===== IMAGE / AUDIO (kept same as before) =====
function openImageModal(itemId) {
    currentImageItem = itemId;
    document.getElementById('imageModal').classList.add('show');
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imageFile').value = '';
    tempImageData = '';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('show');
    currentImageItem = null;
}

function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    
    var file = input.files[0];
    
    // ============================================
    // VALIDATE FILE TYPE - Only allow JPG/JPEG/PNG
    // ============================================
    var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    var allowedExts = /\.(jpg|jpeg|png)$/i;
    
    if (allowedTypes.indexOf(file.type) === -1 && !allowedExts.test(file.name)) {
        showToast('❌ Only JPG, JPEG, or PNG images are allowed!', 'error');
        input.value = '';
        return;
    }
    
    // ============================================
    // VALIDATE FILE SIZE (max 20MB before processing)
    // ============================================
    var maxFileSizeMB = 20;
    if (file.size > maxFileSizeMB * 1024 * 1024) {
        showToast('❌ Image too large! Max ' + maxFileSizeMB + 'MB allowed.', 'error');
        input.value = '';
        return;
    }
    
    var fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
    showToast('📤 Processing ' + fileSizeMB + ' MB image...', 'info');
    
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = new Image();
        img.onload = function() {
            // ============================================
            // RESIZE: Max 1024px on longest side
            // ============================================
            var canvas = document.createElement('canvas');
            var maxSize = 1024;
            var w = img.width, h = img.height;
            
            if (w > h) {
                if (w > maxSize) { 
                    h = Math.round(h * maxSize / w); 
                    w = maxSize; 
                }
            } else {
                if (h > maxSize) { 
                    w = Math.round(w * maxSize / h); 
                    h = maxSize; 
                }
            }
            
            canvas.width = w;
            canvas.height = h;
            
            // ============================================
            // CONVERT TO JPG with white background
            // (in case original is PNG with transparency)
            // ============================================
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            
            // Force JPEG output at 75% quality
            tempImageData = canvas.toDataURL('image/jpeg', 0.75);
            
            // Calculate compressed size
            var compressedKB = Math.round((tempImageData.length * 3 / 4) / 1024);
            
            document.getElementById('imgPreview').src = tempImageData;
            document.getElementById('imgPreview').style.display = 'block';
            
            showToast('✅ Image ready! Resized to ' + w + 'x' + h + 'px (' + compressedKB + ' KB)', 'success');
        };
        img.onerror = function() {
            showToast('❌ Invalid image file', 'error');
            input.value = '';
        };
        img.src = e.target.result;
    };
    reader.onerror = function() {
        showToast('❌ Failed to read file', 'error');
    };
    reader.readAsDataURL(file);
}

function saveImage() {
    if (!tempImageData || !currentImageItem) { closeImageModal(); return; }
    var item = document.getElementById(currentImageItem);
    item.dataset.image = tempImageData;
    document.getElementById('imgArea_' + currentImageItem).innerHTML = 
        '<img src="' + tempImageData + '"><span class="upload-icon">✎</span>';
    showToast('Image attached', 'success');
    closeImageModal();
}

function openAudioModal(itemId) {
    currentAudioItem = itemId;
    document.getElementById('audioModal').classList.add('show');
    document.getElementById('audioPlayback').style.display = 'none';
    document.getElementById('recordStatus').textContent = 'Click to record';
    audioChunks = [];
}

function closeAudioModal() {
    document.getElementById('audioModal').classList.remove('show');
    if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
    currentAudioItem = null;
}

function toggleRecord() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        document.getElementById('recordBtn').textContent = '🎤';
        document.getElementById('recordStatus').textContent = 'Recording saved';
    } else {
        if (!navigator.mediaDevices) { showToast('Microphone not supported', 'error'); return; }
        navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = function(e) { audioChunks.push(e.data); };
            mediaRecorder.onstop = function() {
                var blob = new Blob(audioChunks, {type: 'audio/webm'});
                var audio = document.getElementById('audioPlayback');
                audio.src = URL.createObjectURL(blob);
                audio.style.display = 'block';
                var reader = new FileReader();
                reader.onload = function() {
                    if (currentAudioItem) document.getElementById(currentAudioItem).dataset.audio = reader.result;
                };
                reader.readAsDataURL(blob);
                stream.getTracks().forEach(function(t) { t.stop(); });
            };
            mediaRecorder.start();
            document.getElementById('recordBtn').textContent = '⏹';
            document.getElementById('recordStatus').textContent = '🔴 Recording...';
        }).catch(function() { showToast('Microphone denied', 'error'); });
    }
}

function saveAudio() {
    if (currentAudioItem) {
        var item = document.getElementById(currentAudioItem);
        if (item.dataset.audio) {
            document.getElementById('audioBtn_' + currentAudioItem).style.display = 'none';
            document.getElementById('audioPlay_' + currentAudioItem).style.display = 'inline-block';
            showToast('Voice note saved', 'success');
        }
    }
    closeAudioModal();
}

function playAudio(itemId) {
    var audioData = document.getElementById(itemId).dataset.audio;
    if (audioData) new Audio(audioData).play();
}

// ===== SAVE ORDER =====
function collectOrderData() {
    var items = [];
    var elements = document.querySelectorAll('.cart-item');
    for (var i = 0; i < elements.length; i++) {
        var item = elements[i];
        var tInput = item.querySelector('.tiers');
        var tVal = (tInput && tInput.value !== '') ? parseFloat(tInput.value) : 1;
        items.push({
            inv_id: item.dataset.invId,
            name: item.dataset.name,
            category: item.dataset.name,
            price: parseFloat(item.querySelector('.price-edit').value) || 0,
            qty: parseInt(item.querySelector('.qty-input').value) || 1,
            flavor: item.querySelector('.flavor').value,
            shape: item.querySelector('.shape').value,
            uom: item.querySelector('.uom').value,
            tiers: tVal,
            cake_message: item.querySelector('.cake-msg').value,
            note: item.querySelector('.note').value,
            image_data: item.dataset.image || '',
            audio_data: item.dataset.audio || ''
        });
    }
    return items;
}

function saveOrder(status) {
    var items = collectOrderData();
	console.log('called');
    if (items.length === 0) { showToast('Cart is empty!', 'error'); return; }
    
    var advance = parseInt(document.getElementById('advance').value) || 0;
    if (advance > 0 && !selectedAdvanceMethod) {
        showToast('Select advance payment method', 'error');
        return;
    }
    
    var custName = document.getElementById('custName').value || 'Walk-in';
    var custCell = document.getElementById('custCell').value;
    var deliveryType = document.getElementById('deliveryType').value;
    var deliveryAddress = document.getElementById('deliveryAddress') ? document.getElementById('deliveryAddress').value : '';
    
    if (deliveryType === 'delivery' && !deliveryAddress) {
        showToast('Enter delivery address', 'error');
        return;
    }

    var data = {
        items: items,
        status: status,
        party_detail: custName,
        cell_no: custCell,
        deliver_date: document.getElementById('deliverDate').value,
        delivery_time: document.getElementById('deliverTime').value,
        priority: document.getElementById('priority').value,
        flat_disc: parseInt(document.getElementById('flatDisc').value) || 0,
        advance: advance,
        advance_method: selectedAdvanceMethod,
        occasion: document.getElementById('occasion').value,
        delivery_type: deliveryType,
        delivery_address: deliveryAddress,
        source: document.getElementById('orderSource').value
    };

    showToast('Saving order...', 'info');
    
    var btn = document.querySelector('.btn-confirm');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Saving...'; }

    fetch('save_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast('✅ Order #' + res.bill_no + ' saved!', 'success');
            if (status === 'confirmed') {
                if (confirm('Order #' + res.bill_no + ' saved!\n\nPrint receipt now?')) {
                    window.open('receipt.php?bill=' + res.bill_no, '_blank');
                }
                setTimeout(function() { window.location.href = 'index.php'; }, 1500);
            } else {
                document.getElementById('cartItems').innerHTML = '';
                document.getElementById('emptyMsg').style.display = 'block';
                calcTotals();
                if (btn) { btn.disabled = false; btn.textContent = 'Confirm Order →'; }
            }
        } else {
            showToast('Error: ' + res.message, 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Confirm Order →'; }
        }
    })
    .catch(function() { 
        showToast('Network error', 'error'); 
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm Order →'; }
    });
}

function showToast(msg, type) {
    var toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast ' + type + ' show';
    setTimeout(function() { toast.classList.remove('show'); }, 3500);
}

// ===== SEARCH PRODUCTS =====
document.getElementById('searchItems').addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    var items = document.querySelectorAll('.pos-sidebar .product-item');
    for (var i = 0; i < items.length; i++) {
        var text = items[i].textContent.toLowerCase();
        items[i].style.display = text.indexOf(q) > -1 ? '' : 'none';
    }
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    // F2 = focus barcode
    if (e.key === 'F2') { e.preventDefault(); document.getElementById('barcodeInput').focus(); }
    // F4 = focus customer search
    if (e.key === 'F4') { e.preventDefault(); document.getElementById('custCell').focus(); }
    // F9 = Confirm Order
    if (e.key === 'F9') { e.preventDefault(); saveOrder('confirmed'); }
    // F10 = Hold
    if (e.key === 'F10') { e.preventDefault(); saveOrder('hold'); }
    // Esc = clear barcode
    if (e.key === 'Escape') { document.getElementById('barcodeInput').value = ''; }
});

// Show keyboard shortcuts hint on load
setTimeout(function(){
    showToast('💡 Shortcuts: F2=Barcode | F4=Customer | F9=Confirm | F10=Hold', 'info');
}, 1000);

// Auto-focus barcode on load
document.getElementById('barcodeInput').focus();
</script>
</body>
</html>