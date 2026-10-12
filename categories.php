<?php
require_once 'db.php';
require_once 'includes/cake_config.php';
requireRole(array(1, 3));

ensureCategoryTable($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_name'])) {
        $n = mysqli_real_escape_string($mysqli, trim($_POST['add_name']));
        $t = mysqli_real_escape_string($mysqli, trim($_POST['add_type']));
        $k = mysqli_real_escape_string($mysqli, trim($_POST['add_keywords']));
        if ($n !== '') {
            mysqli_query($mysqli, "INSERT INTO cake_order_category (name, order_type, keywords, dateent) VALUES ('$n','$t','$k',NOW())");
        }
    }
    if (isset($_POST['del_id'])) {
        mysqli_query($mysqli, "DELETE FROM cake_order_category WHERE id = " . intval($_POST['del_id']));
    }
}

$cats = allCategories($mysqli);
$typeLabel = array('cake' => 'Cake', 'lunchbox' => 'Lunch Box', 'sweetsbox' => 'Sweets', 'pos' => 'POS', 'all' => 'All Types');

$pageTitle = 'Categories';
$pageKey   = 'categories';
include 'includes/app_shell.php';
?>

<div class="page-head">
    <div class="ph-ic">▤</div>
    <div>
        <h2>Order Categories</h2>
    </div>
    <div class="spacer"></div>
</div>

<div class="order-cols">
    <div>
        <div class="card">
            <div class="card-title">Add Category</div>
            <form method="post">
                <div class="grid-3">
                    <div class="fld">
                        <label>Name</label>
                        <input class="inp" name="add_name" placeholder="e.g. Chocolate Cake" required>
                    </div>
                    <div class="fld">
                        <label>Order Type</label>
                        <select class="inp" name="add_type">
                            <option value="cake">Cake</option>
                            <option value="lunchbox">Lunch Box</option>
                            <option value="sweetsbox">Sweets</option>
                            <option value="pos">POS</option>
                            <option value="all">All Types</option>
                        </select>
                    </div>
                    <div class="fld">
                        <label>Inventory Keywords (comma separated)</label>
                        <input class="inp" name="add_keywords" placeholder="e.g. chocolate,brownie,truffle">
                    </div>
                </div>
                <button class="btn btn-primary mt12">Save Category</button>
            </form>
            <div class="hint mt8">Keywords limit inventory search on the order screens to items related to this category. Leave empty to match nothing until keywords are added.</div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-title">Saved Categories (<?php echo count($cats); ?>)</div>
            <table class="tbl mt8">
                <thead>
                    <tr><th>Name</th><th>Order Type</th><th>Keywords</th><th style="width:60px;"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cats as $c): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                        <td><?php echo isset($typeLabel[$c['order_type']]) ? $typeLabel[$c['order_type']] : htmlspecialchars($c['order_type']); ?></td>
                        <td><small><?php echo htmlspecialchars($c['keywords']); ?></small></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this category?');">
                                <input type="hidden" name="del_id" value="<?php echo intval($c['id']); ?>">
                                <button class="btn btn-ghost btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cats)): ?>
                    <tr><td colspan="4" class="muted">No categories yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/app_footer.php'; ?>
