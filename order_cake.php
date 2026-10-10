<?php
require_once 'db.php';
require_once 'includes/product_images.php';
require_once 'includes/cake_config.php';
requireRole(array(1, 3));

date_default_timezone_set('Asia/Karachi');
$CFG = cakeConfig();

// Inventory products (same source as classic POS) — searchable cake items
$prodSql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom
            FROM inventory
            WHERE manufacture = 'Finish Product' AND active = 1
            ORDER BY prod_name ASC LIMIT 500";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;

$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');
$refImgs = productReferenceImages();

$pageTitle  = 'Cake Order';
$pageKey    = 'cake';
$shellNoNav = true;   // order pages: no sidebar, more room
include 'includes/app_shell.php';
?>

<div class="page-head">
    <div class="ph-ic">🎂</div>
    <div>
        <h2>Create Cake Order</h2>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-outline" href="order_cake.php">＋ New Order</a>
</div>

<div class="order-cols">
    <!-- ================= LEFT / MAIN ================= -->
    <div>
        <!-- customer + type + date -->
        <div class="card compact">
            <div class="grid-3">
                <div class="fld" style="position:relative;">
                    <label>👤 Customer (F4)</label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <input class="inp" id="custCell" placeholder="Search name or phone..." autocomplete="off">
                        <button class="btn btn-outline btn-sm" style="flex:0 0 auto;" onclick="document.getElementById('custName').focus()" title="Add new customer">＋ Add New</button>
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

        <!-- 1 category + search -->
        <div class="card">
            <div class="sec">
                <div class="num">1</div>
                <div class="sec-head" style="flex:1;">
                    <h4>Category (F2)</h4>
                    <input class="inp mt12" id="cakeSearch" placeholder="🔍 Search cake item... e.g. cream, brownie, chocolate" oninput="filterCategories()" autocomplete="off">
                    <div class="tiles scroll mt12" id="catTiles">
                        <?php foreach ($CFG['categories'] as $i => $c): ?>
                        <div class="tile <?php echo $i === 0 ? 'on' : ''; ?>" data-kind="cat" data-idx="<?php echo $i; ?>"
                             data-name="<?php echo htmlspecialchars(strtolower($c['name']), ENT_QUOTES); ?>"
                             onclick="pickCategory(this)">
                            <span class="tick">✓</span>
                            <?php $img = productImageUrl(0, $c['name']); ?>
                            <?php if ($img): ?><img class="t-img" src="<?php echo $img; ?>" alt=""><?php else: ?><span class="t-ic"><?php echo $c['icon']; ?></span><?php endif; ?>
                            <span class="t-lb"><?php echo htmlspecialchars($c['name']); ?></span>
                            <span class="t-sb">Rs. <?php echo number_format($c['price'], 0); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach ($products as $p): ?>
                        <div class="tile" data-kind="inv" style="display:none;"
                             data-id="<?php echo intval($p['inv_id']); ?>"
                             data-name="<?php echo htmlspecialchars(strtolower($p['prod_name']), ENT_QUOTES); ?>"
                             data-price="<?php echo floatval($p['retail_price']); ?>"
                             data-uom="<?php echo htmlspecialchars($p['uom'], ENT_QUOTES); ?>"
                             onclick="pickInventory(this)">
                            <span class="tick">✓</span>
                            <?php $img = productImageUrl($p['inv_id'], $p['prod_name']); ?>
                            <?php if ($img): ?><img class="t-img" src="<?php echo $img; ?>" alt=""><?php else: ?><span class="t-ic"><?php echo productEmoji($p['prod_name']); ?></span><?php endif; ?>
                            <span class="t-lb"><?php echo htmlspecialchars($p['prod_name']); ?></span>
                            <span class="t-sb">Rs. <?php echo number_format($p['retail_price'], 0); ?></span>
                        </div>
                        <?php endforeach; ?>
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
                        <h4>Flavour</h4>
                        <select class="inp mt12" id="flavor" onchange="onFlavor()"></select>
                    </div>
                </div>
                <div class="sec" style="flex:1;">
                    <div class="num">3</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Ladi</h4>
                        <select class="inp mt12" id="ladi" onchange="recalc()"></select>
                    </div>
                </div>
                <div class="sec" style="flex:1.3;">
                    <div class="num">4</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Shape</h4>
                        <div class="tiles mt12" id="shapeTiles">
                            <?php
                            $shapeIcons = array('Round' => '⬤', 'Square' => '▢', 'Heart' => '♡', 'Rectangle' => '▭', 'Number Shape' => '①', 'Custom Shape' => '✎');
                            foreach ($CFG['shapes'] as $i => $s): ?>
                            <div class="tile <?php echo $i === 0 ? 'on' : ''; ?>" style="min-width:76px;padding:10px 8px;" data-shape="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>" onclick="pickTile('shapeTiles', this); recalc()">
                                <span class="tick">✓</span>
                                <span class="t-ic" style="font-size:18px;"><?php echo isset($shapeIcons[$s]) ? $shapeIcons[$s] : '▢'; ?></span>
                                <span class="t-lb"><?php echo htmlspecialchars($s); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5 size + 6 qty + price + total -->
        <div class="card">
            <div class="row" style="align-items:flex-end;">
                <div class="sec" style="flex:1.3;">
                    <div class="num">5</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Size / UOM</h4>
                        <select class="inp mt12" id="size" onchange="recalc()"></select>
                    </div>
                </div>
                <div class="sec" style="flex:.9;">
                    <div class="num">6</div>
                    <div class="sec-head">
                        <h4>Quantity</h4>
                        <div class="stepper-n mt12">
                            <button type="button" onclick="stepQty(-1)">−</button>
                            <input type="number" id="qty" value="1" min="1" onchange="recalc()">
                            <button type="button" onclick="stepQty(1)">＋</button>
                        </div>
                    </div>
                </div>
                <div class="fld" style="flex:1;">
                    <label>Price (per unit, Rs.)</label>
                    <input class="inp" type="number" id="price" value="0" min="0" onchange="recalc()">
                </div>
                <div style="flex:1;background:var(--blue-soft);border-radius:10px;padding:12px 14px;">
                    <div class="hint">Total</div>
                    <div style="font-size:21px;font-weight:800;color:var(--blue);" id="lineTotal">Rs. 0</div>
                </div>
            </div>
        </div>

        <!-- 7 cake image -->
        <div class="card">
            <div class="sec">
                <div class="num">7</div>
                <div class="sec-head" style="flex:1;">
                    <h4>Cake Image</h4>
                    <div class="row mt12" style="align-items:stretch;">
                        <div class="dropzone" style="flex:1;" onclick="pickImage(imgAdd)">
                            <span class="up">⬆</span>
                            ＋ Add Photos
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 8 extra charges + 9 remarks -->
        <div class="card">
            <div class="row" style="align-items:flex-start;">
                <div class="sec" style="flex:.8;">
                    <div class="num">8</div>
                    <div class="sec-head" style="flex:1;">
                        <h4>Extra Charges <span class="muted" style="font-weight:400;">(optional)</span></h4>
                        <div class="fld mt12">
                            <input class="inp" type="number" id="extraCharge" value="0" min="0" onchange="recalc()" placeholder="Rs. 0">
                        </div>
                    </div>
                </div>
                <div class="sec" style="flex:1.2;">
                    <div class="num">9</div>
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
                        <select class="inp" id="occasion"><option value="">— None —</option><option>Birthday</option><option>Anniversary</option><option>Wedding</option><option>Engagement</option><option>Baby Shower</option><option>Graduation</option><option>Corporate</option><option>Other</option></select></div>
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
                <button class="btn btn-ghost btn-sm" onclick="pickImage(imgAdd)">＋ Add Photo</button>
            </div>
            <div class="preview-box" id="imgPrimary" data-empty="🎂"></div>
            <div class="thumbs mini mt8" id="imgStrip"></div>
            <?php if (!empty($refImgs)): ?>
            <div class="hint mt8">Reference designs:</div>
            <div class="thumbs mini mt8">
                <?php foreach ($refImgs as $r): ?>
                <div class="thb" onclick="addRef('<?php echo htmlspecialchars($r, ENT_QUOTES); ?>')"><img src="<?php echo htmlspecialchars($r); ?>" alt=""></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card summary">
            <div class="card-title"><span class="ic">🧾</span> Order Summary</div>
            <div class="s-row"><span class="k">Category</span><span class="v" id="sCat">—</span></div>
            <div class="s-row"><span class="k">Flavour</span><span class="v" id="sFlav">—</span></div>
            <div class="s-row"><span class="k">Ladi</span><span class="v" id="sLadi">—</span></div>
            <div class="s-row"><span class="k">Shape</span><span class="v" id="sShape">—</span></div>
            <div class="s-row"><span class="k">Size / UOM</span><span class="v" id="sSize">—</span></div>
            <div class="s-row"><span class="k">Quantity</span><span class="v" id="sQty">1</span></div>
            <div class="s-row"><span class="k">Price (per unit)</span><span class="v" id="sPrice">Rs. 0</span></div>
            <div class="s-row"><span class="k">Subtotal</span><span class="v" id="sSub">Rs. 0</span></div>
            <div class="s-row" id="sExtraRow" style="display:none;"><span class="k">+ Extra Charges</span><span class="v" id="sExtra">Rs. 0</span></div>
            <div class="s-total"><span>Total Amount</span><span id="sTotal">Rs. 0</span></div>
            <button class="btn btn-primary btn-block mt12" id="confirmBtn" onclick="saveCake('confirmed', this)">🛒 Add to Order <span class="hint" style="color:#cfe1ff;">(F9)</span></button>
            <button class="btn btn-ghost btn-block mt8" onclick="saveCake('hold', this)">⏸ Hold Order <span class="hint">(F10)</span></button>
            <a class="btn btn-outline btn-block mt8" href="order_list.php">📋 View All Orders</a>
        </div>
    </div>
</div>

<script>
var CFG = <?php echo json_encode($CFG); ?>;
var PRODUCTS = <?php echo json_encode(array_values(array_map(function ($p) {
    return array('id' => intval($p['inv_id']), 'name' => $p['prod_name'], 'price' => floatval($p['retail_price']), 'uom' => $p['uom']);
}, $products))); ?>;
var selected = { kind: 'cat', idx: 0, invId: 0, name: '', price: 0 };

function pickTile(groupId, el) {
    document.querySelectorAll('#' + groupId + ' .tile').forEach(function (t) { t.classList.remove('on'); });
    el.classList.add('on');
}
function fillSelect(id, pairs) {
    var sel = document.getElementById(id);
    sel.innerHTML = '';
    for (var i = 0; i < pairs.length; i++) {
        var o = document.createElement('option');
        o.value = pairs[i][0];
        o.textContent = pairs[i][1];
        sel.appendChild(o);
    }
}
function fillCombos(data) {
    // flavors: pair [value, label(with price)]
    var fp = [];
    for (var f in data.flavors) {
        var pv = data.flavors[f];
        fp.push([f, f + (pv ? ' (Rs. ' + Number(pv).toLocaleString() + ')' : '')]);
    }
    fillSelect('flavor', fp);
    fillSelect('size', data.sizes.map(function (s) { return [s, s]; }));
    fillSelect('ladi', data.ladi.map(function (s) { return [s, s]; }));
}
function pickCategory(el) {
    pickTile('catTiles', el);
    var i = parseInt(el.dataset.idx, 10);
    var c = CFG.categories[i];
    selected = { kind: 'cat', idx: i, invId: invIdByName(c.name), name: c.name, price: c.price };
    fillCombos(c);
    document.getElementById('price').value = Math.round(c.price);
    recalc();
}
function pickInventory(el) {
    pickTile('catTiles', el);
    selected = { kind: 'inv', idx: -1, invId: parseInt(el.dataset.id, 10), name: el.querySelector('.t-lb').textContent, price: parseFloat(el.dataset.price) };
    fillCombos({ flavors: CFG.default_flavors, sizes: CFG.default_sizes, ladi: CFG.default_ladi });
    document.getElementById('price').value = Math.round(selected.price);
    recalc();
}
function invIdByName(name) {
    for (var i = 0; i < PRODUCTS.length; i++) if (PRODUCTS[i].name.toLowerCase() === String(name).toLowerCase()) return PRODUCTS[i].id;
    return 0;
}
function filterCategories() {
    var q = document.getElementById('cakeSearch').value.toLowerCase().trim();
    document.querySelectorAll('#catTiles .tile').forEach(function (t) {
        if (t.dataset.kind === 'cat') {
            t.style.display = (!q || t.dataset.name.indexOf(q) > -1) ? '' : 'none';
        } else {
            t.style.display = (q && t.dataset.name.indexOf(q) > -1) ? '' : 'none';
        }
    });
}
function onFlavor() {
    var sel = document.getElementById('flavor');
    var label = sel.value;
    if (selected.kind === 'cat') {
        var c = CFG.categories[selected.idx];
        if (c.flavors[label]) document.getElementById('price').value = Math.round(c.flavors[label]);
    }
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
function addRef(url) {
    fetch(url).then(function (r) { return r.blob(); }).then(function (b) {
        var rd = new FileReader();
        rd.onload = function () { imgAdd(rd.result); };
        rd.readAsDataURL(b);
    });
}
function recalc() {
    var price = parseFloat(document.getElementById('price').value) || 0;
    var sizeLabel = document.getElementById('size').value || '1 Kg';
    var parsed = parseSize(sizeLabel);
    var qty = parseInt(document.getElementById('qty').value, 10) || 1;
    var extra = parseInt(document.getElementById('extraCharge').value, 10) || 0;

    var sub = price * parsed.tiers * qty;
    var total = sub + extra;

    document.getElementById('lineTotal').textContent = money(total);
    document.getElementById('sCat').textContent = selected.name || '—';
    document.getElementById('sFlav').textContent = document.getElementById('flavor').value || '—';
    document.getElementById('sLadi').textContent = document.getElementById('ladi').value || '—';
    document.getElementById('sShape').textContent = activeData('shapeTiles', 'data-shape') || '—';
    document.getElementById('sSize').textContent = sizeLabel;
    document.getElementById('sQty').textContent = qty;
    document.getElementById('sPrice').textContent = money(price);
    document.getElementById('sSub').textContent = money(sub);
    document.getElementById('sExtraRow').style.display = extra > 0 ? '' : 'none';
    document.getElementById('sExtra').textContent = money(extra);
    document.getElementById('sTotal').textContent = money(total);
}
function parseSize(label) {
    var m = String(label).match(/^\s*([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z]+)?/);
    if (!m) return { tiers: 1, uom: 'kg' };
    return { tiers: parseFloat(m[1]), uom: (m[2] || 'kg').toLowerCase() };
}
function saveCake(status, btn) {
    if (!selected.name) { showToast('Select a cake category first', 'error'); return; }
    var price = parseFloat(document.getElementById('price').value) || 0;
    var parsed = parseSize(document.getElementById('size').value);
    var qty = parseInt(document.getElementById('qty').value, 10) || 1;
    var extra = parseInt(document.getElementById('extraCharge').value, 10) || 0;
    var ladi = document.getElementById('ladi').value;

    var note = document.getElementById('note').value;
    if (ladi && ladi !== 'N/A') note = 'LADI: ' + ladi + (note ? ' | ' + note : '');

    var items = [{
        inv_id: selected.invId || 0,
        name: selected.name,
        category: selected.name,
        price: price * parsed.tiers,        // save_order.php multiplies by qty
        qty: qty,
        flavor: document.getElementById('flavor').value,
        shape: activeData('shapeTiles', 'data-shape'),
        uom: parsed.uom,
        tiers: parsed.tiers,
        cake_message: document.getElementById('cakeMsg').value,
        note: note,
        image_data: primaryImage(),
        audio_data: ''
    }];
    if (extra > 0) {
        items.push({ inv_id: 0, name: 'Extra Charges', category: 'Extra Charges', price: extra, qty: 1, flavor: '', shape: '', uom: 'pcs', tiers: 1, cake_message: '', note: 'Extra charge — cake order', image_data: '', audio_data: '' });
    }

    submitOrder(items, { status: status, btn: btn, extra_images: extraImages() }, function (res) { defaultAfterSave(res, status); });
}

bindCustomerLookup('custCell', 'custName');
pickCategory(document.querySelector('#catTiles .tile.on'));
initShortcuts({
    searchId: 'cakeSearch',
    onConfirm: function () { saveCake('confirmed', document.getElementById('confirmBtn')); },
    onHold: function () { saveCake('hold', null); }
});
</script>

<?php include 'includes/app_footer.php'; ?>
