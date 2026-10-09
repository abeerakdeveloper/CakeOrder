<?php
require_once 'db.php';
requireRole(array(1, 3));
require_once 'order_lines.php';
require_once 'order_store.php';
require_once 'company.php';

date_default_timezone_set('Asia/Karachi');

// Cake picker: only the products ticked as cakes (Cake Products page, admin)
$products = array();
$prodRes = mysqli_query($mysqli, "SELECT i.inv_id, i.prod_name, i.retail_price, i.manualbc AS barcode, i.packing AS uom
    FROM inventory i
    INNER JOIN cake_product c ON c.inv_id = i.inv_id
    WHERE i.manufacture = 'Finish Product' AND i.active = 1
    ORDER BY i.prod_name ASC LIMIT 1000");
$cakeSetupMissing = ($prodRes === false);
if ($prodRes) {
    while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;
}

// Top selling cakes (last 30 days)
$topSelling = array();
$topRes = mysqli_query($mysqli, "SELECT i.inv_id, i.prod_name AS category, i.retail_price, i.packing AS uom, COUNT(*) AS cnt
    FROM cake_order co
    INNER JOIN cake_product cp ON cp.inv_id = co.inv_id
    INNER JOIN inventory i ON i.inv_id = co.inv_id
    WHERE co.inv_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND co.ordercancel = 0
      AND (co.sale_type IS NULL OR co.sale_type = 'cake')
    GROUP BY i.inv_id, i.prod_name, i.retail_price, i.packing
    ORDER BY cnt DESC LIMIT 8");
if ($topRes) {
    while ($t = mysqli_fetch_assoc($topRes)) $topSelling[] = $t;
}

// Name suggestions for box items (from earlier box orders)
$lunchNames = array();
$sweetNames = array();
$nameRes = mysqli_query($mysqli, "SELECT DISTINCT sale_type, category FROM cake_order
    WHERE sale_type IN ('lunch','sweet') AND ordercancel = 0 ORDER BY category LIMIT 400");
if ($nameRes) {
    while ($n = mysqli_fetch_assoc($nameRes)) {
        if ($n['sale_type'] === 'sweet') $sweetNames[] = $n['category'];
        else $lunchNames[] = $n['category'];
    }
}

$flavors = array('Vanilla', 'Chocolate', 'Strawberry', 'Red Velvet', 'Mango', 'Butterscotch', 'Pineapple', 'Coffee', 'Black Forest', 'Tiramisu');
$shapes = array('Round', 'Square', 'Heart', 'Rectangle', 'Number Shape', 'Custom Shape');
$uoms = array('pound', 'kg', 'pcs', 'dozen');
$priorities = array('normal', 'urgent', 'vip');
$occasions = array('Birthday', 'Anniversary', 'Wedding', 'Engagement', 'Baby Shower', 'Graduation', 'Corporate', 'Other');
$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');
$chargeNames = array('Delivery', 'Decoration', 'Packaging', 'Other charge');

$typeLabels = ot_type_labels();
$initialType = (isset($_GET['type']) && is_string($_GET['type']) && isset($typeLabels[$_GET['type']])) ? $_GET['type'] : 'cake';
$branch = getBranchInfo();
$deliveryBranchDefault = $branch['name'];

// Edit: index.php?bill=N opens a saved order on this screen. Allowed only until the kitchen starts preparing it.
$edit = null;
$lockNotice = '';
$editBill = (isset($_GET['bill']) && is_string($_GET['bill']) && ctype_digit($_GET['bill'])) ? intval($_GET['bill']) : 0;
if ($editBill > 0) {
    $editRows = ot_load_bill($mysqli, $editBill);
    if (empty($editRows)) {
        $lockNotice = 'Order #' . $editBill . ' was not found.';
    } else {
        $edit = ot_edit_prefill($editRows, $branch['name']);
        if (!$edit['editable']) {
            $lockNotice = 'Order #' . $editBill . ' is locked. The kitchen has started preparing it (status: ' . $edit['status'] . '), so it can no longer be changed.';
            $edit = null;
        } elseif (json_encode($edit) === false) {
            // Saved text that is not valid UTF-8 cannot be sent to the script: show a notice, not a broken form
            $lockNotice = 'Order #' . $editBill . ' cannot be opened here because some of its saved text cannot be read. Ask the developer to check this order.';
            $edit = null;
        }
    }
    if ($edit === null) {
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order #<?php echo $editBill; ?> - <?php echo htmlspecialchars($COMPANY['name']); ?></title>
<link rel="stylesheet" href="style.css">
<style>.top-logo { max-height: 40px; max-width: 170px; display: block; } .topbar-brand { display: flex; align-items: center; gap: 14px; } .topbar-brand h2 { margin: 0; }</style>
</head>
<body>
<div class="topbar">
    <div class="topbar-brand"><?php echo company_logo_html('top-logo'); ?><h2>Order #<?php echo $editBill; ?></h2></div>
    <div class="topbar-right"><a href="order_list.php">Orders</a><a href="dashboard.php">Dashboard</a></div>
</div>
<div style="max-width:640px;margin:40px auto;padding:0 16px;">
    <div style="background:#fff;border:1px solid #e8e0f0;border-radius:8px;padding:20px;">
        <p style="font-size:15px;line-height:1.5;margin:0 0 16px;"><?php echo htmlspecialchars($lockNotice); ?></p>
        <a class="btn btn-outline" href="order_detail.php?bill=<?php echo $editBill; ?>">View order</a>
        <a class="btn btn-primary" href="order_list.php">Back to orders</a>
    </div>
</div>
</body>
</html>
<?php
        exit;
    }
    $initialType = $edit['type'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New Order - <?php echo htmlspecialchars($COMPANY['name']); ?></title>
<link rel="stylesheet" href="style.css">
<style>
/* New order screen */
.top-logo { max-height: 40px; max-width: 170px; display: block; }
.top-logo-text { color: #fff; font-size: 17px; font-weight: 700; }
.topbar-brand { display: flex; align-items: center; gap: 14px; }
.topbar-brand h2 { margin: 0; }
.pos-main { min-width: 0; }
.transaction-area { flex: 1; overflow-y: auto; min-height: 120px; }
.suggest-box {
    position: absolute; background: #fff; border: 1px solid #6c3483;
    border-radius: 6px; max-height: 300px; overflow-y: auto; z-index: 100;
    min-width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.suggest-item { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; cursor: pointer; }
.suggest-item:hover { background: #f5f0fa; }
.suggest-item .name { font-weight: 600; color: #333; font-size: 13px; }
.suggest-item .meta { font-size: 11px; color: #888; margin-top: 2px; }
.badge-vip { background: #6c3483; color: #fff; padding: 1px 6px; border-radius: 8px; font-size: 9px; margin-left: 4px; }
.badge-loyal { background: #27ae60; color: #fff; padding: 1px 6px; border-radius: 8px; font-size: 9px; margin-left: 4px; }
.tab-row { display: flex; gap: 4px; border-bottom: 2px solid #e8e0f0; margin-bottom: 12px; }
.tab-btn {
    padding: 8px 14px; cursor: pointer; border: none; background: transparent;
    font-size: 12px; font-weight: 600; color: #888; border-bottom: 2px solid transparent; margin-bottom: -2px;
}
.tab-btn.active { color: #6c3483; border-bottom-color: #6c3483; }
.customer-info-card { background: #f5f0fa; border-left: 4px solid #6c3483; padding: 8px 12px; border-radius: 6px; margin: 0 8px 8px; font-size: 12px; display: none; }
.customer-info-card.show { display: block; }
.customer-info-card strong { color: #6c3483; }
.history-item { padding: 8px; background: #fff; border-radius: 6px; margin-bottom: 4px; border: 1px solid #e8e0f0; font-size: 12px; }
.advance-section { background: #fff8e1; padding: 10px; border-radius: 6px; margin-top: 10px; border: 1px dashed #f39c12; display: none; }
.advance-section.show { display: block; }
.kbd-hint { font-size: 10px; color: #aaa; }
.barcode-input-area { background: #fff; padding: 10px; border-radius: 8px; border: 2px dashed #6c3483; margin-bottom: 8px; text-align: center; }
.empty-msg { text-align: center; padding: 36px 20px; color: #888; font-size: 14px; }
.cp-msg.error { background: #fdecec; color: #9b2c2c; border: 1px solid #f1bcbc; padding: 8px 10px; border-radius: 6px; font-size: 12px; margin-bottom: 8px; }
.customer-bar { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 14px; }
.customer-bar .field { display: flex; flex-direction: column; gap: 3px; }
.customer-bar label { font-size: 12px; color: #555; font-weight: 600; }
.customer-bar input, .customer-bar select { padding: 7px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; background: #fff; }
.field-row-label { font-size: 12px; color: #6c3483; font-weight: 600; margin-bottom: 4px; }
.footer-fields { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.footer-fields .field { display: flex; flex-direction: column; gap: 2px; }
.footer-fields label { font-size: 11px; color: #6c3483; font-weight: 600; }
.footer-fields input { padding: 6px; border: 1px solid #ddd; border-radius: 6px; }
.modal h3 { font-size: 16px; }
</style>
</head>
<body data-type="<?php echo $initialType; ?>"<?php echo $edit ? ' data-mode="edit"' : ''; ?>>

<div class="topbar">
    <div class="topbar-brand">
        <?php echo company_logo_html('top-logo'); ?>
        <h2><?php echo $edit ? 'Edit order #' . intval($edit['bill_no']) : 'New order'; ?></h2>
    </div>
    <div class="topbar-right">
        <span style="font-size:13px;background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:12px;">
            <?php echo getRoleName(); ?>
        </span>
        <a href="dashboard.php">Dashboard</a>
        <a href="order_list.php">Orders</a>
        <a href="logout.php" style="background:#e74c3c;padding:6px 12px;border-radius:6px;">Logout</a>
    </div>
</div>

<div class="pos-layout">
    <!-- CAKE PICKER: shown only for Cake orders, and only cakes marked in Cake Products -->
    <div class="pos-sidebar show-cake">
        <div class="barcode-input-area">
            <input type="text" id="barcodeInput" placeholder="Scan barcode"
                   style="width:100%;padding:6px;border:none;background:transparent;text-align:center;font-size:13px;"
                   onkeyup="if(event.key=='Enter') scanBarcode()">
            <div class="kbd-hint">Press Enter after scan</div>
        </div>

        <input type="text" id="searchItems" class="search" placeholder="Search cakes">

        <?php if ($cakeSetupMissing): ?>
        <div class="cp-msg error">The cake list is not set up yet. Run the database update, then mark cakes on the Cake Products page.</div>
        <?php endif; ?>

        <div class="tab-row">
            <button type="button" class="tab-btn active" onclick="switchTab('all', this)">All cakes</button>
            <button type="button" class="tab-btn" onclick="switchTab('top', this)">Top sellers</button>
        </div>

        <div id="tab-all" class="product-list" style="max-height: calc(100vh - 280px);">
            <?php foreach ($products as $p): ?>
            <div class="product-item"
                 data-id="<?php echo intval($p['inv_id']); ?>"
                 data-name="<?php echo htmlspecialchars($p['prod_name'], ENT_QUOTES); ?>"
                 data-price="<?php echo $p['retail_price']; ?>"
                 data-uom="<?php echo htmlspecialchars($p['uom'], ENT_QUOTES); ?>"
                 data-barcode="<?php echo htmlspecialchars($p['barcode'], ENT_QUOTES); ?>"
                 onclick="addItemFromProduct(this)">
                <div class="name"><?php echo htmlspecialchars($p['prod_name']); ?></div>
                <div class="price">Rs. <?php echo number_format($p['retail_price'], 0); ?></div>
                <div class="meta">
                    <?php echo htmlspecialchars($p['uom']); ?>
                    <?php if (!empty($p['barcode'])): ?> &middot; <?php echo htmlspecialchars($p['barcode']); ?><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($products) && !$cakeSetupMissing): ?>
            <div style="text-align:center;padding:20px;color:#999;font-size:12px;">
                No cakes yet. Mark the cake products on the Cake Products page.
            </div>
            <?php endif; ?>
        </div>

        <div id="tab-top" class="product-list" style="display:none;max-height: calc(100vh - 280px);">
            <?php if (empty($topSelling)): ?>
            <p style="text-align:center;color:#999;font-size:12px;padding:20px;">No sales data yet</p>
            <?php endif; ?>
            <?php foreach ($topSelling as $t): ?>
            <div class="product-item"
                 data-id="<?php echo intval($t['inv_id']); ?>"
                 data-name="<?php echo htmlspecialchars($t['category'], ENT_QUOTES); ?>"
                 data-price="<?php echo $t['retail_price']; ?>"
                 data-uom="<?php echo htmlspecialchars($t['uom'], ENT_QUOTES); ?>"
                 data-barcode=""
                 onclick="addItemFromProduct(this)">
                <div class="name"><?php echo htmlspecialchars($t['category']); ?></div>
                <div class="price">Rs. <?php echo number_format($t['retail_price'], 0); ?></div>
                <div class="meta">Sold <?php echo intval($t['cnt']); ?> times in the last 30 days</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="pos-main">
        <!-- ORDER TYPE -->
        <div class="type-tabs">
            <button type="button" class="type-tab" data-type="cake" onclick="selectType('cake')">Cake</button>
            <button type="button" class="type-tab" data-type="lunch" onclick="selectType('lunch')">Lunch box</button>
            <button type="button" class="type-tab" data-type="sweet" onclick="selectType('sweet')">Sweet box</button>
            <button type="button" class="type-tab" data-type="eatable" onclick="selectType('eatable')">Eatable picture</button>
            <button type="button" class="type-tab" data-type="other" onclick="selectType('other')">Other</button>
        </div>

        <!-- CUSTOMER AND DELIVERY -->
        <div style="margin:0 8px 8px;background:#fff;border:1px solid #e8e0f0;border-radius:8px;padding:12px 14px;">
            <div class="customer-bar">
                <div class="field" style="position:relative;">
                    <label for="custCell">Phone (search by number)</label>
                    <input type="text" id="custCell" placeholder="0300-0000000" autocomplete="off"
                           oninput="searchCustomer(this.value)" onblur="setTimeout(hideSuggest, 200)">
                    <div id="suggestBox" class="suggest-box" style="display:none;"></div>
                </div>
                <div class="field">
                    <label for="custName">Customer name</label>
                    <input type="text" id="custName" placeholder="Walk-in" value="Walk-in">
                </div>
                <div class="field">
                    <label for="deliverDate">Delivery date</label>
                    <input type="date" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="field">
                    <label for="deliverTime">Delivery time</label>
                    <input type="time" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                </div>
                <div class="field">
                    <label for="deliveryType">Pickup or delivery</label>
                    <select id="deliveryType" onchange="toggleDeliveryAddress()">
                        <option value="pickup">Pickup</option>
                        <option value="delivery">Home delivery</option>
                    </select>
                </div>
                <div class="field">
                    <label for="deliveryBranch">Delivery branch</label>
                    <input type="text" id="deliveryBranch" maxlength="100" value="<?php echo htmlspecialchars($deliveryBranchDefault); ?>">
                </div>
                <div class="field">
                    <label for="priority">Priority</label>
                    <select id="priority">
                        <?php foreach ($priorities as $pr): ?>
                        <option value="<?php echo $pr; ?>"><?php echo ucfirst($pr); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="deliveryAddrRow" style="display:none;margin-top:10px;">
                <div class="field" style="display:flex;flex-direction:column;gap:3px;">
                    <label style="font-size:12px;font-weight:600;color:#6c3483;" for="deliveryAddress">Delivery address</label>
                    <input type="text" id="deliveryAddress" placeholder="Full delivery address" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                </div>
            </div>
        </div>

        <details class="more-details" style="margin:0 8px 8px;">
            <summary>More details (occasion, order source)</summary>
            <div class="customer-bar" style="margin-top:10px;">
                <div class="field">
                    <label for="occasion">Occasion</label>
                    <select id="occasion">
                        <option value="">None</option>
                        <?php foreach ($occasions as $o): ?>
                        <option value="<?php echo $o; ?>"><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="orderSource">Order source</label>
                    <select id="orderSource">
                        <?php foreach ($sources as $k => $v): ?>
                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </details>

        <div id="customerInfoCard" class="customer-info-card">
            <span id="customerInfoText"></span>
            <button type="button" class="btn btn-sm btn-outline" style="float:right;margin-left:8px;" onclick="showCustomerHistory()"<?php echo $edit ? ' hidden' : ''; ?>>View history</button>
        </div>

        <!-- ITEMS -->
        <div class="transaction-area">
            <div class="transaction-header">
                <div>
                    <h3 id="areaTitle">Cake items</h3>
                    <p>Order #<span id="billNo">New</span> &middot; Customer: <span id="custDisplay">Walk-in</span></p>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline" onclick="showCustomerHistory()" id="historyBtn" style="display:none;">Previous orders</button>
                    <button type="button" class="btn-clear" onclick="clearAll()">Clear all</button>
                </div>
            </div>

            <!-- Cake, eatable picture and other items -->
            <div class="show-cake show-eatable show-other">
                <div id="cartItems" class="cart-items"></div>
                <div id="emptyMsg" class="empty-msg"></div>
                <div class="add-row show-cake"><button type="button" class="btn btn-sm btn-outline" onclick="addEatableItem()">+ Add eatable picture to this cake</button></div>
                <div class="add-row show-eatable"><button type="button" class="btn btn-sm btn-primary" onclick="addEatableItem()">+ Add picture</button></div>
                <div class="add-row show-other"><button type="button" class="btn btn-sm btn-primary" onclick="addOtherItem()">+ Add item</button></div>
            </div>

            <!-- Lunch and sweet boxes: box groups -->
            <div class="show-box">
                <div class="box-totals">
                    <span>Total boxes: <strong id="boxTotalCount">0</strong></span>
                    <span>Total: <strong id="boxTotalAmount">Rs. 0</strong></span>
                </div>
                <p id="weighedNote" class="muted" style="display:none;margin-bottom:8px;"></p>
                <div id="boxGroups"></div>
                <div class="add-row"><button type="button" class="btn btn-sm btn-primary" onclick="addBoxGroup()">+ Add box group</button></div>
                <p class="muted" style="margin-top:8px;">
                    One box group is one kind of box. Example: 6 boxes with set A and 6 boxes with set B are two box groups.
                </p>
            </div>
        </div>

        <!-- EXTRA CHARGES (all order types) -->
        <div class="charges-card">
            <h4>Extra charges (optional): delivery, decoration, packaging, and similar</h4>
            <div id="chargeRows"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <button type="button" class="btn btn-sm btn-outline" onclick="addCharge()">+ Add charge</button>
                <span class="box-summary">Charges: <span id="chargeTotal">Rs. 0</span></span>
            </div>
        </div>

        <!-- TOTALS AND ACTIONS -->
        <div class="footer-bar">
            <div class="footer-inner">
                <div class="totals">
                    <div class="row">Items <span id="subtotal">Rs. 0</span></div>
                    <div class="row">Extra charges <span id="chargesDisplay">Rs. 0</span></div>
                    <div class="row">Discount <span id="discountDisplay">Rs. 0</span></div>
                    <div class="row grand">Total <span id="total">Rs. 0</span></div>
                    <div class="row" style="color:#27ae60;font-size:12px;">Advance <span id="advanceDisplay">Rs. 0</span></div>
                    <div class="row" style="color:#c0392b;font-size:13px;font-weight:bold;">Balance <span id="balanceDisplay">Rs. 0</span></div>
                </div>
                <div class="footer-fields">
                    <div class="field">
                        <label for="discPercent">Discount %</label>
                        <input type="number" id="discPercent" value="0" min="0" max="100" style="width:70px;" onchange="applyDiscPercent()">
                    </div>
                    <div class="field">
                        <label for="flatDisc">Discount Rs.</label>
                        <input type="number" id="flatDisc" value="0" min="0" style="width:90px;" onchange="calcTotals()">
                    </div>
                    <div class="field">
                        <label for="advance">Advance Rs.</label>
                        <input type="number" id="advance" value="0" min="0" style="width:90px;" onchange="toggleAdvanceMethod()"<?php echo $edit ? ' disabled title="The advance is not changed in edit"' : ''; ?>>
                    </div>
                </div>
                <div class="action-btns">
                    <button type="button" class="btn-hold" onclick="saveOrder('hold')"<?php echo $edit ? ' style="display:none;"' : ''; ?>>Hold (F10)</button>
                    <?php if ($edit): ?><a class="btn btn-outline" href="order_list.php">Back to orders</a><?php endif; ?>
                    <button type="button" class="btn-confirm" id="confirmBtn" onclick="saveOrder('confirmed')"><?php echo $edit ? 'Save changes (F9)' : 'Confirm order (F9)'; ?></button>
                </div>
                <?php if ($edit): ?>
                <small class="muted" style="display:block;margin-top:6px;text-align:right;">Advance and payments are not changed here. Every saved change is recorded in the order history.</small>
                <?php endif; ?>
            </div>

            <div id="advanceSection" class="advance-section">
                <label style="font-size:12px;font-weight:600;color:#9a6700;">Advance payment method</label>
                <div style="display:flex;gap:6px;margin-top:6px;">
                    <button type="button" class="btn btn-sm btn-outline" data-method="cash" onclick="setAdvanceMethod('cash', this)">Cash</button>
                    <button type="button" class="btn btn-sm btn-outline" data-method="bank" onclick="setAdvanceMethod('bank', this)">Bank</button>
                    <button type="button" class="btn btn-sm btn-outline" data-method="card" onclick="setAdvanceMethod('card', this)">Card</button>
                    <button type="button" class="btn btn-sm btn-outline" data-method="easypaisa" onclick="setAdvanceMethod('easypaisa', this)">Easypaisa</button>
                </div>
                <small style="display:block;margin-top:4px;color:#888;">The advance is recorded in the GL ledger automatically.</small>
            </div>
        </div>
    </div>
</div>

<datalist id="lunchNames"><?php foreach ($lunchNames as $n): ?><option value="<?php echo htmlspecialchars($n, ENT_QUOTES); ?>"><?php endforeach; ?></datalist>
<datalist id="sweetNames"><?php foreach ($sweetNames as $n): ?><option value="<?php echo htmlspecialchars($n, ENT_QUOTES); ?>"><?php endforeach; ?></datalist>
<datalist id="chargeNames"><?php foreach ($chargeNames as $n): ?><option value="<?php echo htmlspecialchars($n, ENT_QUOTES); ?>"><?php endforeach; ?></datalist>

<!-- IMAGE MODAL -->
<div class="modal-overlay" id="imageModal">
    <div class="modal">
        <h3>Attach photo</h3>
        <input type="file" id="imageFile" accept="image/jpeg,image/jpg,image/png,.jpg,.jpeg,.png" capture="environment" onchange="previewImage(this)" style="width:100%;padding:10px;border:1px dashed #6c3483;border-radius:8px;">
        <small style="display:block;margin-top:6px;color:#888;font-size:11px;">
            Only JPG or PNG. Images are resized to 1024 px and saved as JPG.
        </small>
        <img id="imgPreview" class="img-preview" style="display:none;max-height:300px;margin-top:10px;">
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeImageModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveImage()">Save photo</button>
        </div>
    </div>
</div>

<!-- VOICE MODAL -->
<div class="modal-overlay" id="audioModal">
    <div class="modal">
        <h3>Voice message</h3>
        <p style="margin-bottom:12px;color:#888;font-size:13px;">Record a voice note for the kitchen</p>
        <div style="text-align:center;margin:20px 0;">
            <button type="button" class="btn btn-danger" id="recordBtn" onclick="toggleRecord()" style="width:90px;height:44px;">Record</button>
            <p id="recordStatus" style="margin-top:8px;font-size:12px;color:#888;">Click to record</p>
        </div>
        <audio id="audioPlayback" controls style="width:100%;display:none;margin:10px 0;"></audio>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeAudioModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveAudio()">Save</button>
        </div>
    </div>
</div>

<!-- CUSTOMER HISTORY MODAL -->
<div class="modal-overlay" id="historyModal">
    <div class="modal" style="max-width:650px;">
        <h3>Customer order history</h3>
        <div id="historyContent" style="max-height:400px;overflow-y:auto;"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('historyModal').classList.remove('show')">Close</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var FLAVORS = <?php echo json_encode($flavors); ?>;
var SHAPES = <?php echo json_encode($shapes); ?>;
var UOMS = <?php echo json_encode($uoms); ?>;
var currentType = '<?php echo $initialType; ?>';
var itemCounter = 0;
var boxCounter = 0;
var lastItemsTotal = 0;
var currentImageItem = null;
var currentAudioItem = null;
var mediaRecorder = null;
var audioChunks = [];
var tempImageData = '';
var selectedAdvanceMethod = '';
var currentCustomerCell = '';
var TYPE_TITLES = { cake: 'Cake items', lunch: 'Lunch boxes', sweet: 'Sweet boxes', eatable: 'Eatable pictures', other: 'Other items' };
var EDIT = <?php echo $edit ? json_encode($edit, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;

// ===== HELPERS =====
function esc(s) {
    return String(s === undefined || s === null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
function money(n) { return Math.round(n).toLocaleString('en-US'); }
function isBoxType(t) { return t === 'lunch' || t === 'sweet'; }
function removeEl(el) { if (el && el.parentNode) el.parentNode.removeChild(el); }
function labelWrap(text, html, extraClass) {
    return '<label' + (extraClass ? ' class="' + extraClass + '"' : '') + '>' + esc(text) + html + '</label>';
}
function selectHtml(cls, options, selected) {
    var h = '<select class="' + cls + '"><option value="">Choose</option>';
    for (var i = 0; i < options.length; i++) {
        h += '<option value="' + esc(options[i]) + '"' + (options[i] === selected ? ' selected' : '') + '>' + esc(options[i]) + '</option>';
    }
    return h + '</select>';
}
function qtyControlHtml(id, qty) {
    return '<div class="qty-control">' +
        '<button type="button" onclick="changeQty(\'' + id + '\', -1)">-</button>' +
        '<input type="number" class="qty-input" value="' + qty + '" min="1" step="1" onchange="updateQtyDirect(\'' + id + '\', this.value)">' +
        '<button type="button" onclick="changeQty(\'' + id + '\', 1)">+</button>' +
        '</div>';
}
function cardImageHtml(id, placeholder) {
    return '<div class="item-image" id="imgArea_' + id + '" onclick="openImageModal(\'' + id + '\')">' +
        '<span class="placeholder">' + esc(placeholder) + '</span><span class="upload-icon">+</span></div>';
}
function makeCard(id, kind) {
    var div = document.createElement('div');
    div.className = 'cart-item fade-in';
    div.id = id;
    div.dataset.kind = kind;
    div.dataset.invId = 0;
    div.dataset.name = '';
    div.dataset.price = 0;
    div.dataset.image = '';
    div.dataset.audio = '';
    return div;
}
function setCardImage(id, dataUrl) {
    document.getElementById(id).dataset.image = dataUrl;
    document.getElementById('imgArea_' + id).innerHTML = '<img src="' + dataUrl + '" alt="Photo"><span class="upload-icon">Change</span>';
}

// ===== ORDER TYPE =====
function hasAnyItems() {
    return document.querySelectorAll('#cartItems .cart-item').length > 0 ||
           document.querySelectorAll('#boxGroups .box-group').length > 0;
}
function selectType(type) {
    if (EDIT || type === currentType) return;
    if (hasAnyItems()) {
        if (!confirm('Change order type?\n\nThe items you added will be removed.')) return;
        clearItems();
    }
    setType(type);
}
function setType(type) {
    currentType = type;
    document.body.setAttribute('data-type', type);
    var tabs = document.querySelectorAll('.type-tab');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.toggle('active', tabs[i].getAttribute('data-type') === type);
    }
    document.getElementById('areaTitle').textContent = TYPE_TITLES[type];
    if (isBoxType(type) && document.querySelectorAll('#boxGroups .box-group').length === 0) {
        addBoxGroup();
    }
    updateEmptyMessage();
    calcTotals();
    if (!EDIT && window.history && window.history.replaceState) {
        window.history.replaceState(null, '', 'index.php?type=' + type);
    }
}
function clearItems() {
    document.getElementById('cartItems').innerHTML = '';
    document.getElementById('boxGroups').innerHTML = '';
    updateEmptyMessage();
    calcTotals();
}
function updateEmptyMessage() {
    var box = document.getElementById('emptyMsg');
    var hasCards = document.querySelectorAll('#cartItems .cart-item').length > 0;
    box.style.display = (hasCards || isBoxType(currentType)) ? 'none' : 'block';
    if (currentType === 'cake') {
        box.textContent = 'Click a cake on the left, scan its barcode, or search by customer phone.';
    } else if (currentType === 'eatable') {
        box.textContent = 'Click "Add picture" to add an eatable picture with its photo.';
    } else {
        box.textContent = 'Click "Add item" to add an item.';
    }
}

// ===== TABS (cake picker) =====
function switchTab(tab, btn) {
    var tabsBtn = document.querySelectorAll('.tab-btn');
    for (var i = 0; i < tabsBtn.length; i++) tabsBtn[i].classList.remove('active');
    btn.classList.add('active');
    document.getElementById('tab-all').style.display = tab === 'all' ? '' : 'none';
    document.getElementById('tab-top').style.display = tab === 'top' ? '' : 'none';
}

// ===== BARCODE SCANNER (cakes only) =====
function scanBarcode() {
    var code = document.getElementById('barcodeInput').value.trim();
    if (!code) return;
    var found = false;
    var items = document.querySelectorAll('#tab-all .product-item');
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
        .then(function(r){ return r.json(); })
        .then(function(res) {
            var box = document.getElementById('suggestBox');
            if (!res.customers || res.customers.length === 0) { hideSuggest(); return; }
            var html = '';
            for (var i = 0; i < res.customers.length; i++) {
                var c = res.customers[i];
                html += '<div class="suggest-item" onclick="selectCustomer(\'' + String(c.cell).replace(/'/g, "\\'") + '\', \'' + String(c.name).replace(/'/g, "\\'") + '\')">';
                html += '<div class="name">' + esc(c.name);
                if (c.is_vip) html += '<span class="badge-vip">VIP</span>';
                if (c.is_loyal) html += '<span class="badge-loyal">LOYAL</span>';
                html += '</div>';
                html += '<div class="meta">' + esc(c.cell) + ' &middot; ' + c.orders + ' orders &middot; Spent Rs. ' + Number(c.spent).toLocaleString('en-US') + '</div>';
                if (c.fav_flavors) html += '<div class="meta">Likes: ' + esc(c.fav_flavors) + '</div>';
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
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.customers && res.customers.length > 0) {
            var c = res.customers[0];
            var html = '<strong>' + esc(c.name) + '</strong> &mdash; ';
            html += c.orders + ' previous orders, total spent <strong>Rs. ' + Number(c.spent).toLocaleString('en-US') + '</strong>';
            if (c.fav_flavors) html += ' &middot; Favourite flavours: ' + esc(c.fav_flavors);
            if (c.is_vip) html += ' <span class="badge-vip">VIP</span>';
            if (c.last_order) html += ' &middot; Last order: ' + esc(c.last_order);
            document.getElementById('customerInfoText').innerHTML = html;
            document.getElementById('customerInfoCard').classList.add('show');
            if (!EDIT) document.getElementById('historyBtn').style.display = 'inline-block';
        }
    });
}
function showCustomerHistory() {
    var cell = document.getElementById('custCell').value;
    if (!cell) { showToast('Enter customer phone first', 'error'); return; }
    fetch('customer_lookup.php?action=history&cell=' + encodeURIComponent(cell))
    .then(function(r){ return r.json(); })
    .then(function(res) {
        var html = '';
        if (!res.orders || res.orders.length === 0) {
            html = '<p style="text-align:center;color:#999;padding:20px;">No previous orders</p>';
        } else {
            for (var i = 0; i < res.orders.length; i++) {
                var o = res.orders[i];
                html += '<div class="history-item">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                html += '<strong>Order #' + o.bill_no + '</strong>' + o.status_badge;
                html += '</div>';
                html += '<div style="margin-top:4px;color:#666;">' + esc(o.date) + ' &middot; Rs. ' + o.total + '</div>';
                html += '<div style="margin-top:2px;font-size:11px;color:#888;">' + esc(o.items) + '</div>';
                if (o.flavors) html += '<div style="font-size:11px;color:#888;">Flavours: ' + esc(o.flavors) + '</div>';
                html += '<div style="margin-top:6px;">';
                html += '<button type="button" class="btn btn-sm btn-primary" onclick="duplicateOrder(' + o.bill_no + ')">Copy this order</button>';
                html += '</div>';
                html += '</div>';
            }
        }
        document.getElementById('historyContent').innerHTML = html;
        document.getElementById('historyModal').classList.add('show');
    });
}

// Copy a previous order: its cakes, other items and box groups (eatable pictures and charges are not copied)
function duplicateOrder(billNo) {
    fetch('customer_lookup.php?action=duplicate&bill_no=' + billNo)
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (hasAnyItems() && !confirm('Replace the current items with a copy of order #' + billNo + '?')) return;
        clearItems();
        setType(res.type || 'cake');
        // setType adds one empty box group for box types; the copy replaces it
        document.getElementById('boxGroups').innerHTML = '';
        calcTotals();
        var skipped = 0;
        var items = res.items || [];
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            if (it.kind === 'cake') {
                addCakeItem(it.inv_id, it.name, it.price, it.uom, it);
            } else if (it.kind === 'other') {
                addOtherItem({ name: it.name, price: it.price, qty: it.qty, note: it.note });
            } else {
                skipped++;
            }
        }
        var boxes = res.boxes || [];
        for (var b = 0; b < boxes.length; b++) {
            addBoxGroup(boxes[b]);
        }
        document.getElementById('historyModal').classList.remove('show');
        var msg = 'Copied from order #' + billNo;
        if (skipped) msg += '. Eatable pictures are not copied; add the picture again.';
        showToast(msg, 'success');
    });
}

// ===== DELIVERY TOGGLE =====
function toggleDeliveryAddress() {
    var type = document.getElementById('deliveryType').value;
    document.getElementById('deliveryAddrRow').style.display = type === 'delivery' ? '' : 'none';
}

document.getElementById('custName').addEventListener('input', function() {
    document.getElementById('custDisplay').textContent = this.value || 'Walk-in';
});

// ===== CAKE CARDS =====
function addItemFromProduct(el) {
    if (currentType !== 'cake') setType('cake');
    addCakeItem(el.dataset.id, el.dataset.name, parseFloat(el.dataset.price) || 0, el.dataset.uom || '', null);
}

function addCakeItem(invId, name, basePrice, uom, preset) {
    preset = preset || {};
    itemCounter++;
    var id = 'item_' + itemCounter;
    var div = makeCard(id, 'cake');
    div.dataset.invId = invId || 0;
    div.dataset.name = name;
    div.dataset.price = basePrice;
    div.dataset.image = preset.image_data || '';
    div.dataset.audio = '';
    div.dataset.rowId = preset.id || 0;
    div.innerHTML =
        cardImageHtml(id, 'Photo') +
        '<div class="item-details">' +
            '<div class="item-top">' +
                '<h4>' + esc(name) + '</h4>' +
                '<button type="button" class="btn-play" id="audioBtn_' + id + '" onclick="openAudioModal(\'' + id + '\')">Voice</button>' +
                '<button type="button" class="btn-play" id="audioPlay_' + id + '" style="display:none;" onclick="playAudio(\'' + id + '\')">Play voice</button>' +
                labelWrap('Price (Rs)', '<input type="number" class="price-edit" value="' + (num(basePrice) * (num(preset.tiers) || 1)) + '" min="0" step="1" onchange="updatePrice(\'' + id + '\', this.value)">') +
                '<button type="button" class="btn-remove" onclick="removeItem(\'' + id + '\')">Remove</button>' +
            '</div>' +
            '<div class="item-grid">' +
                labelWrap('Flavour', selectHtml('flavor', FLAVORS, preset.flavor || '')) +
                labelWrap('Shape', selectHtml('shape', SHAPES, preset.shape || '')) +
                labelWrap('Unit', selectHtml('uom', UOMS, preset.uom || uom || '')) +
                labelWrap('Weight', '<input type="number" class="tiers" value="' + (preset.tiers || 1) + '" min="0" step="0.25" oninput="calcTotals()">') +
                labelWrap('Quantity', qtyControlHtml(id, preset.qty || 1)) +
                labelWrap('Material (optional)', '<input type="text" class="material" maxlength="100" value="' + esc(preset.material || '') + '">') +
                labelWrap('Cake message', '<input type="text" class="cake-msg" value="' + esc(preset.cake_message || '') + '" placeholder="Written on the cake">', 'span-2') +
                labelWrap('Kitchen note', '<input type="text" class="note" value="' + esc(preset.note || '') + '" placeholder="Decoration, colour, allergy...">', 'span-2') +
            '</div>' +
        '</div>';
    document.getElementById('cartItems').appendChild(div);
    if (preset.image_data) setCardImage(id, preset.image_data);
    if (preset.has_image && preset.id) showSavedImage(id, preset.id);
    if (preset.has_audio && preset.id) showSavedAudio(id, preset.id);
    updateEmptyMessage();
    calcTotals();
    return id;
}

// ===== EATABLE PICTURE CARDS =====
function addEatableItem(preset) {
    if (currentType !== 'cake' && currentType !== 'eatable') setType('eatable');
    preset = preset || {};
    itemCounter++;
    var id = 'item_' + itemCounter;
    var div = makeCard(id, 'eatable');
    div.dataset.name = 'Eatable picture';
    div.dataset.price = num(preset.price);
    div.dataset.rowId = preset.id || 0;
    div.innerHTML =
        cardImageHtml(id, 'Add picture (required)') +
        '<div class="item-details">' +
            '<div class="item-top">' +
                '<h4>Eatable picture</h4>' +
                labelWrap('Price (Rs)', '<input type="number" class="price-edit" value="' + num(preset.price) + '" min="0" step="1" onchange="updatePrice(\'' + id + '\', this.value)">') +
                '<button type="button" class="btn-remove" onclick="removeItem(\'' + id + '\')">Remove</button>' +
            '</div>' +
            '<div class="item-grid">' +
                labelWrap('Size (optional)', '<input type="text" class="size" value="' + esc(preset.size || '') + '" placeholder="e.g. 8 x 10 inch">') +
                labelWrap('Quantity', qtyControlHtml(id, preset.qty || 1)) +
                labelWrap('Kitchen note', '<input type="text" class="note" value="' + esc(preset.note || '') + '">', 'span-2') +
            '</div>' +
        '</div>';
    document.getElementById('cartItems').appendChild(div);
    if (preset.has_image && preset.id) showSavedImage(id, preset.id);
    updateEmptyMessage();
    calcTotals();
    return id;
}

// ===== OTHER ITEM CARDS =====
function addOtherItem(preset) {
    if (currentType !== 'other') setType('other');
    preset = preset || {};
    itemCounter++;
    var id = 'item_' + itemCounter;
    var div = makeCard(id, 'other');
    div.dataset.name = preset.name || '';
    div.dataset.price = num(preset.price);
    div.dataset.rowId = preset.id || 0;
    div.innerHTML =
        '<div class="item-details">' +
            '<div class="item-top">' +
                '<h4>Other item</h4>' +
                labelWrap('Price (Rs)', '<input type="number" class="price-edit" value="' + num(preset.price) + '" min="0" step="1" onchange="updatePrice(\'' + id + '\', this.value)">') +
                '<button type="button" class="btn-remove" onclick="removeItem(\'' + id + '\')">Remove</button>' +
            '</div>' +
            '<div class="item-grid">' +
                labelWrap('Description', '<input type="text" class="desc" value="' + esc(preset.name || '') + '" placeholder="What is it?">', 'span-2') +
                labelWrap('Quantity', qtyControlHtml(id, preset.qty || 1)) +
                labelWrap('Kitchen note', '<input type="text" class="note" value="' + esc(preset.note || '') + '">', 'span-2') +
            '</div>' +
        '</div>';
    document.getElementById('cartItems').appendChild(div);
    updateEmptyMessage();
    calcTotals();
    return id;
}

function unitMultiplier(item) {
    if (item.dataset.kind === 'cake') {
        var t = item.querySelector('.tiers');
        var w = t ? num(t.value) : 1;
        return w > 0 ? w : 0;
    }
    return 1;
}

function updatePrice(id, newPrice) {
    var item = document.getElementById(id);
    var m = unitMultiplier(item);
    var p = num(newPrice);
    item.dataset.price = m > 0 ? p / m : p;
    calcTotals();
}

function updateQtyDirect(id, newQty) {
    var q = parseInt(newQty, 10) || 1;
    if (q < 1) q = 1;
    document.getElementById(id).querySelector('.qty-input').value = q;
    calcTotals();
}

function removeItem(id) {
    removeEl(document.getElementById(id));
    updateEmptyMessage();
    calcTotals();
}

function changeQty(id, delta) {
    var input = document.getElementById(id).querySelector('.qty-input');
    var q = (parseInt(input.value, 10) || 1) + delta;
    if (q < 1) { removeItem(id); return; }
    input.value = q;
    calcTotals();
}

// ===== BOX GROUPS (lunch and sweet boxes) =====
function addBoxGroup(preset) {
    preset = preset || {};
    boxCounter++;
    var gid = 'box_' + boxCounter;
    var div = document.createElement('div');
    div.className = 'box-group';
    div.id = gid;
    div.innerHTML =
        '<div class="box-group-head">' +
            '<span class="box-title">Box group</span>' +
            labelWrap('Number of boxes', '<input type="number" class="box-count" min="1" step="1" value="' + (preset.boxes || 1) + '" oninput="calcTotals()">') +
            '<button type="button" class="btn-remove" onclick="removeBoxGroup(\'' + gid + '\')">Remove group</button>' +
        '</div>' +
        '<div class="box-items-head"><span>Item</span><span>Qty per box</span><span class="hide-sweet">Price per piece (Rs)</span><span></span></div>' +
        '<div class="box-items"></div>' +
        '<div class="box-group-foot">' +
            '<button type="button" class="btn btn-sm btn-outline" onclick="addBoxItem(\'' + gid + '\')">+ Add item</button>' +
            '<span class="box-summary"></span>' +
        '</div>';
    document.getElementById('boxGroups').appendChild(div);
    var items = (preset.items && preset.items.length) ? preset.items : [{}];
    for (var i = 0; i < items.length; i++) addBoxItem(gid, items[i]);
    calcTotals();
    return gid;
}

function addBoxItem(gid, preset) {
    preset = preset || {};
    var group = document.getElementById(gid);
    var listId = currentType === 'sweet' ? 'sweetNames' : 'lunchNames';
    var row = document.createElement('div');
    row.className = 'box-item-row';
    row.dataset.rowId = preset.id || 0;
    row.innerHTML =
        '<input type="text" class="bi-name" list="' + listId + '" placeholder="Item name" value="' + esc(preset.name || '') + '" oninput="calcTotals()">' +
        '<input type="number" class="bi-qty" min="1" step="1" title="Quantity in each box" value="' + (preset.qty || 1) + '" oninput="calcTotals()">' +
        '<input type="number" class="bi-price hide-sweet" min="0" step="1" title="Price for one piece" placeholder="Rs" value="' + (preset.price ? preset.price : '') + '" oninput="calcTotals()">' +
        '<button type="button" class="btn-remove" onclick="removeBoxItem(this)">Remove</button>';
    group.querySelector('.box-items').appendChild(row);
    calcTotals();
}

function removeBoxItem(btn) {
    removeEl(btn.parentNode);
    calcTotals();
}

function removeBoxGroup(gid) {
    removeEl(document.getElementById(gid));
    if (document.querySelectorAll('#boxGroups .box-group').length === 0) addBoxGroup();
    calcTotals();
}

function boxGroupNumbers(group) {
    var boxes = Math.max(0, Math.floor(num(group.querySelector('.box-count').value)));
    var each = 0;
    var rows = group.querySelectorAll('.box-item-row');
    for (var i = 0; i < rows.length; i++) {
        var name = rows[i].querySelector('.bi-name').value.trim();
        if (!name) continue;
        var perBox = Math.max(1, num(rows[i].querySelector('.bi-qty').value) || 1);
        var price = currentType === 'sweet' ? 0 : num(rows[i].querySelector('.bi-price').value);
        each += price * perBox;
    }
    return { boxes: boxes, each: each, total: each * boxes };
}

// ===== EXTRA CHARGES =====
function addCharge(preset) {
    preset = preset || {};
    var row = document.createElement('div');
    row.className = 'charge-row';
    row.dataset.rowId = preset.id || 0;
    row.innerHTML =
        '<input type="text" class="ch-label" list="chargeNames" placeholder="Label, e.g. Delivery" value="' + esc(preset.label || '') + '" oninput="calcTotals()">' +
        '<input type="number" class="ch-amount" min="0" step="1" placeholder="Rs" value="' + (preset.amount ? preset.amount : '') + '" oninput="calcTotals()">' +
        '<button type="button" class="btn-remove" onclick="removeCharge(this)">Remove</button>';
    document.getElementById('chargeRows').appendChild(row);
    calcTotals();
}
function removeCharge(btn) {
    removeEl(btn.parentNode);
    calcTotals();
}
function collectCharges() {
    var out = [];
    var rows = document.querySelectorAll('#chargeRows .charge-row');
    for (var i = 0; i < rows.length; i++) {
        var amt = num(rows[i].querySelector('.ch-amount').value);
        if (amt > 0) {
            out.push({ label: rows[i].querySelector('.ch-label').value.trim(), amount: amt, id: parseInt(rows[i].dataset.rowId, 10) || 0 });
        }
    }
    return out;
}

// ===== CALCULATIONS =====
function calcTotals() {
    // Sweet boxes are waiting for weighing until a weighed amount is saved (edit shows it)
    var weighed = (EDIT && EDIT.weighed !== null) ? EDIT.weighed : 0;
    var waitingWeight = currentType === 'sweet' && weighed <= 0;
    // Cake, eatable and other cards
    var itemsTotal = 0;
    var cards = document.querySelectorAll('#cartItems .cart-item');
    for (var i = 0; i < cards.length; i++) {
        var item = cards[i];
        var unit = num(item.dataset.price) * unitMultiplier(item);
        var pe = item.querySelector('.price-edit');
        if (pe) pe.value = Math.round(unit * 100) / 100;
        var qtyEl = item.querySelector('.qty-input');
        var qty = qtyEl ? (parseInt(qtyEl.value, 10) || 1) : 1;
        itemsTotal += unit * qty;
    }

    // Box groups: lunch boxes are priced; sweet boxes are priced after weighing (no price entered)
    var boxCount = 0;
    var boxTotal = 0;
    var groups = document.querySelectorAll('#boxGroups .box-group');
    for (var g = 0; g < groups.length; g++) {
        var t = boxGroupNumbers(groups[g]);
        boxCount += t.boxes;
        boxTotal += t.total;
        groups[g].querySelector('.box-title').textContent = 'Box group ' + (g + 1);
        groups[g].querySelector('.box-summary').textContent = waitingWeight
            ? t.boxes + ' boxes. Priced after weighing.'
            : t.boxes + ' boxes. Each box Rs. ' + money(t.each) + '. Group total Rs. ' + money(t.total) + '.';
    }
    itemsTotal += boxTotal + weighed;
    lastItemsTotal = itemsTotal;
    document.getElementById('boxTotalCount').textContent = boxCount;
    document.getElementById('boxTotalAmount').textContent = waitingWeight ? 'After weighing' : 'Rs. ' + money(boxTotal + weighed);

    // Extra charges
    var charges = 0;
    var chargeRows = document.querySelectorAll('#chargeRows .charge-row');
    for (var c = 0; c < chargeRows.length; c++) {
        var amt = num(chargeRows[c].querySelector('.ch-amount').value);
        if (amt > 0) charges += amt;
    }
    document.getElementById('chargeTotal').textContent = 'Rs. ' + money(charges);

    var discount = parseInt(document.getElementById('flatDisc').value, 10) || 0;
    var advance = parseInt(document.getElementById('advance').value, 10) || 0;
    var total = Math.max(0, itemsTotal + charges - discount);
    var balance = total - advance;
    var isSweet = waitingWeight;

    document.getElementById('subtotal').textContent = isSweet ? 'After weighing' : 'Rs. ' + money(itemsTotal);
    document.getElementById('chargesDisplay').textContent = 'Rs. ' + money(charges);
    document.getElementById('discountDisplay').textContent = 'Rs. ' + money(discount);
    if (isSweet) {
        document.getElementById('total').textContent = total > 0 ? 'Rs. ' + money(total) + ' + weighing' : 'After weighing';
        document.getElementById('balanceDisplay').textContent = 'After weighing';
    } else {
        document.getElementById('total').textContent = 'Rs. ' + money(total);
        document.getElementById('balanceDisplay').textContent = 'Rs. ' + money(Math.max(0, balance));
    }
    document.getElementById('advanceDisplay').textContent = 'Rs. ' + money(advance);
}

function applyDiscPercent() {
    var pct = num(document.getElementById('discPercent').value);
    document.getElementById('flatDisc').value = Math.round(lastItemsTotal * pct / 100);
    calcTotals();
}

function toggleAdvanceMethod() {
    var adv = parseInt(document.getElementById('advance').value, 10) || 0;
    document.getElementById('advanceSection').classList.toggle('show', adv > 0);
    calcTotals();
}

function setAdvanceMethod(method, btn) {
    selectedAdvanceMethod = method;
    var buttons = document.querySelectorAll('#advanceSection .btn');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('btn-warning');
        buttons[i].classList.add('btn-outline');
    }
    btn.classList.remove('btn-outline');
    btn.classList.add('btn-warning');
}

function clearAll() {
    if (!confirm('Clear all items, charges and discounts?')) return;
    clearItems();
    document.getElementById('chargeRows').innerHTML = '';
    if (!EDIT) document.getElementById('advance').value = 0;
    document.getElementById('flatDisc').value = 0;
    document.getElementById('discPercent').value = 0;
    document.getElementById('advanceSection').classList.remove('show');
    selectedAdvanceMethod = '';
    calcTotals();
}

// ===== IMAGE / AUDIO =====
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
    var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    var allowedExts = /\.(jpg|jpeg|png)$/i;
    if (allowedTypes.indexOf(file.type) === -1 && !allowedExts.test(file.name)) {
        showToast('Only JPG or PNG images are allowed.', 'error');
        input.value = '';
        return;
    }
    var maxFileSizeMB = 20;
    if (file.size > maxFileSizeMB * 1024 * 1024) {
        showToast('Image too large. Maximum ' + maxFileSizeMB + ' MB.', 'error');
        input.value = '';
        return;
    }
    showToast('Processing image...', 'info');
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = new Image();
        img.onload = function() {
            var canvas = document.createElement('canvas');
            var maxSize = 1024;
            var w = img.width, h = img.height;
            if (w > h) {
                if (w > maxSize) { h = Math.round(h * maxSize / w); w = maxSize; }
            } else {
                if (h > maxSize) { w = Math.round(w * maxSize / h); h = maxSize; }
            }
            canvas.width = w;
            canvas.height = h;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            tempImageData = canvas.toDataURL('image/jpeg', 0.75);
            document.getElementById('imgPreview').src = tempImageData;
            document.getElementById('imgPreview').style.display = 'block';
            showToast('Photo ready (' + w + ' x ' + h + ' px)', 'success');
        };
        img.onerror = function() {
            showToast('Invalid image file', 'error');
            input.value = '';
        };
        img.src = e.target.result;
    };
    reader.onerror = function() { showToast('Could not read the file', 'error'); };
    reader.readAsDataURL(file);
}

function saveImage() {
    if (!tempImageData || !currentImageItem) { closeImageModal(); return; }
    setCardImage(currentImageItem, tempImageData);
    showToast('Photo attached', 'success');
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
        document.getElementById('recordBtn').textContent = 'Record';
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
            document.getElementById('recordBtn').textContent = 'Stop';
            document.getElementById('recordStatus').textContent = 'Recording...';
        }).catch(function() { showToast('Microphone access was denied', 'error'); });
    }
}

function saveAudio() {
    if (currentAudioItem) {
        var item = document.getElementById(currentAudioItem);
        if (item && item.dataset.audio) {
            document.getElementById('audioBtn_' + currentAudioItem).style.display = 'none';
            document.getElementById('audioPlay_' + currentAudioItem).style.display = 'inline-block';
            showToast('Voice message saved', 'success');
        }
    }
    closeAudioModal();
}

function playAudio(itemId) {
    var el = document.getElementById(itemId);
    var src = el.dataset.audio || el.dataset.audioUrl;
    if (src) new Audio(src).play();
}

// ===== COLLECT ORDER DATA =====
function collectItems() {
    var list = [];
    var cards = document.querySelectorAll('#cartItems .cart-item');
    for (var i = 0; i < cards.length; i++) {
        var item = cards[i];
        var kind = item.dataset.kind;
        var entry = {
            kind: kind,
            inv_id: parseInt(item.dataset.invId, 10) || 0,
            name: '',
            price: num(item.querySelector('.price-edit').value),
            qty: parseInt(item.querySelector('.qty-input').value, 10) || 1,
            note: item.querySelector('.note') ? item.querySelector('.note').value.trim() : '',
            image_data: item.dataset.image || '',
            id: parseInt(item.dataset.rowId, 10) || 0,
            keep_image: item.dataset.keepImage === '1' && !item.dataset.image,
            keep_audio: !!item.dataset.audioUrl && !item.dataset.audio
        };
        if (kind === 'cake') {
            entry.name = item.dataset.name;
            entry.flavor = item.querySelector('.flavor').value;
            entry.shape = item.querySelector('.shape').value;
            entry.uom = item.querySelector('.uom').value;
            entry.tiers = num(item.querySelector('.tiers').value) || 1;
            entry.material = item.querySelector('.material').value.trim();
            entry.cake_message = item.querySelector('.cake-msg').value.trim();
            entry.audio_data = item.dataset.audio || '';
        } else if (kind === 'eatable') {
            entry.name = 'Eatable picture';
            entry.size = item.querySelector('.size').value.trim();
        } else {
            entry.name = item.querySelector('.desc').value.trim();
        }
        list.push(entry);
    }
    return list;
}

function collectBoxes() {
    var out = [];
    var groups = document.querySelectorAll('#boxGroups .box-group');
    for (var g = 0; g < groups.length; g++) {
        var items = [];
        var rows = groups[g].querySelectorAll('.box-item-row');
        for (var r = 0; r < rows.length; r++) {
            var name = rows[r].querySelector('.bi-name').value.trim();
            if (!name) continue;
            items.push({
                name: name,
                qty: Math.max(1, parseInt(rows[r].querySelector('.bi-qty').value, 10) || 1),
                price: currentType === 'sweet' ? 0 : num(rows[r].querySelector('.bi-price').value),
                id: parseInt(rows[r].dataset.rowId, 10) || 0
            });
        }
        out.push({
            boxes: Math.floor(num(groups[g].querySelector('.box-count').value)),
            items: items
        });
    }
    return out;
}

// ===== SAVE ORDER =====
function resetAfterSave() {
    clearItems();
    document.getElementById('chargeRows').innerHTML = '';
    document.getElementById('advance').value = 0;
    document.getElementById('flatDisc').value = 0;
    document.getElementById('discPercent').value = 0;
    document.getElementById('advanceSection').classList.remove('show');
    selectedAdvanceMethod = '';
    calcTotals();
}

// Checks the order form. Returns the order data, or null after a message is shown.
function collectForSave() {
    var type = currentType;
    var items = collectItems();
    var boxes = isBoxType(type) ? collectBoxes() : [];
    var charges = collectCharges();

    if (!isBoxType(type) && items.length === 0) { showToast('Add an item first.', 'error'); return null; }
    if (isBoxType(type) && boxes.length === 0) { showToast('Add a box group first.', 'error'); return null; }
    for (var i = 0; i < items.length; i++) {
        if (items[i].kind === 'cake' && (!items[i].name || items[i].tiers <= 0)) {
            showToast('Enter the weight for each cake.', 'error');
            return null;
        }
        if (items[i].kind === 'eatable' && !items[i].image_data && !items[i].keep_image) {
            showToast('Add the picture for each eatable picture.', 'error');
            return null;
        }
        if (items[i].kind === 'other' && !items[i].name) {
            showToast('Describe each other item.', 'error');
            return null;
        }
    }
    for (var b = 0; b < boxes.length; b++) {
        if (boxes[b].boxes < 1) { showToast('Box group ' + (b + 1) + ': enter how many boxes.', 'error'); return null; }
        if (boxes[b].items.length === 0) { showToast('Box group ' + (b + 1) + ': add at least one item.', 'error'); return null; }
    }

    var deliveryType = document.getElementById('deliveryType').value;
    var deliveryAddress = document.getElementById('deliveryAddress').value.trim();
    if (deliveryType === 'delivery' && !deliveryAddress) { showToast('Enter the delivery address.', 'error'); return null; }

    return {
        type: type,
        items: items,
        boxes: boxes,
        charges: charges,
        header: {
            party_detail: document.getElementById('custName').value.trim() || 'Walk-in',
            cell_no: document.getElementById('custCell').value.trim(),
            deliver_date: document.getElementById('deliverDate').value,
            delivery_time: document.getElementById('deliverTime').value,
            priority: document.getElementById('priority').value,
            flat_disc: parseInt(document.getElementById('flatDisc').value, 10) || 0,
            occasion: document.getElementById('occasion').value,
            delivery_type: deliveryType,
            delivery_address: deliveryAddress,
            delivery_branch: document.getElementById('deliveryBranch').value.trim(),
            source: document.getElementById('orderSource').value
        }
    };
}

function setSaving(on) {
    var btn = document.getElementById('confirmBtn');
    btn.disabled = on;
    btn.textContent = on ? 'Saving...' : (EDIT ? 'Save changes (F9)' : 'Confirm order (F9)');
}

function saveOrder(status) {
    if (EDIT) { saveEdit(); return; }
    var c = collectForSave();
    if (!c) return;
    var advance = parseInt(document.getElementById('advance').value, 10) || 0;
    if (advance > 0 && !selectedAdvanceMethod) { showToast('Select the advance payment method.', 'error'); return; }

    var data = c.header;
    data.type = c.type;
    data.items = c.items;
    data.boxes = c.boxes;
    data.charges = c.charges;
    data.status = status;
    data.advance = advance;
    data.advance_method = selectedAdvanceMethod;

    showToast('Saving order...', 'info');
    setSaving(true);
    fetch('save_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        setSaving(false);
        if (res.success) {
            showToast('Order #' + res.bill_no + ' saved', 'success');
            if (status === 'confirmed') {
                if (confirm('Order #' + res.bill_no + ' saved.\n\nPrint the invoice now?')) {
                    window.open('invoice.php?bill=' + res.bill_no, '_blank');
                }
                setTimeout(function() { window.location.href = 'index.php?type=' + c.type; }, 800);
            } else {
                resetAfterSave();
            }
        } else {
            showToast(res.message || 'Could not save the order.', 'error');
        }
    })
    .catch(function() {
        setSaving(false);
        showToast('Network error. The order was not saved.', 'error');
    });
}

// Edit mode: the whole order is sent; the server checks the status again and records the changes
function saveEdit() {
    var c = collectForSave();
    if (!c) return;
    var payload = c.header;
    payload.bill_no = EDIT.bill_no;
    payload.type = c.type;
    payload.items = c.items;
    payload.boxes = c.boxes;
    payload.charges = c.charges;

    showToast('Saving changes...', 'info');
    setSaving(true);
    fetch('save_order_edit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        setSaving(false);
        if (res.success) {
            showToast(res.message, 'success');
            if (res.changed) {
                setTimeout(function() { window.location.href = 'order_detail.php?bill=' + EDIT.bill_no; }, 1000);
            }
        } else {
            showToast(res.message || 'Could not save the changes.', 'error');
        }
    })
    .catch(function() {
        setSaving(false);
        showToast('Network error. The changes were not saved.', 'error');
    });
}

// Edit mode: the saved order is shown on this screen
function selectValue(id, value) {
    var sel = document.getElementById(id);
    value = value || '';
    var found = false;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === value) found = true;
    }
    if (!found && value !== '') sel.appendChild(new Option(value, value));
    sel.value = value;
}

function showSavedImage(cardId, rowId) {
    document.getElementById(cardId).dataset.keepImage = '1';
    document.getElementById('imgArea_' + cardId).innerHTML =
        '<img src="show_image.php?type=thumb&amp;id=' + rowId + '" alt="Photo"><span class="upload-icon">Change</span>';
}

function showSavedAudio(cardId, rowId) {
    document.getElementById(cardId).dataset.audioUrl = 'show_image.php?type=audio&amp;id=' + rowId;
    document.getElementById('audioBtn_' + cardId).style.display = 'none';
    document.getElementById('audioPlay_' + cardId).style.display = 'inline-block';
}

function applyEdit(E) {
    var h = E.header;
    document.getElementById('custCell').value = h.cell_no;
    document.getElementById('custName').value = h.party_detail;
    document.getElementById('custDisplay').textContent = h.party_detail || 'Walk-in';
    document.getElementById('deliverDate').value = h.deliver_date;
    document.getElementById('deliverTime').value = h.delivery_time;
    document.getElementById('deliveryType').value = h.delivery_type;
    toggleDeliveryAddress();
    document.getElementById('deliveryAddress').value = h.delivery_address;
    document.getElementById('deliveryBranch').value = h.delivery_branch;
    selectValue('priority', h.priority);
    selectValue('occasion', h.occasion);
    selectValue('orderSource', h.source);
    document.getElementById('flatDisc').value = h.flat_disc;
    document.getElementById('discPercent').value = 0;
    document.getElementById('advance').value = h.advance;
    document.getElementById('billNo').textContent = E.bill_no;

    // The order type of a saved order does not change
    var tabs = document.querySelectorAll('.type-tab');
    for (var t = 0; t < tabs.length; t++) tabs[t].disabled = true;
    setType(E.type);
    clearItems();

    for (var i = 0; i < E.items.length; i++) {
        var it = E.items[i];
        if (it.kind === 'cake') {
            addCakeItem(it.inv_id, it.name, it.price, it.uom, it);
        } else if (it.kind === 'eatable') {
            addEatableItem(it);
        } else {
            addOtherItem(it);
        }
    }
    for (var b = 0; b < E.boxes.length; b++) addBoxGroup(E.boxes[b]);
    for (var c = 0; c < E.charges.length; c++) addCharge(E.charges[c]);

    if (E.weighed !== null) {
        var note = document.getElementById('weighedNote');
        note.textContent = 'Sweet boxes weighed: Rs. ' + money(E.weighed) + '. If the boxes changed, weigh them again on the Payment page.';
        note.style.display = 'block';
    }
    calcTotals();
}

function showToast(msg, type) {
    var toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast ' + type + ' show';
    setTimeout(function() { toast.classList.remove('show'); }, 3500);
}

// ===== SEARCH CAKES =====
document.getElementById('searchItems').addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    var items = document.querySelectorAll('#tab-all .product-item');
    for (var i = 0; i < items.length; i++) {
        var text = items[i].textContent.toLowerCase();
        items[i].style.display = text.indexOf(q) > -1 ? '' : 'none';
    }
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'F2') { e.preventDefault(); document.getElementById('barcodeInput').focus(); }
    if (e.key === 'F4') { e.preventDefault(); document.getElementById('custCell').focus(); }
    if (e.key === 'F9') { e.preventDefault(); saveOrder('confirmed'); }
    if (e.key === 'F10' && !EDIT) { e.preventDefault(); saveOrder('hold'); }
    if (e.key === 'Escape') { document.getElementById('barcodeInput').value = ''; }
});

// ===== START =====
if (EDIT) { applyEdit(EDIT); } else { setType(currentType); }
setTimeout(function() {
    showToast('Shortcuts: F2 barcode, F4 customer, F9 confirm, F10 hold', 'info');
}, 800);
document.getElementById('barcodeInput').focus();
</script>
</body>
</html>
