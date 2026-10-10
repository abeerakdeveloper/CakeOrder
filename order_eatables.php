<?php
require_once 'db.php';
require_once 'includes/product_images.php';
requireRole(array(1, 3));

date_default_timezone_set('Asia/Karachi');

$prodSql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom
            FROM inventory
            WHERE manufacture = 'Finish Product' AND active = 1
            ORDER BY prod_name ASC LIMIT 500";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;

$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');

$pageTitle  = 'Eatables';
$pageKey    = 'eatables';
$shellNoNav = true;
include 'includes/app_shell.php';
?>

<div class="page-head">
    <div class="ph-ic">🍽️</div>
    <div>
        <h2>Eatables</h2>
    </div>
    <div class="spacer"></div>
    <input class="inp" style="max-width:260px;" id="gridSearch" placeholder="🔍 Search eatables..." oninput="filterGrid()">
    <a class="btn btn-outline" href="order_eatables.php">＋ New Order</a>
</div>

<div class="order-cols">
    <!-- ============ picture grid ============ -->
    <div>
        <div class="card compact">
            <div class="grid-3" style="margin-bottom:14px;">
                <div class="fld" style="position:relative;">
                    <label>👤 Customer (F4)</label>
                    <div class="row" style="flex-wrap:nowrap; display:none;">
                        <input class="inp" id="custCell" placeholder="Search name or phone..." autocomplete="off">
                        <button class="btn btn-outline btn-sm" style="flex:0 0 auto;" onclick="document.getElementById('custName').focus()">＋ Add New</button>
                    </div>
                    <div class="row mt8" style="flex-wrap:nowrap;">
                        <input class="inp" id="custName" placeholder="Walk-in" value="Walk-in" style="flex:1;">
                        <input class="inp" id="custPhone" placeholder="📞 Contact" inputmode="numeric" style="flex:1;">
                    </div>
                    <div id="suggestBox" class="suggest-box" style="display:none;"></div>
                </div>
                <div class="fld">
                    <label>Order Type</label>
                    <select class="inp" id="orderTypeSel" onchange="toggleAddrRow()">
                        <option value="pickup">🛍 Pickup</option>
                        <option value="delivery">🚚 Delivery</option>
                    </select>
                </div>
                <div class="fld">
                    <label>Pickup / Delivery Date &amp; Time</label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <input type="date" class="inp" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                        <input type="time" class="inp" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                    </div>
                </div>
            </div>
            <div class="fld" id="addrRow" style="display:none;">
                <label>📍 Delivery Address</label>
                <input class="inp" id="deliveryAddress" placeholder="Full delivery address...">
            </div>
        </div>

        <div class="eat-grid mt16" id="eatGrid">
            <?php foreach ($products as $p):
                $img = productImageUrl($p['inv_id'], $p['prod_name']); ?>
            <div class="eat-card" id="card_<?php echo intval($p['inv_id']); ?>"
                 data-id="<?php echo intval($p['inv_id']); ?>"
                 data-name="<?php echo htmlspecialchars(strtolower($p['prod_name']), ENT_QUOTES); ?>"
                 onclick='tapEat(<?php echo htmlspecialchars(json_encode(array('id' => intval($p['inv_id']), 'name' => $p['prod_name'], 'price' => floatval($p['retail_price']), 'uom' => $p['uom'], 'img' => $img, 'emoji' => productEmoji($p['prod_name']))), ENT_QUOTES); ?>)'>
                <span class="cnt" id="cnt_<?php echo intval($p['inv_id']); ?>">0</span>
                <div class="ph"><?php if ($img): ?><img src="<?php echo $img; ?>" alt=""><?php else: ?><?php echo productEmoji($p['prod_name']); ?><?php endif; ?></div>
                <div class="bd">
                    <div class="nm"><?php echo htmlspecialchars($p['prod_name']); ?></div>
                    <div class="pr">Rs. <?php echo number_format($p['retail_price'], 0); ?></div>
                    <div class="um">per <?php echo htmlspecialchars($p['uom']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($products)): ?><div class="empty" style="grid-column:1/-1;"><span class="big">🍽️</span>No products in inventory.</div><?php endif; ?>
        </div>
    </div>

    <!-- ============ cart ============ -->
    <div>
        <div class="card">
            <div class="card-title"><span class="ic">📷</span> Order Photos <span class="spacer"></span>
                <button class="btn btn-ghost btn-sm" onclick="pickImage(imgAdd)">＋ Add</button>
            </div>
            <div class="preview-box" id="imgPrimary" data-empty="📷" style="height:150px;"></div>
            <div class="thumbs mini mt8" id="imgStrip"></div>
        </div>
        <div class="card summary mt16">
            <div class="card-title"><span class="ic">🧾</span> Order Summary
                <span class="spacer"></span>
                <button class="btn btn-ghost btn-sm" onclick="clearCart()">🗑 Clear</button>
            </div>
            <div id="cartRows"><div class="empty"><span class="big">👈</span>Tap pictures to add items</div></div>

            <div class="s-row mt12"><span class="k">Subtotal</span><span class="v" id="sSub">Rs. 0</span></div>
            <div class="row mt8" style="flex-wrap:nowrap;">
                <div class="fld"><label>Discount %</label><input class="inp" type="number" id="discPercent" value="0" min="0" max="100" onchange="applyPercent()"></div>
                <div class="fld"><label>Flat Disc (Rs.)</label><input class="inp" type="number" id="flatDisc" value="0" min="0" onchange="recalc()"></div>
            </div>
            <div class="row mt8" style="flex-wrap:nowrap;">
                <div class="fld"><label>Advance (Rs.)</label><input class="inp" type="number" id="advance" value="0" min="0" onchange="recalc()"></div>
                <div class="fld"><label>Method</label>
                    <select class="inp" id="advMethod">
                        <option value="">—</option><option value="cash">💵 Cash</option><option value="bank">🏦 Bank</option>
                        <option value="card">💳 Card</option><option value="easypaisa">📱 Easypaisa</option>
                    </select></div>
            </div>
            <div class="fld mt8">
                <label>Extra Charges (Rs.) — added once to the bill</label>
                <input class="inp" type="number" id="extraCharge" value="0" min="0" onchange="recalc()" placeholder="Rs. 0">
            </div>
            <div class="s-row mt8"><span class="k">Balance</span><span class="v" id="sBal" style="color:var(--red);">Rs. 0</span></div>
            <div class="s-row" id="sExtraRow" style="display:none;"><span class="k">+ Extra Charges</span><span class="v" id="sExtra">Rs. 0</span></div>
            <div class="s-total"><span>Total Amount</span><span id="sTotal">Rs. 0</span></div>

            <button class="btn btn-primary btn-block mt12" onclick="saveEat('confirmed', this)">🛒 Add to Order</button>
            <button class="btn btn-ghost btn-block mt8" onclick="saveEat('hold', this)">⏸ Hold Order</button>
            <a class="btn btn-outline btn-block mt8" href="order_list.php">📋 View All Orders</a>

            <button class="collapse-btn mt8" onclick="toggleCollapse('moreOpts', this)">▾ More options</button>
            <div class="collapse-body" id="moreOpts">
                <div class="fld mt8"><label>⚡ Priority</label>
                    <select class="inp" id="priority"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="vip">VIP</option></select></div>
                <div class="fld mt8"><label>📍 Source</label>
                    <select class="inp" id="orderSource"><?php foreach ($sources as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
            </div>
        </div>
    </div>
</div>

<script>
var cart = [];   // {id,name,price,uom,img,emoji,qty}

function findCart(id) { for (var i = 0; i < cart.length; i++) if (cart[i].id === id) return i; return -1; }

function tapEat(p) {
    var i = findCart(p.id);
    if (i === -1) cart.push({ id: p.id, name: p.name, price: p.price, uom: p.uom, img: p.img, emoji: p.emoji, qty: 1 });
    else cart[i].qty += 1;
    renderCart();
}
function stepCart(id, d) {
    var i = findCart(id);
    if (i === -1) return;
    cart[i].qty += d;
    if (cart[i].qty < 1) cart.splice(i, 1);
    renderCart();
}
function setQty(id, v) {
    var i = findCart(id);
    if (i === -1) return;
    cart[i].qty = Math.max(1, parseInt(v, 10) || 1);
    renderCart();
}
function removeCart(id) {
    var i = findCart(id);
    if (i > -1) cart.splice(i, 1);
    renderCart();
}
function clearCart() { if (cart.length && confirm('Clear all items?')) { cart = []; renderCart(); } }

function renderCart() {
    var wrap = document.getElementById('cartRows');
    if (!cart.length) { wrap.innerHTML = '<div class="empty"><span class="big">👈</span>Tap pictures to add items</div>'; }
    else {
        var html = '';
        for (var i = 0; i < cart.length; i++) {
            var c = cart[i];
            html += '<div class="cart-row">' +
                '<span class="th">' + (c.img ? '<img src="' + c.img + '" alt="">' : c.emoji) + '</span>' +
                '<span class="mid"><span class="nm">' + escHtml(c.name) + '</span><br><span class="sb">Rs. ' + Math.round(c.price).toLocaleString() + ' / ' + escHtml(c.uom) + '</span></span>' +
                '<span class="stepper-n"><button type="button" onclick="stepCart(' + c.id + ',-1)">−</button>' +
                '<input type="number" value="' + c.qty + '" min="1" onchange="setQty(' + c.id + ',this.value)">' +
                '<button type="button" onclick="stepCart(' + c.id + ',1)">＋</button></span>' +
                '<span class="amt">' + money(c.price * c.qty) + '</span>' +
                '<button class="x" onclick="removeCart(' + c.id + ')">✕</button></div>';
        }
        wrap.innerHTML = html;
    }
    // tile counters
    document.querySelectorAll('.eat-card').forEach(function (el) {
        var id = parseInt(el.dataset.id, 10);
        var i = findCart(id);
        var cnt = el.querySelector('.cnt');
        if (i > -1) { cnt.textContent = cart[i].qty; el.classList.add('has'); }
        else { cnt.textContent = '0'; el.classList.remove('has'); }
    });
    recalc();
}

function recalc() {
    var sub = 0;
    for (var i = 0; i < cart.length; i++) sub += cart[i].price * cart[i].qty;
    var disc = parseInt(document.getElementById('flatDisc').value, 10) || 0;
    var adv = parseInt(document.getElementById('advance').value, 10) || 0;
    var extra = parseInt(document.getElementById('extraCharge').value, 10) || 0;
    var total = Math.max(0, sub - disc) + extra;
    document.getElementById('sSub').textContent = money(sub);
    document.getElementById('sExtraRow').style.display = extra > 0 ? '' : 'none';
    document.getElementById('sExtra').textContent = money(extra);
    document.getElementById('sTotal').textContent = money(total);
    document.getElementById('sBal').textContent = money(Math.max(0, total - adv));
}
function applyPercent() {
    var pct = parseFloat(document.getElementById('discPercent').value) || 0;
    var sub = 0;
    for (var i = 0; i < cart.length; i++) sub += cart[i].price * cart[i].qty;
    document.getElementById('flatDisc').value = Math.round(sub * pct / 100);
    recalc();
}
function filterGrid() {
    var q = document.getElementById('gridSearch').value.toLowerCase();
    document.querySelectorAll('.eat-card').forEach(function (el) {
        el.style.display = el.getAttribute('data-name').indexOf(q) > -1 ? '' : 'none';
    });
}
function saveEat(status, btn) {
    if (!cart.length) { showToast('Tap at least one picture first', 'error'); return; }
    var items = [];
    for (var i = 0; i < cart.length; i++) {
        var c = cart[i];
        items.push({ inv_id: c.id, name: c.name, category: c.name, price: c.price, qty: c.qty, flavor: '', shape: '', uom: c.uom, tiers: 1, cake_message: '', note: '', image_data: '', audio_data: '' });
    }
    var extra = parseInt(document.getElementById('extraCharge').value, 10) || 0;
    if (extra > 0) items.push({ inv_id: 0, name: 'Extra Charges', category: 'Extra Charges', price: extra, qty: 1, flavor: '', shape: '', uom: 'pcs', tiers: 1, cake_message: '', note: 'Extra charge — eatables order', image_data: '', audio_data: '' });
    submitOrder(items, { status: status, btn: btn, advance_method: document.getElementById('advMethod').value, extra_images: extraImages() }, function (res) { defaultAfterSave(res, status); });
}

bindCustomerLookup('custCell', 'custName');
initShortcuts({
    searchId: 'gridSearch',
    onConfirm: function () { saveEat('confirmed', null); },
    onHold: function () { saveEat('hold', null); }
});
renderCart();
</script>

<?php include 'includes/app_footer.php'; ?>
