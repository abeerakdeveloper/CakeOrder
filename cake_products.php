<?php
require_once 'db.php';
requireRole(array(3)); // Admin only

// The New Order cake picker shows only the products ticked here.
// Ticking is stored in the cake_product table (one row per cake product).

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $picked = array();
    if (isset($_POST['cakes']) && is_array($_POST['cakes'])) {
        foreach ($_POST['cakes'] as $id) {
            $id = intval($id);
            if ($id > 0) $picked[$id] = true;
        }
    }

    mysqli_autocommit($mysqli, false);
    $ok = mysqli_query($mysqli, "DELETE FROM cake_product");
    if ($ok) {
        foreach (array_keys($picked) as $id) {
            if (!mysqli_query($mysqli, "INSERT INTO cake_product (inv_id, added_by, added_at)
                    VALUES ($id, '" . esc($_SESSION['user']) . "', NOW())")) {
                $ok = false;
                break;
            }
        }
    }
    if ($ok) {
        mysqli_commit($mysqli);
        $message = 'Cake products saved. ' . count($picked) . ' products are marked as cakes.';
    } else {
        mysqli_rollback($mysqli);
        $message = 'Could not save. Run the database update (migrations/2026-10-order-types.sql) first.';
        $messageType = 'error';
    }
    mysqli_autocommit($mysqli, true);
}

$products = array();
$res = mysqli_query($mysqli, "SELECT i.inv_id, i.prod_name, i.retail_price, i.packing AS uom,
        (c.inv_id IS NOT NULL) AS is_cake
    FROM inventory i
    LEFT JOIN cake_product c ON c.inv_id = i.inv_id
    WHERE i.manufacture = 'Finish Product' AND i.active = 1
    ORDER BY i.prod_name ASC LIMIT 2000");
$setupMissing = ($res === false);
if ($res) {
    while ($p = mysqli_fetch_assoc($res)) $products[] = $p;
}
$cakeCount = 0;
foreach ($products as $p) {
    if ($p['is_cake']) $cakeCount++;
}

$pageTitle = 'Cake Products';
include 'includes/header.php';
?>
<style>
    .cp-toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
    .cp-toolbar input[type=search] { flex: 1; min-width: 240px; max-width: 420px; padding: 9px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
    .cp-table td, .cp-table th { padding: 8px 10px; font-size: 13px; }
    .cp-table input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; }
    .cp-note { color: #666; font-size: 13px; margin: 0 0 14px; line-height: 1.5; }
    .cp-msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
    .cp-msg.success { background: #eaf6ee; color: #1e6b3a; border: 1px solid #bfe3cb; }
    .cp-msg.error { background: #fdecec; color: #9b2c2c; border: 1px solid #f1bcbc; }
</style>

<div class="data-card">
    <h4>Cake products</h4>
    <p class="cp-note">
        Tick the products that are cakes. Only ticked products appear in the cake picker on New Order.
        Lunch boxes, sweet boxes, eatable pictures and other items are not affected.
        Currently <strong><?php echo $cakeCount; ?></strong> products are marked as cakes.
    </p>

    <?php if ($message !== ''): ?>
    <div class="cp-msg <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($setupMissing): ?>
    <div class="cp-msg error">The cake list is not set up in the database yet. Run migrations/2026-10-order-types.sql first.</div>
    <?php endif; ?>

    <form method="post" id="cakeForm">
        <div class="cp-toolbar">
            <input type="search" id="cpSearch" placeholder="Search product name..." oninput="filterCakeList()">
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllVisible(true)">Tick all shown</button>
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllVisible(false)">Untick all shown</button>
            <button type="submit" class="btn btn-primary">Save cake list</button>
        </div>

        <table class="cp-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:70px;">Cake</th>
                    <th>Product</th>
                    <th>Unit</th>
                    <th style="text-align:right;">Price (Rs)</th>
                </tr>
            </thead>
            <tbody id="cpBody">
                <?php foreach ($products as $p): ?>
                <tr data-name="<?php echo htmlspecialchars(strtolower($p['prod_name']), ENT_QUOTES); ?>">
                    <td><input type="checkbox" name="cakes[]" value="<?php echo intval($p['inv_id']); ?>" <?php echo $p['is_cake'] ? 'checked' : ''; ?>></td>
                    <td><?php echo htmlspecialchars($p['prod_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['uom']); ?></td>
                    <td style="text-align:right;"><?php echo number_format((float) $p['retail_price'], 0); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:24px;">No finished products found in inventory.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
function filterCakeList() {
    var q = document.getElementById('cpSearch').value.toLowerCase();
    var rows = document.querySelectorAll('#cpBody tr[data-name]');
    for (var i = 0; i < rows.length; i++) {
        rows[i].style.display = rows[i].dataset.name.indexOf(q) > -1 ? '' : 'none';
    }
}
function setAllVisible(on) {
    var rows = document.querySelectorAll('#cpBody tr[data-name]');
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].style.display === 'none') continue;
        var box = rows[i].querySelector('input[type=checkbox]');
        if (box) box.checked = on;
    }
}
</script>
</body>
</html>
