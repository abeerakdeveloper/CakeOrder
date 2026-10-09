<?php
require_once 'db.php';
require_once 'includes/product_images.php';
requireRole(array(1, 3));

date_default_timezone_set('Asia/Karachi');

// Same product source as the classic POS
$prodSql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom
            FROM inventory
            WHERE manufacture = 'Finish Product' AND active = 1
            ORDER BY prod_name ASC LIMIT 500";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;

$flavors = array('Vanilla', 'Chocolate', 'Strawberry', 'Red Velvet', 'Mango', 'Butterscotch', 'Pineapple', 'Coffee', 'Black Forest', 'Tiramisu');
$shapes  = array('Round', 'Square', 'Heart', 'Rectangle', 'Number Shape', 'Custom Shape');
$uoms    = array('pound', 'kg', 'pcs', 'dozen');
$sizes   = array('0.5', '1', '1.5', '2', '3', '5');
$occasions = array('Birthday', 'Anniversary', 'Wedding', 'Engagement', 'Baby Shower', 'Graduation', 'Corporate', 'Other');
$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');
$extras  = array('Delivery Charges', 'Decoration', 'Packaging', 'Similar Cake Design');
$refImgs = productReferenceImages();

$pageTitle = 'Cake Order';
$pageKey   = 'cake';
include 'includes/app_shell.php';
?>

<div class="page-head">
    <div class="ph-ic">🎂</div>
    <div>
        <h2>Create Cake Order</h2>
        <p>Select category, flavour and other details to create a new cake order.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-outline" href="order_cake.php">＋ New Order</a>
</div>

<div class="order-cols">
    <!-- ================= LEFT / MAIN ================= -->
    <div>
        <!-- customer + type + date -->
        <div class="card">
            <div class="grid-3">
                <div class="fld" style="position:relative;">
                    <label>👤 Customer</label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <input class="inp" id="custCell" placeholder="Search name or phone..." autocomplete="off">
                        <button class="btn btn-outline btn-sm" style="flex:0 0 auto;" onclick="document.getElementById('custName').focus()">＋ Add New</button>
                    </div>
                    <input class="inp mt8" id="custName" placeholder="Walk-in" value="Walk-in">
                    <div id="suggestBox" class="suggest-box" style="display:none;"></div>
                </div>
                <div class="fld">
                    <label>Order Type</label>
                    <div class="seg">
                        <button type="button" class="on" data-type="pickup" onclick="setOrderType('pickup', this)">🛍 Pickup</button>
                        <button type="button" data-type="delivery" onclick="setOrderType('delivery', this)">🚚 Delivery</button>
                    </div>
                </div>
                <div class="fld">
                    <label>Delivery / Pickup Date &amp; Time</label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <input type="date" class="inp" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                        <input type="time" class="inp" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                    </div>
                </div>
            </div>
            <div class="fld mt12" id="addrRow" style="display:none;">
                <label>📍 Delivery Address</label>
                <input class="inp" id="deliveryAddress" placeholder="Full delivery address...">
            </div>
        </div>

        <!-- 1 category -->
        <div class="card">
            <div class="sec">
                <div class="num">1</div>
                <div class="sec-head" style="flex:1;">
                    <h4>Category</h4><p>Choose a cake category</p>
                    <div class="tiles scroll mt12" id="catTiles">
                        <?php foreach ($products as $i => $p): ?>
                        <div class="tile <?php echo $i === 0 ? 'on' : ''; ?>"
                             data-id="<?php echo intval($p['inv_id']); ?>"
                             data-name="<?php echo htmlspecialchars($p['prod_name'], ENT_QUOTES); ?>"
                             data-price="<?php echo floatval($p['retail_price']); ?>"
                             data-uom="<?php echo htmlspecialchars($p['uom'], ENT_QUOTES); ?>"
                             onclick="pickCategory(this)">
                            <span class="tick">✓</span>
                            <?php $img = productImageUrl($p['inv_id'], $p['prod_name']); ?>
                            <?php if ($img): ?><img class="t-img" src="<?php echo $img; ?>" alt=""><?php else: ?><span class="t-ic"><?php echo productEmoji($p['prod_name']); ?></span><?php endif; ?>
                            <span class="t-lb"><?php echo htmlspecialchars($p['prod_name']); ?></span>
                            <span class="t-sb">Rs. <?php echo number_format($p['retail_price'], 0); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?><div class="empty">No products in inventory.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2 flavour + 3 shape -->
        <div class="card">
            <div class="row" style="align-items:flex-start;">
                <div class="sec" style="flex:1;">
                    <div class="num">2</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Flavour</h4><p>Select flavour from the list</p>
                        <select class="inp mt12" id="flavor" onchange="recalc()">
                            <option value="">— Flavour —</option>
                            <?php foreach ($flavors as $f): ?><option value="<?php echo $f; ?>"><?php echo $f; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="sec" style="flex:1.4;">
                    <div class="num">3</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Shape</h4><p>Pick the cake shape</p>
                        <div class="tiles mt12" id="shapeTiles">
                            <?php foreach ($shapes as $i => $s): ?>
                            <div class="tile <?php echo $i === 0 ? 'on' : ''; ?>" style="min-width:78px;padding:10px 8px;" data-shape="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>" onclick="pickTile('shapeTiles', this); recalc()">
                                <span class="tick">✓</span>
                                <span class="t-ic" style="font-size:18px;"><?php echo array('Round' => '⬤', 'Square' => '▢', 'Heart' => '♡', 'Rectangle' => '▭', 'Number Shape' => '①', 'Custom Shape' => '✎')[$s]; ?></span>
                                <span class="t-lb"><?php echo htmlspecialchars($s); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 size + 5 qty + price + total -->
        <div class="card">
            <div class="row" style="align-items:flex-end;">
                <div class="sec" style="flex:1.2;">
                    <div class="num">4</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Size / UOM</h4><p>Select size and unit</p>
                        <div class="row mt12" style="flex-wrap:nowrap;">
                            <select class="inp" id="size" onchange="recalc()">
                                <?php foreach ($sizes as $s): ?><option value="<?php echo $s; ?>" <?php echo $s == '1' ? 'selected' : ''; ?>><?php echo $s; ?></option><?php endforeach; ?>
                            </select>
                            <select class="inp" id="uom" onchange="recalc()">
                                <?php foreach ($uoms as $u): ?><option value="<?php echo $u; ?>" <?php echo $u == 'kg' ? 'selected' : ''; ?>><?php echo $u; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="sec" style="flex:.9;">
                    <div class="num">5</div>
                    <div class="sec-head">
                        <h4>Quantity</h4><p>Number of cakes</p>
                        <div class="stepper-n mt12">
                            <button type="button" onclick="stepQty(-1)">−</button>
                            <input type="number" id="qty" value="1" min="1" onchange="recalc()">
                            <button type="button" onclick="stepQty(1)">＋</button>
                        </div>
                    </div>
                </div>
                <div class="fld" style="flex:1;">
                    <label>Price (per <?php echo 'unit'; ?>)</label>
                    <input class="inp" type="number" id="price" value="0" min="0" onchange="recalc()">
                    <span class="hint">Editable — tap to change</span>
                </div>
                <div style="flex:1;background:var(--blue-soft);border-radius:10px;padding:12px 14px;">
                    <div class="hint">Total (approx.)</div>
                    <div style="font-size:21px;font-weight:800;color:var(--blue);" id="lineTotal">Rs. 0</div>
                    <div class="hint" id="weightHint"></div>
                </div>
            </div>
        </div>

        <!-- 6 cake image -->
        <div class="card">
            <div class="sec">
                <div class="num">6</div>
                <div class="sec-head" style="flex:1;">
                    <h4>Cake Image</h4><p>Upload or choose a reference image</p>
                    <div class="row mt12" style="align-items:stretch;">
                        <div class="dropzone" style="flex:1;" onclick="pickImage(setCakeImage)">
                            <span class="up">⬆</span>
                            Click to upload or drag &amp; drop<br><span class="hint">JPG, PNG — Max 5MB</span>
                        </div>
                        <?php if (!empty($refImgs)): ?>
                        <div class="thumbs" style="flex:1.4;">
                            <?php foreach ($refImgs as $r): ?>
                            <div class="thb" data-src="<?php echo htmlspecialchars($r, ENT_QUOTES); ?>" onclick="chooseRef(this)"><img src="<?php echo htmlspecialchars($r); ?>" alt=""></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7 extras + 8 remarks -->
        <div class="card">
            <div class="row" style="align-items:flex-start;">
                <div class="sec" style="flex:1;">
                    <div class="num">7</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Extra Charges <span class="muted" style="font-weight:400;">(optional)</span></h4>
                        <p>Add any additional charges</p>
                        <div class="tiles mt12" id="extraTiles">
                            <?php foreach ($extras as $e): ?>
                            <div class="tile" style="min-width:120px;">
                                <span class="t-lb"><?php echo htmlspecialchars($e); ?></span>
                                <input class="inp mt8 extra-amt" style="text-align:center;" type="number" min="0" value="0" data-label="<?php echo htmlspecialchars($e, ENT_QUOTES); ?>" onchange="recalc()">
                            </div>
                            <?php endforeach; ?>
                            <button class="btn btn-outline btn-sm" style="align-self:center;" onclick="addExtra()">＋ Add Charge</button>
                        </div>
                    </div>
                </div>
                <div class="sec" style="flex:1;">
                    <div class="num">8</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Remarks <span class="muted" style="font-weight:400;">(optional)</span></h4>
                        <div class="fld mt12">
                            <input class="inp" id="cakeMsg" placeholder="✍ Cake message (written on the cake)...">
                            <textarea class="inp mt8" id="note" maxlength="300" placeholder="Any special message, notes or custom design details..." oninput="document.getElementById('noteCount').textContent=this.value.length+'/300'"></textarea>
                            <span class="hint" style="text-align:right;" id="noteCount">0/300</span>
                        </div>
                    </div>
                </div>
            </div>
            <button class="collapse-btn" onclick="toggleCollapse('moreOpts', this)">▾ More options</button>
            <div class="collapse-body" id="moreOpts">
                <div class="grid-3 mt8">
                    <div class="fld"><label>⚡ Priority</label>
                        <select class="inp" id="priority"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="vip">VIP</option></select></div>
                    <div class="fld"><label>🎉 Occasion</label>
                        <select class="inp" id="occasion"><option value="">— None —</option><?php foreach ($occasions as $o): ?><option><?php echo $o; ?></option><?php endforeach; ?></select></div>
                    <div class="fld"><label>📍 Source</label>
                        <select class="inp" id="orderSource"><?php foreach ($sources as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= RIGHT / SUMMARY ================= -->
    <div>
        <div class="card">
            <div class="card-title"><span class="ic">🖼️</span> Cake Preview <span class="spacer"></span>
                <button class="btn btn-ghost btn-sm" onclick="pickImage(setCakeImage)">Change Image</button>
            </div>
            <div class="preview-box" id="previewBox">🎂</div>
        </div>

        <div class="card summary">
            <div class="card-title"><span class="ic">🧾</span> Order Summary</div>
            <div class="s-row"><span class="k">Category</span><span class="v" id="sCat">—</span></div>
            <div class="s-row"><span class="k">Flavour</span><span class="v" id="sFlav">—</span></div>
            <div class="s-row"><span class="k">Shape</span><span class="v" id="sShape">—</span></div>
            <div class="s-row"><span class="k">Size / UOM</span><span class="v" id="sSize">—</span></div>
            <div class="s-row"><span class="k">Quantity</span><span class="v" id="sQty">1</span></div>
            <div class="s-row"><span class="k">Price (per unit)</span><span class="v" id="sPrice">Rs. 0</span></div>
            <div class="s-row"><span class="k">Subtotal</span><span class="v" id="sSub">Rs. 0</span></div>
            <div id="sExtras"></div>
            <div class="s-total"><span>Total Amount</span><span id="sTotal">Rs. 0</span></div>
            <button class="btn btn-primary btn-block mt12" onclick="saveCake('confirmed')">🛒 Add to Order</button>
            <button class="btn btn-ghost btn-block mt8" onclick="saveCake('hold')">⏸ Hold Order</button>
            <a class="btn btn-outline btn-block mt8" href="order_list.php">📋 View All Orders</a>
        </div>
    </div>
</div>

<script>
var CAT = <?php echo json_encode(array_values(array_map(function ($p) {
    return array('id' => intval($p['inv_id']), 'name' => $p['prod_name'], 'price' => floatval($p['retail_price']), 'uom' => $p['uom']);
}, $products))); ?>;
var cakeImage = '';

function pickTile(groupId, el) {
    document.querySelectorAll('#' + groupId + ' .tile').forEach(function (t) { t.classList.remove('on'); });
    el.classList.add('on');
}
function pickCategory(el) {
    pickTile('catTiles', el);
    document.getElementById('price').value = Math.round(parseFloat(el.dataset.price) || 0);
    var u = el.dataset.uom;
    if (u) { var us = document.getElementById('uom'); for (var i = 0; i < us.options.length; i++) if (us.options[i].value === u) us.selectedIndex = i; }
    recalc();
}
function stepQty(d) {
    var q = document.getElementById('qty');
    q.value = Math.max(1, (parseInt(q.value, 10) || 1) + d);
    recalc();
}
function activeData(groupId, attr) {
    var el = document.querySelector('#' + groupId + ' .tile.on');
    return el ? el.getAttribute(attr) : '';
}
function setCakeImage(dataUrl) {
    cakeImage = dataUrl;
    document.getElementById('previewBox').innerHTML = '<img src="' + dataUrl + '" alt="">';
    document.querySelectorAll('.thumbs .thb').forEach(function (t) { t.classList.remove('on'); });
}
function chooseRef(th) {
    var url = th.getAttribute('data-src');
    fetch(url).then(function (r) { return r.blob(); }).then(function (b) {
        var rd = new FileReader();
        rd.onload = function () { setCakeImage(rd.result); showToast('Reference image selected', 'success'); };
        rd.readAsDataURL(b);
    });
    document.querySelectorAll('.thumbs .thb').forEach(function (t) { t.classList.remove('on'); });
    th.classList.add('on');
}
function addExtra() {
    var label = prompt('Charge name (e.g. Candles):');
    if (!label) return;
    var d = document.createElement('div');
    d.className = 'tile'; d.style.minWidth = '120px';
    d.innerHTML = '<span class="t-lb">' + escHtml(label) + '</span>' +
        '<input class="inp mt8 extra-amt" style="text-align:center;" type="number" min="0" value="0" data-label="' + escHtml(label).replace(/"/g, '&quot;') + '" onchange="recalc()">';
    document.getElementById('extraTiles').insertBefore(d, document.getElementById('extraTiles').lastElementChild);
    recalc();
}
function extrasList() {
    var out = [];
    document.querySelectorAll('.extra-amt').forEach(function (i) {
        var v = parseInt(i.value, 10) || 0;
        if (v > 0) out.push({ label: i.getAttribute('data-label'), amount: v });
    });
    return out;
}
function recalc() {
    var catEl = document.querySelector('#catTiles .tile.on');
    var price = parseFloat(document.getElementById('price').value) || 0;
    var size = parseFloat(document.getElementById('size').value) || 1;
    var qty = parseInt(document.getElementById('qty').value, 10) || 1;
    var uom = document.getElementById('uom').value;
    var ex = extrasList(), exSum = 0;
    for (var i = 0; i < ex.length; i++) exSum += ex[i].amount;

    var line = price * size;               // one cake of this size
    var sub = line * qty;
    var total = sub + exSum;

    document.getElementById('lineTotal').textContent = money(total);
    document.getElementById('weightHint').textContent = (size * qty) + ' ' + uom;
    document.getElementById('sCat').textContent = catEl ? catEl.dataset.name : '—';
    document.getElementById('sFlav').textContent = document.getElementById('flavor').value || '—';
    document.getElementById('sShape').textContent = activeData('shapeTiles', 'data-shape') || '—';
    document.getElementById('sSize').textContent = size + ' ' + uom;
    document.getElementById('sQty').textContent = qty;
    document.getElementById('sPrice').textContent = money(price);
    document.getElementById('sSub').textContent = money(sub);
    var hx = '';
    for (var j = 0; j < ex.length; j++) hx += '<div class="s-row"><span class="k">+ ' + escHtml(ex[j].label) + '</span><span class="v">' + money(ex[j].amount) + '</span></div>';
    document.getElementById('sExtras').innerHTML = hx;
    document.getElementById('sTotal').textContent = money(total);
}
function saveCake(status) {
    var catEl = document.querySelector('#catTiles .tile.on');
    if (!catEl) { showToast('Select a cake category', 'error'); return; }
    var price = parseFloat(document.getElementById('price').value) || 0;
    var size = parseFloat(document.getElementById('size').value) || 1;
    var qty = parseInt(document.getElementById('qty').value, 10) || 1;
    var ex = extrasList(), exSum = 0, exTxt = [];
    for (var i = 0; i < ex.length; i++) { exSum += ex[i].amount; exTxt.push(ex[i].label + ' Rs.' + ex[i].amount); }

    var note = document.getElementById('note').value;
    if (exTxt.length) note = (note ? note + ' | ' : '') + 'EXTRA CHARGES: ' + exTxt.join(', ');

    // One line for the cake itself (price per cake of the chosen size × qty)...
    var items = [{
        inv_id: parseInt(catEl.dataset.id, 10),
        name: catEl.dataset.name,
        category: catEl.dataset.name,
        price: price * size,                // save_order.php multiplies this by qty
        qty: qty,
        flavor: document.getElementById('flavor').value,
        shape: activeData('shapeTiles', 'data-shape'),
        uom: document.getElementById('uom').value,
        tiers: size,
        cake_message: document.getElementById('cakeMsg').value,
        note: note,
        image_data: cakeImage,
        audio_data: ''
    }];
    // ...plus one line per extra charge (charged once, not per cake)
    for (var e = 0; e < ex.length; e++) {
        items.push({
            inv_id: 0, name: ex[e].label, category: ex[e].label,
            price: ex[e].amount, qty: 1, flavor: '', shape: '', uom: 'pcs',
            tiers: 1, cake_message: '', note: 'Extra charge — cake order',
            image_data: '', audio_data: ''
        });
    }

    submitOrder(items, { status: status, btn: event && event.target }, function (res) { defaultAfterSave(res, status); });
}

bindCustomerLookup('custCell', 'custName');
document.addEventListener('keydown', function (e) { if (e.key === 'F9') { e.preventDefault(); saveCake('confirmed'); } });
recalc();
</script>

<?php include 'includes/app_footer.php'; ?>
