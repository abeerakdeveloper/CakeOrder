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
<link rel="stylesheet" href="order_screen.css?v=4">
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
            <a class="btn btn-outline" href="order_detail.php?bill=<?php echo $editBill; ?>">View order</a>
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
$presetCharges = array('Delivery Charges', 'Decoration', 'Packaging', 'Similar Cake Design');
$screenConfig = array(
    'type' => $initialType,
    'edit' => $edit,
    'flavors' => $flavors,
    'shapes' => $shapes,
    'uoms' => $uoms,
    'cakes' => $cakeList,
    'topSellers' => $topList,
    'lunchNames' => $lunchNames,
    'sweetNames' => $sweetNames,
    'presetCharges' => $presetCharges
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
<?php
// Sidebar links: the same pages and roles as includes/header.php
$navItems = array(
    array('New Order', 'index.php', 'plus', isPOSUser() || isAdmin()),
    array('Dashboard', 'dashboard.php', 'home', true),
    array('Order List', 'order_list.php', 'search', true),
    array('Check Status', 'order_status.php', 'clock', true),
    array('Kitchen', 'kitchen_display.php', 'flame', isKitchen() || isAdmin()),
    array('Pickup Queue', 'pickup_queue.php', 'truck', isPOSUser() || isAdmin()),
    array('Payments', 'admin_payments.php', 'card', isAdmin()),
    array('GL Ledger', 'admin_ledger.php', 'chart', isAdmin()),
    array('Cake Products', 'cake_products.php', 'cake', isAdmin())
);
// Page heading for the order type, or for the order being edited
$pageHeads = array(
    'cake' => array('Create Cake Order', 'Select a category, flavour and other details to create a new cake order.', 'cake'),
    'lunch' => array('Create Lunch Box Order', 'Build lunch box sets and quantities in a few steps.', 'box'),
    'sweet' => array('Create Sweet Box Order', 'Build sweet box sets. Sweet boxes are priced after weighing.', 'box'),
    'eatable' => array('Create Eatable Picture Order', 'Add each picture with its photo, size and quantity.', 'image'),
    'other' => array('Create Other Order', 'Add the items that are not cakes or boxes.', 'list')
);
$headLine = $edit
    ? array('Edit Order #' . intval($edit['bill_no']), 'Changes are allowed until the kitchen starts preparing this order. Every change is recorded.', 'list')
    : $pageHeads[$initialType];
$roleName = getRoleName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($COMPANY['name']); ?></title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="order_screen.css?v=4">
</head>
<body class="ord-page" data-type="<?php echo htmlspecialchars($initialType); ?>" data-mode="<?php echo $edit ? 'edit' : 'new'; ?>">

<svg class="sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-flame" viewBox="0 0 24 24"><path d="M12 3c1 3 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3.5 2-4.5.2 1.6.8 2.6 2 3.2C11 9 11 6 12 3z"/></symbol>
    <symbol id="i-truck" viewBox="0 0 24 24"><path d="M2 7h12v9H2zM14 10h4l3 3v3h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></symbol>
    <symbol id="i-store" viewBox="0 0 24 24"><path d="M3 9l2-5h14l2 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></symbol>
    <symbol id="i-card" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V4M4 20h16"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/></symbol>
    <symbol id="i-cake" viewBox="0 0 24 24"><path d="M4 21h16v-8H4z"/><path d="M4 17c2 0 2-1.5 4-1.5S10 17 12 17s2-1.5 4-1.5 2 1.5 4 1.5"/><path d="M12 11V7M12 4v.01"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></symbol>
    <symbol id="i-image" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></symbol>
    <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></symbol>
    <symbol id="i-mic" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></symbol>
    <symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol>
</svg>

<div class="app">
    <aside class="side">
        <div class="brand"><?php echo company_logo_html('brand-logo'); ?></div>
        <nav class="nav" aria-label="Main menu">
            <?php foreach ($navItems as $item): ?>
            <?php if (!$item[3]) continue; ?>
            <a href="<?php echo $item[1]; ?>"<?php echo $item[1] === 'index.php' ? ' class="active" aria-current="page"' : ''; ?>>
                <svg class="ico" aria-hidden="true"><use href="#i-<?php echo $item[2]; ?>"/></svg>
                <span><?php echo htmlspecialchars($item[0]); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar2">
            <form class="top-search" action="order_list.php" method="get" role="search">
                <svg class="ico" aria-hidden="true"><use href="#i-search"/></svg>
                <input type="search" name="search" placeholder="Search orders, customers..." aria-label="Search orders and customers">
            </form>
            <div class="top-right">
                <span class="role-pill"><span class="avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($roleName, 0, 1))); ?></span><?php echo htmlspecialchars($roleName); ?></span>
                <a class="btn btn-logout" href="logout.php"><svg class="ico" aria-hidden="true"><use href="#i-logout"/></svg>Logout</a>
            </div>
        </header>

        <main class="page">
            <div class="page-head">
                <div>
                    <div class="page-title">
                        <span class="title-ico"><svg class="ico" aria-hidden="true"><use href="#i-<?php echo $headLine[2]; ?>"/></svg></span>
                        <div>
                            <h1 id="pageTitle"><?php echo htmlspecialchars($headLine[0]); ?></h1>
                            <p id="pageSub"><?php echo htmlspecialchars($headLine[1]); ?></p>
                        </div>
                    </div>
                    <?php if (!$edit): ?>
                    <p class="shortcuts">Shortcuts: F2 cake search, F4 customer, F9 confirm, F10 hold</p>
                    <?php endif; ?>
                </div>
                <div class="page-actions">
                    <button type="button" class="btn-text danger" id="clearAllBtn">Clear all</button>
                    <?php if ($edit): ?>
                    <a class="btn btn-outline" href="order_list.php">Back to orders</a>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline" id="newOrderBtn">+ New Order</button>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="type-bar" aria-label="Order type">
                <button type="button" class="type-tab" data-action="type" data-type="cake">Cake</button>
                <button type="button" class="type-tab" data-action="type" data-type="lunch">Lunch box</button>
                <button type="button" class="type-tab" data-action="type" data-type="sweet">Sweet box</button>
                <button type="button" class="type-tab" data-action="type" data-type="eatable">Eatable picture</button>
                <button type="button" class="type-tab" data-action="type" data-type="other">Other</button>
            </nav>

            <section class="info-row">
                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Customer</h2>
                        <button type="button" class="btn btn-outline btn-sm" id="addNewCustomerBtn">+ Add New</button>
                    </div>
                    <div class="search-field rel">
                        <svg class="ico" aria-hidden="true"><use href="#i-search"/></svg>
                        <input type="text" id="custCell" placeholder="Search customer name or phone..." autocomplete="off">
                        <div id="suggestBox" class="suggest" data-kind="customer" hidden></div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label class="lbl" for="custName">Customer name</label>
                        <input type="text" id="custName" placeholder="Walk-in" value="Walk-in">
                    </div>
                    <div class="cust-row">
                        <div id="customerInfoCard" class="cust-info" hidden><span id="customerInfoText"></span></div>
                        <button type="button" class="btn-text" id="historyBtn"<?php echo $edit ? ' hidden' : ''; ?>>Previous orders</button>
                    </div>
                    <details class="more">
                        <summary>More details (occasion, order source, branch)</summary>
                        <div class="grid2">
                            <div class="field">
                                <label class="lbl" for="occasion">Occasion</label>
                                <select id="occasion">
                                    <option value="">None</option>
                                    <?php foreach ($occasions as $o): ?>
                                    <option value="<?php echo $o; ?>"><?php echo $o; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="lbl" for="orderSource">Order source</label>
                                <select id="orderSource">
                                    <?php foreach ($sources as $k => $v): ?>
                                    <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field span-2">
                                <label class="lbl" for="deliveryBranch">Delivery branch</label>
                                <input type="text" id="deliveryBranch" maxlength="100" value="<?php echo htmlspecialchars($deliveryBranchDefault); ?>">
                            </div>
                        </div>
                    </details>
                </div>

                <div class="card">
                    <h2 class="card-title">Pickup or delivery</h2>
                    <div class="seg" role="group" aria-label="Pickup or delivery">
                        <button type="button" data-action="delivery" data-value="pickup" class="is-active" aria-pressed="true"><svg class="ico" aria-hidden="true"><use href="#i-store"/></svg>Pickup</button>
                        <button type="button" data-action="delivery" data-value="delivery" aria-pressed="false"><svg class="ico" aria-hidden="true"><use href="#i-truck"/></svg>Delivery</button>
                    </div>
                    <input type="hidden" id="deliveryType" value="pickup">
                    <div id="deliveryAddrRow" class="addr-row" hidden>
                        <label class="lbl" for="deliveryAddress">Delivery address</label>
                        <input type="text" id="deliveryAddress" placeholder="Full delivery address">
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Date and time</h2>
                    <div class="dt-grid">
                        <div class="field">
                            <label class="lbl" for="deliverDate">Date</label>
                            <input type="date" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="field">
                            <label class="lbl" for="deliverTime">Time</label>
                            <input type="time" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label class="lbl" for="priority">Priority</label>
                        <select id="priority">
                            <?php foreach ($priorities as $pr): ?>
                            <option value="<?php echo $pr; ?>"><?php echo htmlspecialchars($priorityLabels[$pr]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <div class="work" id="work">
                <!-- Lunch and sweet boxes: item catalogue -->
                <aside class="card catalog" id="catalogPanel" data-show="lunch sweet" hidden>
                    <h2 class="card-title">Items</h2>
                    <div class="search-field">
                        <svg class="ico" aria-hidden="true"><use href="#i-search"/></svg>
                        <input type="search" id="catalogSearch" placeholder="Search items..." autocomplete="off" aria-label="Search items">
                    </div>
                    <p class="hint">e.g. chicken, pizza, juice...</p>
                    <div class="cat-head" id="catalogHead">Default items</div>
                    <div id="catalogList" class="catalog-list"></div>
                    <p class="hint">Click Add to put an item in the set you are working on.</p>
                </aside>

                <div class="work-main">
                    <!-- Cakes: category tiles -->
                    <section class="card" id="itemsPanel" data-show="cake">
                        <div class="card-head">
                            <h2 class="card-title">Category</h2>
                        </div>
                        <div id="cakePicker">
                            <div class="search-field rel">
                                <svg class="ico" aria-hidden="true"><use href="#i-search"/></svg>
                                <input type="text" id="cakeSearch" autocomplete="off" placeholder="Search a cake, or scan its barcode and press Enter" aria-label="Search cake">
                                <div id="cakeSuggest" class="suggest" data-kind="cake" hidden></div>
                            </div>
                            <p class="hint">Only cake products are listed. Click a tile to add that cake to the order.</p>
                            <div id="tiles" class="tiles" style="margin-top:12px;"></div>
                            <?php if ($cakeSetupMissing): ?>
                            <p class="notice-line error">The cake list is not set up yet. Run the database update, then mark cakes on the Cake Products page.</p>
                            <?php elseif (empty($products)): ?>
                            <p class="notice-line">No cakes yet. Mark the cake products on the Cake Products page.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Cakes, eatable pictures and other items -->
                    <section class="card" id="cardsPanel" data-show="cake eatable other">
                        <div class="card-head">
                            <h2 class="card-title" id="cardsTitle">Cake details</h2>
                            <div class="panel-actions">
                                <button type="button" class="btn btn-outline btn-sm" id="addEatableBtn" data-show="cake eatable">+ Add picture</button>
                                <button type="button" class="btn btn-outline btn-sm" id="addOtherBtn" data-show="other">+ Add item</button>
                            </div>
                        </div>
                        <div id="cartItems" class="cards"></div>
                        <p id="emptyMsg" class="empty" hidden></p>
                    </section>

                    <!-- Lunch and sweet boxes: sets -->
                    <section class="card" id="boxPanel" data-show="lunch sweet">
                        <div class="card-head">
                            <h2 class="card-title" id="boxTitle">Lunch Box Sets</h2>
                            <button type="button" class="btn btn-outline btn-sm" id="addGroupBtn"><svg class="ico" aria-hidden="true"><use href="#i-plus"/></svg>Add another set</button>
                        </div>
                        <p class="hint">One set is one kind of box. Example: 6 boxes with set A and 6 boxes with set B are two sets.</p>
                        <p class="hint" data-show="sweet">Sweet boxes are priced after weighing. Weigh them on the Payment page.</p>
                        <p id="weighedNote" class="notice-line" hidden></p>
                        <div id="boxGroups" style="margin-top:12px;"></div>
                    </section>
                </div>

                <aside class="work-side">
                    <!-- Cakes and eatable pictures: photo after upload only -->
                    <section class="card" id="previewPanel" data-show="cake eatable">
                        <div class="card-head">
                            <h2 class="card-title">Cake Preview</h2>
                            <button type="button" class="btn-text" id="previewChangeBtn" hidden>Change image</button>
                        </div>
                        <div id="previewBox" class="preview-box"><span class="preview-empty">Upload a reference photo to see it here.</span></div>
                        <p class="preview-caption" id="previewCaption"></p>
                    </section>

                    <section class="card" id="summaryPanel">
                        <h2 class="card-title">Order Summary</h2>
                        <div class="sum-tiles" data-show="lunch sweet">
                            <div class="sum-tile"><strong id="setCount">0</strong><span id="setCountLabel">Lunch Box Sets</span></div>
                            <div class="sum-tile"><strong id="boxTotalCount">0</strong><span id="boxTotalLabel">Total Lunch Boxes</span></div>
                        </div>
                        <div data-show="lunch sweet"><p class="sub-head">Set breakdown</p></div>
                        <div id="setBreakdown" class="breakdown" hidden></div>
                        <div data-show="cake eatable other"><p class="sub-head">Items</p></div>
                        <div id="orderLines" class="breakdown" hidden></div>

                        <div class="sum-line"><span id="itemsLabel">Items total</span><strong id="subtotal">Rs. 0</strong></div>

                        <div class="sum-block">
                            <div class="sum-head"><span>Extra charges</span><button type="button" class="btn-text" id="addChargeBtn">+ Add charge</button></div>
                            <div id="chargePresets" class="chips charge-presets"></div>
                            <div id="chargeRows"></div>
                            <div class="sum-line sub"><span>Total extra charges</span><strong id="chargesDisplay">Rs. 0</strong></div>
                        </div>

                        <div class="sum-block">
                            <div class="sum-head"><span>Discount</span></div>
                            <div class="disc-grid">
                                <label class="field"><span class="lbl">Discount %</span>
                                    <input type="number" id="discPercent" value="0" min="0" max="100" step="any"></label>
                                <label class="field"><span class="lbl">Discount (Rs.)</span>
                                    <input type="number" id="flatDisc" value="0" min="0" step="1"></label>
                            </div>
                            <div class="sum-line sub"><span>Total discount</span><strong id="discountDisplay">Rs. 0</strong></div>
                        </div>

                        <div class="total-box"><span>Total amount</span><strong id="total">Rs. 0</strong></div>

                        <div class="sum-block">
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
                            <button type="button" class="btn btn-outline btn-block" id="holdBtn">Hold (F10)</button>
                            <?php endif; ?>
                            <a class="btn btn-ghost btn-block" href="order_list.php">View All Orders</a>
                        </div>
                        <?php if ($edit): ?>
                        <p class="hint">Advance and payments are not changed here. Every saved change is recorded in the order history.</p>
                        <?php endif; ?>
                    </section>
                </aside>
            </div>
        </main>
    </div>
</div>

<datalist id="chargeNames"><?php foreach (array_unique(array_merge($presetCharges, $chargeNames)) as $n): ?><option value="<?php echo htmlspecialchars($n, ENT_QUOTES); ?>"><?php endforeach; ?></datalist>

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
            <button type="button" class="btn btn-outline" id="recordBtn" style="min-width:120px;">Record</button>
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
<script src="order_screen.js?v=4"></script>
</body>
</html>
