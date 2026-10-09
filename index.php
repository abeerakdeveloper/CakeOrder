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
$priorityLabels = array('normal' => 'Normal', 'urgent' => 'Urgent', 'vip' => 'VIP');
$occasions = array('Birthday', 'Anniversary', 'Wedding', 'Engagement', 'Baby Shower', 'Graduation', 'Corporate', 'Other');
$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');
$chargeNames = array('Delivery', 'Decoration', 'Packaging', 'Other charge');
$typeTitles = array('cake' => 'Cakes', 'eatable' => 'Eatable pictures', 'other' => 'Other items', 'lunch' => 'Lunch boxes', 'sweet' => 'Sweet boxes');

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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order #<?php echo $editBill; ?> - <?php echo htmlspecialchars($COMPANY['name']); ?></title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="order_screen.css?v=2">
</head>
<body class="ord-page">
<div class="topbar">
    <div class="topbar-brand"><?php echo company_logo_html('top-logo'); ?><h2>Order #<?php echo $editBill; ?></h2></div>
    <div class="topbar-right"><a href="order_list.php">Orders</a><a href="dashboard.php">Dashboard</a></div>
</div>
<div class="ord-notice-wrap">
    <div class="ord-notice">
        <p class="ord-notice-text"><?php echo htmlspecialchars($lockNotice); ?></p>
        <div class="ord-notice-actions">
            <a class="btn btn-secondary" href="order_detail.php?bill=<?php echo $editBill; ?>">View order</a>
            <a class="btn btn-primary" href="order_list.php">Back to orders</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
        exit;
    }
    $initialType = $edit['type'];
}

// Data for order_screen.js. json_encode fails only on text that is not valid UTF-8;
// if it does, the cake lists are left out rather than breaking the whole screen.
$cakeList = array();
foreach ($products as $p) {
    $cakeList[] = array(
        'id' => intval($p['inv_id']),
        'name' => (string) $p['prod_name'],
        'price' => floatval($p['retail_price']),
        'uom' => (string) $p['uom'],
        'barcode' => (string) $p['barcode']
    );
}
$topList = array();
foreach ($topSelling as $t) {
    $topList[] = array(
        'id' => intval($t['inv_id']),
        'name' => (string) $t['category'],
        'price' => floatval($t['retail_price']),
        'uom' => (string) $t['uom'],
        'sold' => intval($t['cnt'])
    );
}
$screenConfig = array(
    'type' => $initialType,
    'edit' => $edit,
    'flavors' => $flavors,
    'shapes' => $shapes,
    'uoms' => $uoms,
    'cakes' => $cakeList,
    'topSellers' => $topList,
    'lunchNames' => $lunchNames,
    'sweetNames' => $sweetNames
);
$screenConfigJson = ord_json($screenConfig);
if ($screenConfigJson === 'null') {
    $screenConfig['cakes'] = array();
    $screenConfig['topSellers'] = array();
    $screenConfigJson = ord_json($screenConfig);
}

// Encodes data for a script block. Returns 'null' if the data cannot be encoded.
function ord_json($value) {
    $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return ($json === false) ? 'null' : $json;
}

$pageTitle = $edit ? 'Edit order #' . intval($edit['bill_no']) : 'New order';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($COMPANY['name']); ?></title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="order_screen.css?v=2">
</head>
<body class="ord-page" data-type="<?php echo htmlspecialchars($initialType); ?>" data-mode="<?php echo $edit ? 'edit' : 'new'; ?>">

<div class="topbar">
    <div class="topbar-brand">
        <?php echo company_logo_html('top-logo'); ?>
        <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
    </div>
    <div class="topbar-right">
        <span class="ord-role"><?php echo htmlspecialchars(getRoleName()); ?></span>
        <a href="dashboard.php">Dashboard</a>
        <a href="order_list.php">Orders</a>
        <a href="logout.php" class="ord-logout">Logout</a>
    </div>
</div>

<div class="ord-wrap">
    <div class="ord-head">
        <p class="ord-sub">Order #<span id="billNo"><?php echo $edit ? intval($edit['bill_no']) : 'New'; ?></span> &middot; Customer: <span id="custDisplay">Walk-in</span></p>
        <div class="ord-head-actions">
            <?php if ($edit): ?>
            <span class="ord-lock-note">Changes are allowed until the kitchen starts preparing this order. Every change is recorded.</span>
            <?php endif; ?>
            <button type="button" class="btn-text danger" id="clearAllBtn">Clear all</button>
        </div>
    </div>

    <div class="ord-grid">
        <div class="ord-main">

            <!-- 1. ORDER TYPE -->
            <section class="panel">
                <h2 class="panel-title"><span class="step-no">1</span> Order type</h2>
                <div class="type-tabs" role="group" aria-label="Order type">
                    <button type="button" class="type-tab" data-type="cake">Cake</button>
                    <button type="button" class="type-tab" data-type="lunch">Lunch box</button>
                    <button type="button" class="type-tab" data-type="sweet">Sweet box</button>
                    <button type="button" class="type-tab" data-type="eatable">Eatable picture</button>
                    <button type="button" class="type-tab" data-type="other">Other</button>
                </div>
            </section>

            <!-- 2. CUSTOMER AND DELIVERY -->
            <section class="panel">
                <h2 class="panel-title"><span class="step-no">2</span> Customer and delivery</h2>
                <div class="form-grid">
                    <div class="field span-2 rel">
                        <label for="custCell">Phone (search by number)</label>
                        <input type="text" id="custCell" placeholder="0300-0000000" autocomplete="off" inputmode="tel">
                        <div id="suggestBox" class="suggest" data-kind="customer" hidden></div>
                    </div>
                    <div class="field span-2">
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
                        <select id="deliveryType">
                            <option value="pickup">Pickup</option>
                            <option value="delivery">Home delivery</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="priority">Priority</label>
                        <select id="priority">
                            <?php foreach ($priorities as $pr): ?>
                            <option value="<?php echo $pr; ?>"><?php echo htmlspecialchars($priorityLabels[$pr]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field span-2">
                        <label for="deliveryBranch">Delivery branch</label>
                        <input type="text" id="deliveryBranch" maxlength="100" value="<?php echo htmlspecialchars($deliveryBranchDefault); ?>">
                    </div>
                    <div class="field span-2" id="deliveryAddrRow" hidden>
                        <label for="deliveryAddress">Delivery address</label>
                        <input type="text" id="deliveryAddress" placeholder="Full delivery address">
                    </div>
                </div>
                <div class="cust-row">
                    <div id="customerInfoCard" class="cust-info" hidden><span id="customerInfoText"></span></div>
                    <button type="button" class="btn btn-secondary btn-sm" id="historyBtn"<?php echo $edit ? ' hidden' : ''; ?>>Previous orders</button>
                </div>
                <details class="more">
                    <summary>More details (occasion, order source)</summary>
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="occasion">Occasion</label>
                            <select id="occasion">
                                <option value="">None</option>
                                <?php foreach ($occasions as $o): ?>
                                <option value="<?php echo $o; ?>"><?php echo $o; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field span-2">
                            <label for="orderSource">Order source</label>
                            <select id="orderSource">
                                <?php foreach ($sources as $k => $v): ?>
                                <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </details>
            </section>

            <!-- 3a. CAKES, EATABLE PICTURES AND OTHER ITEMS -->
            <section class="panel" id="itemsPanel" data-show="cake eatable other">
                <div class="panel-head">
                    <h2 class="panel-title"><span class="step-no">3</span> <span id="itemsTitle"><?php echo htmlspecialchars($typeTitles[$initialType]); ?></span></h2>
                </div>

                <div id="cakePicker" data-show="cake">
                    <div class="field rel">
                        <label for="cakeSearch">Find a cake (type the name, or scan the barcode and press Enter)</label>
                        <input type="text" id="cakeSearch" autocomplete="off" placeholder="Cake name or barcode">
                        <div id="cakeSuggest" class="suggest" data-kind="cake" hidden></div>
                    </div>
                    <div id="topSellers" class="quick-row"></div>
                    <?php if ($cakeSetupMissing): ?>
                    <p class="notice-line error">The cake list is not set up yet. Run the database update, then mark cakes on the Cake Products page.</p>
                    <?php elseif (empty($products)): ?>
                    <p class="notice-line">No cakes yet. Mark the cake products on the Cake Products page.</p>
                    <?php endif; ?>
                </div>

                <div id="cartItems" class="cards"></div>
                <p id="emptyMsg" class="hint center" style="margin-top:10px;" hidden></p>

                <div class="panel-foot">
                    <button type="button" class="btn btn-secondary btn-sm" id="addEatableBtn" data-show="cake eatable">+ Add picture</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="addOtherBtn" data-show="other">+ Add item</button>
                </div>
            </section>

            <!-- 3b. LUNCH AND SWEET BOXES: BOX GROUPS -->
            <section class="panel" id="boxPanel" data-show="lunch sweet">
                <div class="panel-head">
                    <h2 class="panel-title"><span class="step-no">3</span> <span id="boxTitle">Lunch boxes</span></h2>
                    <div class="box-totals">
                        <span>Total boxes <strong id="boxTotalCount">0</strong></span>
                        <span>Total <strong id="boxTotalAmount">Rs. 0</strong></span>
                    </div>
                </div>
                <p class="hint">One box group is one kind of box. Example: 6 boxes with set A and 6 boxes with set B are two box groups. Type an item name to search the item list.</p>
                <p class="hint" data-show="sweet">Sweet boxes are priced after weighing. Weigh them on the Payment page.</p>
                <p id="weighedNote" class="notice-line" hidden></p>
                <div id="boxGroups" style="margin-top:10px;"></div>
                <div class="panel-foot">
                    <button type="button" class="btn btn-secondary btn-sm" id="addGroupBtn">+ Add box group</button>
                </div>
            </section>
        </div>

        <!-- TOTALS, EXTRA CHARGES, DISCOUNT, ADVANCE AND ACTIONS -->
        <aside class="ord-side">
            <section class="panel summary" id="summaryPanel">
                <h2 class="panel-title">Totals</h2>
                <div class="sum-line"><span>Items</span><strong id="subtotal">Rs. 0</strong></div>

                <div class="sum-section">
                    <div class="sum-head">
                        <span>Extra charges (optional)</span>
                        <button type="button" class="btn-text" id="addChargeBtn">+ Add charge</button>
                    </div>
                    <div id="chargeRows"></div>
                    <div class="sum-line sub"><span>Total extra charges</span><strong id="chargesDisplay">Rs. 0</strong></div>
                </div>

                <div class="sum-section">
                    <div class="sum-head"><span>Discount</span></div>
                    <div class="disc-grid">
                        <label class="field"><span class="lbl">Discount %</span>
                            <input type="number" id="discPercent" value="0" min="0" max="100" step="any"></label>
                        <label class="field"><span class="lbl">Discount (Rs.)</span>
                            <input type="number" id="flatDisc" value="0" min="0" step="1"></label>
                    </div>
                    <div class="sum-line sub"><span>Total discount</span><strong id="discountDisplay">Rs. 0</strong></div>
                </div>

                <div class="sum-line grand"><span>Total</span><strong id="total">Rs. 0</strong></div>

                <div class="sum-section">
                    <label class="field"><span class="lbl">Advance (Rs.)</span>
                        <input type="number" id="advance" value="0" min="0" step="1"<?php echo $edit ? ' disabled title="The advance is not changed in edit"' : ''; ?>></label>
                    <div id="advanceSection" class="adv-section" hidden>
                        <span class="lbl">Advance payment method</span>
                        <div class="chips" id="advMethods">
                            <button type="button" class="chip" data-action="method" data-method="cash">Cash</button>
                            <button type="button" class="chip" data-action="method" data-method="bank">Bank</button>
                            <button type="button" class="chip" data-action="method" data-method="card">Card</button>
                            <button type="button" class="chip" data-action="method" data-method="easypaisa">Easypaisa</button>
                        </div>
                        <p class="hint">The advance is recorded in the GL ledger automatically.</p>
                    </div>
                    <div class="sum-line sub"><span>Advance received</span><strong id="advanceDisplay">Rs. 0</strong></div>
                    <div class="sum-line due"><span>Balance due</span><strong id="balanceDisplay">Rs. 0</strong></div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-primary btn-block" id="confirmBtn"><?php echo $edit ? 'Save changes (F9)' : 'Confirm order (F9)'; ?></button>
                    <?php if (!$edit): ?>
                    <button type="button" class="btn btn-secondary btn-block" id="holdBtn">Hold (F10)</button>
                    <?php else: ?>
                    <a class="btn btn-ghost btn-block" href="order_list.php">Back to orders</a>
                    <?php endif; ?>
                </div>
                <?php if ($edit): ?>
                <p class="hint">Advance and payments are not changed here. Every saved change is recorded in the order history.</p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>

<datalist id="chargeNames"><?php foreach ($chargeNames as $n): ?><option value="<?php echo htmlspecialchars($n, ENT_QUOTES); ?>"><?php endforeach; ?></datalist>

<!-- PHOTO DIALOG -->
<div class="overlay" id="imageModal" role="dialog" aria-modal="true" aria-labelledby="imageTitle">
    <div class="modal">
        <h3 id="imageTitle">Add photo</h3>
        <p class="hint">Only JPG or PNG. Images are resized to 1024 px and saved as JPG.</p>
        <input type="file" id="imageFile" class="file-input" accept="image/jpeg,image/jpg,image/png,.jpg,.jpeg,.png" capture="environment" style="margin-top:10px;">
        <img id="imgPreview" class="img-preview" alt="Photo preview" hidden>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="imgCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="imgSave">Save photo</button>
        </div>
    </div>
</div>

<!-- VOICE DIALOG -->
<div class="overlay" id="audioModal" role="dialog" aria-modal="true" aria-labelledby="audioTitle">
    <div class="modal">
        <h3 id="audioTitle">Voice message</h3>
        <p class="hint">Record a voice note for the kitchen.</p>
        <div style="text-align:center;margin:18px 0;">
            <button type="button" class="btn btn-secondary" id="recordBtn" style="min-width:120px;">Record</button>
            <p id="recordStatus" class="hint">Click to record</p>
        </div>
        <audio id="audioPlayback" controls style="width:100%;" hidden></audio>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="audioCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="audioSave">Save</button>
        </div>
    </div>
</div>

<!-- CUSTOMER HISTORY DIALOG -->
<div class="overlay" id="historyModal" role="dialog" aria-modal="true" aria-labelledby="historyTitle">
    <div class="modal" style="width:min(640px,100%);">
        <h3 id="historyTitle">Customer order history</h3>
        <div id="historyContent" style="max-height:400px;overflow-y:auto;margin-top:10px;"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="historyClose">Close</button>
        </div>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>window.ORDER_CONFIG = <?php echo $screenConfigJson; ?>;</script>
<script src="order_screen.js?v=2"></script>
</body>
</html>
