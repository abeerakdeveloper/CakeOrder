<?php
require_once 'db.php';
require_once 'includes/product_images.php';
requireRole(array(1, 3));

date_default_timezone_set('Asia/Karachi');

$type = isset($_GET['type']) && $_GET['type'] === 'sweetsbox' ? 'sweetsbox' : 'lunchbox';
$isSweets = ($type === 'sweetsbox');

$prodSql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom
            FROM inventory
            WHERE manufacture = 'Finish Product' AND active = 1
            ORDER BY prod_name ASC LIMIT 500";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) while ($p = mysqli_fetch_assoc($prodRes)) $products[] = $p;

if (empty($products)) {
    $fallbackRes = mysqli_query($mysqli, "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom FROM inventory WHERE active = 1 ORDER BY prod_name ASC LIMIT 500");
    if ($fallbackRes) while ($p = mysqli_fetch_assoc($fallbackRes)) $products[] = $p;
}

$boxWord   = $isSweets ? 'Sweets Box' : 'Lunch Box';
$boxesWord = $isSweets ? 'sweets boxes' : 'lunch boxes';
$icon      = $isSweets ? '🍬' : '🍱';
$units     = array('piece', 'pieces', 'portion', 'box', 'cup', 'bottle', 'kg', 'dozen');
$sources   = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');

$pageTitle  = $boxWord . ' Order';
$pageKey    = $isSweets ? 'sweets' : 'lunch';
$shellNoNav = true;
include 'includes/app_shell.php';
?>

<style>
.set-card { overflow: visible !important; position: relative !important; }
.set-body { overflow: visible !important; position: relative !important; }
.card { overflow: visible !important; }
.set-picker-row { position: relative !important; z-index: 100 !important; }
.suggest-box {
    position: absolute !important;
    top: calc(100% + 4px) !important;
    left: 0 !important;
    right: 0 !important;
    background: #ffffff !important;
    border: 1px solid #bcd3fb !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 30px rgba(22,40,63,0.22) !important;
    max-height: 280px !important;
    overflow-y: auto !important;
    z-index: 99999 !important;
}
.suggest-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f0f3f8;
}
.suggest-item:hover, .suggest-item.active {
    background: #eaf1fe !important;
}
</style>

<div class="page-head">
    <div class="ph-ic"><?php echo $icon; ?></div>
    <div>
        <h2>Create <?php echo $boxWord; ?> Order</h2>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-outline" href="order_box.php?type=<?php echo $type; ?>">＋ New Order</a>
    <a class="btn btn-ghost" href="order_box.php?type=<?php echo $isSweets ? 'lunchbox' : 'sweetsbox'; ?>">Switch to <?php echo $isSweets ? 'Lunch' : 'Sweets'; ?> Box</a>
</div>

<div class="order-cols">
    <!-- ============ MAIN: sets & customer ============ -->
    <div>
        <div class="card compact">
            <div class="grid-3">
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
            <div class="fld mt12" id="addrRow" style="display:none;">
                <label>📍 Delivery Address</label>
                <input class="inp" id="deliveryAddress" placeholder="Full delivery address...">
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span class="ic"><?php echo $icon; ?></span> <?php echo $boxWord; ?> Sets
                <span class="spacer"></span>
                <button class="btn btn-outline btn-sm" onclick="addSet()">＋ Add another set</button>
            </div>
            <div id="setsWrap"></div>
            <div class="empty" id="setsEmpty"><span class="big"><?php echo $icon; ?></span>No sets yet — click “＋ Add another set” to start.</div>
        </div>

        <button class="collapse-btn" onclick="toggleCollapse('moreOpts', this)">▾ More options</button>
        <div class="collapse-body" id="moreOpts">
            <div class="card">
                <div class="grid-3">
                    <div class="fld"><label>⚡ Priority</label>
                        <select class="inp" id="priority"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="vip">VIP</option></select></div>
                    <div class="fld"><label>🎉 Occasion</label>
                        <select class="inp" id="occasion"><option value="">— None —</option><option>Birthday</option><option>Anniversary</option><option>Wedding</option><option>Corporate</option><option>Other</option></select></div>
                    <div class="fld"><label>📍 Source</label>
                        <select class="inp" id="orderSource"><?php foreach ($sources as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ RIGHT: summary ============ -->
    <div>
        <div class="card">
            <div class="card-title"><span class="ic">📷</span> Order Photos <span class="spacer"></span>
                <button class="btn btn-ghost btn-sm" onclick="pickImage(imgAdd)">＋ Add</button>
            </div>
            <div class="preview-box" id="imgPrimary" data-empty="📷" style="height:150px;"></div>
            <div class="thumbs mini mt8" id="imgStrip"></div>
        </div>
        <div class="card summary mt16">
            <div class="card-title"><span class="ic">🧾</span> Order Summary</div>
            <div class="s-stat">
                <div class="box"><div class="n"><?php echo $icon; ?> <span id="sumSets">0</span></div><div class="l"><?php echo $boxWord; ?> Sets</div></div>
                <div class="box"><div class="n">🍴 <span id="sumBoxes">0</span></div><div class="l">Total <?php echo ucfirst($boxesWord); ?></div></div>
            </div>
            <div class="card-title" style="font-size:13px;">Set Breakdown</div>
            <div id="sumBreak"><div class="empty" style="padding:10px;">No sets added</div></div>
            <div class="s-row mt12"><span class="k">Items Total</span><span class="v" id="sumItems">Rs. 0</span></div>
            <div class="s-row" id="sumExtraRow" style="display:none;"><span class="k">+ Extra Charges</span><span class="v" id="sumExtra">Rs. 0</span></div>
            <div class="fld mt8" id="packFld">
                <label>Extra Charges (Rs.)</label>
                <input class="inp" type="number" id="packCharge" value="0" min="0" onchange="recalc()" placeholder="Rs. 0">
            </div>
            <div class="s-row"><span class="k">Total Amount</span><span class="v" id="sumTotalWrap"><strong id="sumTotal">Rs. 0</strong></span></div>
            <div class="s-row" id="sweetNote" style="display:none;"><span class="k">Total Amount</span><span class="v muted">Will be calculated after weight</span></div>
            <div class="row mt8" style="flex-wrap:nowrap;">
                <div class="fld"><label>Advance (Rs.)</label><input class="inp" type="number" id="advance" value="0" min="0"></div>
                <div class="fld"><label>Method</label>
                    <select class="inp" id="advMethod">
                        <option value="">—</option><option value="cash">💵 Cash</option><option value="bank">🏦 Bank</option>
                        <option value="card">💳 Card</option><option value="easypaisa">📱 Easypaisa</option>
                    </select></div>
            </div>
            <button class="btn btn-primary btn-block mt12" onclick="saveBox('confirmed', this)">🛒 Add to Order</button>
            <button class="btn btn-ghost btn-block mt8" onclick="saveBox('hold', this)">⏸ Hold Order</button>
            <a class="btn btn-outline btn-block mt8" href="order_list.php">📋 View All Orders</a>
        </div>
    </div>
</div>

<script>
var IS_SWEETS = <?php echo $isSweets ? 'true' : 'false'; ?>;
var BOX_LABEL = '<?php echo $boxWord; ?>';
var BOXES_WORD = '<?php echo $boxesWord; ?>';
var UNITS = <?php echo json_encode($units); ?>;
var PRODUCTS = <?php echo json_encode(array_values(array_map(function ($p) {
    return array('id' => intval($p['inv_id']), 'name' => $p['prod_name'], 'price' => floatval($p['retail_price']), 'uom' => $p['uom'],
                 'barcode' => isset($p['barcode']) ? $p['barcode'] : '',
                 'img' => productImageUrl($p['inv_id'], $p['prod_name']), 'emoji' => productEmoji($p['prod_name']));
}, $products))); ?>;

var sets = [];          // [{boxes:6, items:[{id,name,price,per,unit,on}]}]
var activeSet = -1;

function thumbHtml(p, cls) {
    var inner = p.img ? '<img src="' + p.img + '" alt="">' : (p.emoji || productEmojiLocal(p.name));
    return '<span class="' + (cls || 'th') + '">' + inner + '</span>';
}
function productEmojiLocal(n) { return '🍽️'; }
function findProduct(id) { for (var i = 0; i < PRODUCTS.length; i++) if (PRODUCTS[i].id === id) return PRODUCTS[i]; return null; }

function addSet() {
    sets.push({ boxes: 1, items: [] });
    activeSet = sets.length - 1;
    renderSets(); recalc();
}
function removeSet(i) {
    if (!confirm('Remove Set ' + (i + 1) + '?')) return;
    sets.splice(i, 1);
    if (activeSet >= sets.length) activeSet = sets.length - 1;
    renderSets(); recalc();
}
function setBoxes(i, d) {
    sets[i].boxes = Math.max(1, sets[i].boxes + d);
    renderSets(); recalc();
}
function setBoxesDirect(i, v) {
    sets[i].boxes = Math.max(1, parseInt(v, 10) || 1);
    recalc(); renderSummary();
}
function selectSet(i) {
    if (activeSet === i) return;
    activeSet = i;
    var cards = document.querySelectorAll('#setsWrap .set-card');
    for (var c = 0; c < cards.length; c++) {
        cards[c].style.borderColor = (c === activeSet) ? 'var(--blue)' : '';
    }
}

function addPickedProduct(setIndex, prodId) {
    var p = findProduct(prodId);
    if (!p) return;
    var s = sets[setIndex];
    if (!s) return;
    for (var k = 0; k < s.items.length; k++) {
        if (s.items[k].id === p.id) {
            s.items[k].per += 1;
            renderSets();
            recalc();
            showToast('Increased: ' + p.name + ' (' + s.items[k].per + ' per box)', 'info');
            setTimeout(function() {
                var inp = document.getElementById('pick_input_' + setIndex);
                if (inp) inp.focus();
            }, 50);
            return;
        }
    }
    s.items.push({ id: p.id, name: p.name, price: p.price, per: 1, unit: p.uom === 'kg' ? 'kg' : 'piece', on: true });
    renderSets();
    recalc();
    showToast('Added to Set ' + (setIndex + 1) + ': ' + p.name, 'success');
    setTimeout(function() {
        var inp = document.getElementById('pick_input_' + setIndex);
        if (inp) inp.focus();
    }, 50);
}

function addCustomItemToSet(setIndex, defaultName) {
    var name = prompt('Item name:', defaultName || '');
    if (!name) return;
    var price = parseFloat(prompt('Price (Rs.):', '0')) || 0;
    var s = sets[setIndex];
    if (!s) return;
    s.items.push({ id: 0, name: name, price: price, per: 1, unit: 'piece', on: true });
    renderSets();
    recalc();
    showToast('Added to Set ' + (setIndex + 1) + ': ' + name, 'success');
}

function setItemField(i, k, field, value) {
    var it = sets[i].items[k];
    if (field === 'on') it.on = value;
    else if (field === 'per') it.per = Math.max(1, parseInt(value, 10) || 1);
    else it[field] = value;
    recalc(); renderSummary();
}
function removeItem(i, k) { sets[i].items.splice(k, 1); renderSets(); recalc(); }

function renderSets() {
    var wrap = document.getElementById('setsWrap');
    document.getElementById('setsEmpty').style.display = sets.length ? 'none' : '';
    var html = '';
    for (var i = 0; i < sets.length; i++) {
        var s = sets[i];
        var onCount = 0; for (var c = 0; c < s.items.length; c++) if (s.items[c].on) onCount++;
        html += '<div class="set-card" style="' + (i === activeSet ? 'border-color:var(--blue);' : '') + '" onclick="selectSet(' + i + ')">';
        html += '<div class="set-head"><span class="num">' + (i + 1) + '</span><div><h5>Set ' + (i + 1) + '</h5>' +
                '<span class="sub">' + s.boxes + ' ' + BOXES_WORD + ' • Combination ' + (i + 1) + '</span></div>' +
                '<span class="spacer"></span><span class="lbl" style="font-size:12px;color:var(--muted);">Quantity</span>' +
                '<span class="stepper-n"><button type="button" onclick="event.stopPropagation();setBoxes(' + i + ',-1)">−</button>' +
                '<input type="number" value="' + s.boxes + '" min="1" onclick="event.stopPropagation()" onchange="setBoxesDirect(' + i + ',this.value)">' +
                '<button type="button" onclick="event.stopPropagation();setBoxes(' + i + ',1)">＋</button></span>' +
                '<span class="lbl" style="font-size:11px;color:var(--muted);">(' + BOXES_WORD + ')</span>' +
                '<button class="btn btn-danger btn-sm" onclick="event.stopPropagation();removeSet(' + i + ')">✕</button></div>';
        html += '<div class="set-body"><div class="lbl" style="font-size:11px;color:var(--muted);margin-bottom:6px;">Items in this set</div>';
        if (!s.items.length) html += '<div class="empty" style="padding:14px;">No items — search and add items below.</div>';
        for (var k = 0; k < s.items.length; k++) {
            var it = s.items[k];
            var p = findProduct(it.id) || { img: '', emoji: '🍽️' };
            html += '<div class="set-item">' +
                '<input type="checkbox" class="chk" ' + (it.on ? 'checked' : '') + ' onclick="event.stopPropagation();setItemField(' + i + ',' + k + ',\'on\',this.checked)">' +
                thumbHtml(p) +
                '<span class="nm">' + escHtml(it.name) + ' <span class="lbl">Rs. ' + Math.round(it.price).toLocaleString() + '</span></span>' +
                '<span class="lbl">Qty per box</span>' +
                '<input class="inp" style="width:64px;" type="number" min="1" value="' + it.per + '" onclick="event.stopPropagation()" onchange="setItemField(' + i + ',' + k + ',\'per\',this.value)">' +
                '<select class="inp" style="width:96px;" onclick="event.stopPropagation()" onchange="setItemField(' + i + ',' + k + ',\'unit\',this.value)">';
            for (var u = 0; u < UNITS.length; u++) html += '<option ' + (UNITS[u] === it.unit ? 'selected' : '') + '>' + UNITS[u] + '</option>';
            html += '</select><button class="x" onclick="event.stopPropagation();removeItem(' + i + ',' + k + ')">✕</button></div>';
        }
        html += '<div class="set-picker-row mt8">' +
            '<div style="position:relative;display:flex;gap:8px;align-items:center;">' +
                '<div style="position:relative;flex:1;">' +
                    '<input type="text" class="inp" id="pick_input_' + i + '" placeholder="🔍 Click or type to search items (e.g. samosa, chicken, gulab jamun)..." autocomplete="off" ' +
                    'onclick="event.stopPropagation();onPickFocus(' + i + ', this.value)" ' +
                    'onfocus="event.stopPropagation();onPickFocus(' + i + ', this.value)" ' +
                    'oninput="event.stopPropagation();onPickSearch(' + i + ', this.value)" ' +
                    'onkeydown="event.stopPropagation();onPickKeydown(' + i + ', event)">' +
                    '<div id="pick_suggest_' + i + '" class="suggest-box" style="display:none;"></div>' +
                '</div>' +
                '<button type="button" class="btn btn-outline btn-sm" style="flex:0 0 auto;" onclick="event.stopPropagation();addCustomItemToSet(' + i + ')">⊕ Custom Item</button>' +
            '</div></div>';
        html += '</div></div>';
    }
    wrap.innerHTML = html;
    if (window.refreshTabOrder) refreshTabOrder();
}

// Fuzzy matching algorithm
function fuzzyMatch(text, query) {
    text = (text || '').toLowerCase();
    query = (query || '').toLowerCase().trim();
    if (!query) return true;
    
    // Direct substring
    if (text.indexOf(query) !== -1) return true;
    
    // Multi-token match
    var words = query.split(/\s+/);
    var allMatch = true;
    for (var w = 0; w < words.length; w++) {
        if (words[w] && text.indexOf(words[w]) === -1) {
            allMatch = false;
            break;
        }
    }
    if (allMatch) return true;
    
    // Character sequence fuzzy match
    var qIdx = 0;
    for (var i = 0; i < text.length && qIdx < query.length; i++) {
        if (text[i] === query[qIdx]) qIdx++;
    }
    return qIdx === query.length;
}

var pickSearchTimer = null;

function onPickFocus(setIndex, query) {
    onPickSearch(setIndex, query || '');
}

function onPickSearch(setIndex, query) {
    var q = (query || '').trim();
    var matches = [];
    for (var p = 0; p < PRODUCTS.length; p++) {
        var prod = PRODUCTS[p];
        if (!q || fuzzyMatch(prod.name + ' ' + (prod.barcode || '') + ' ' + (prod.uom || ''), q)) {
            matches.push(prod);
            if (matches.length >= 30) break;
        }
    }
    
    renderSuggestBox(setIndex, matches, q);

    // Live server query fallback
    if (q.length >= 2) {
        clearTimeout(pickSearchTimer);
        pickSearchTimer = setTimeout(function () {
            fetch('get_products.php?search=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.products && data.products.length > 0) {
                        var serverMatches = data.products.map(function (p) {
                            return {
                                id: parseInt(p.inv_id, 10),
                                name: p.prod_name,
                                price: parseFloat(p.retail_price),
                                uom: p.uom,
                                barcode: p.barcode,
                                img: '',
                                emoji: '🍽️'
                            };
                        });
                        for (var sm = 0; sm < serverMatches.length; sm++) {
                            if (!findProduct(serverMatches[sm].id)) {
                                PRODUCTS.push(serverMatches[sm]);
                            }
                        }
                        renderSuggestBox(setIndex, serverMatches, q);
                    }
                }).catch(function () { });
        }, 180);
    }
}

function renderSuggestBox(setIndex, matches, q) {
    var box = document.getElementById('pick_suggest_' + setIndex);
    if (!box) return;
    
    // Hide other suggestion boxes
    document.querySelectorAll('[id^="pick_suggest_"]').forEach(function (el) {
        if (el.id !== 'pick_suggest_' + setIndex) el.style.display = 'none';
    });

    if (!matches || matches.length === 0) {
        box.innerHTML = '<div class="suggest-item" style="color:var(--muted);cursor:default;padding:12px;">No matching products found</div>' +
            (q ? '<div class="suggest-item" onmousedown="event.preventDefault();addCustomItemToSet(' + setIndex + ',\'' + escHtml(q).replace(/'/g, "\\'") + '\')"><span class="name" style="color:var(--blue);font-size:12.5px;font-weight:600;">⊕ Add "<strong>' + escHtml(q) + '</strong>" as custom item</span></div>' : '');
        box.style.display = 'block';
        return;
    }
    
    var html = '';
    for (var m = 0; m < matches.length; m++) {
        var item = matches[m];
        var thumb = item.img ? '<img src="' + item.img + '" style="width:30px;height:30px;border-radius:6px;object-fit:cover;">' : '<span style="font-size:18px;">' + (item.emoji || '🍽️') + '</span>';
        html += '<div class="suggest-item pick-suggest-item' + (m === 0 ? ' active' : '') + '" data-id="' + item.id + '" onmousedown="event.preventDefault();addPickedProduct(' + setIndex + ',' + item.id + ')">' +
            '<div style="display:flex;align-items:center;gap:10px;">' +
            '<span style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' + thumb + '</span>' +
            '<div style="flex:1;min-width:0;"><div class="name" style="font-size:13px;font-weight:600;color:var(--text);">' + escHtml(item.name) + '</div>' +
            '<div class="meta" style="font-size:11.5px;color:var(--muted);">Rs. ' + Math.round(item.price).toLocaleString() + (item.uom ? ' / ' + escHtml(item.uom) : '') + '</div></div>' +
            '<button type="button" class="btn btn-outline btn-sm" style="padding:3px 10px;font-size:11px;">＋ Add</button>' +
            '</div></div>';
    }
    
    if (q) {
        html += '<div class="suggest-item" onmousedown="event.preventDefault();addCustomItemToSet(' + setIndex + ',\'' + escHtml(q).replace(/'/g, "\\'") + '\')" style="border-top:1px dashed var(--line);background:#fafcff;">' +
            '<span class="name" style="color:var(--blue);font-size:12px;font-weight:600;">⊕ Add "' + escHtml(q) + '" as custom item</span></div>';
    }
    
    box.innerHTML = html;
    box.style.display = 'block';
}

function onPickKeydown(setIndex, e) {
    var box = document.getElementById('pick_suggest_' + setIndex);
    if (!box || box.style.display === 'none') {
        if (e.key === 'ArrowDown' || e.key === 'Enter') {
            onPickSearch(setIndex, e.target.value);
        }
        return;
    }
    
    var items = box.querySelectorAll('.pick-suggest-item');
    var activeIdx = -1;
    for (var i = 0; i < items.length; i++) {
        if (items[i].classList.contains('active')) {
            activeIdx = i;
            break;
        }
    }
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (activeIdx >= 0 && activeIdx < items.length - 1) {
            items[activeIdx].classList.remove('active');
            items[activeIdx + 1].classList.add('active');
            items[activeIdx + 1].scrollIntoView({ block: 'nearest' });
        } else if (activeIdx < 0 && items.length > 0) {
            items[0].classList.add('active');
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (activeIdx > 0) {
            items[activeIdx].classList.remove('active');
            items[activeIdx - 1].classList.add('active');
            items[activeIdx - 1].scrollIntoView({ block: 'nearest' });
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIdx >= 0 && items[activeIdx]) {
            var prodId = parseInt(items[activeIdx].dataset.id, 10);
            addPickedProduct(setIndex, prodId);
        } else if (items.length > 0) {
            var firstId = parseInt(items[0].dataset.id, 10);
            addPickedProduct(setIndex, firstId);
        } else if (e.target.value.trim()) {
            addCustomItemToSet(setIndex, e.target.value.trim());
        }
        box.style.display = 'none';
        e.target.value = '';
    } else if (e.key === 'Escape') {
        box.style.display = 'none';
    }
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.set-picker-row')) {
        document.querySelectorAll('[id^="pick_suggest_"]').forEach(function (el) {
            el.style.display = 'none';
        });
    }
});

function recalc() { renderSummary(); }
function totals() {
    var boxes = 0, itemsTotal = 0, breakdown = [];
    for (var i = 0; i < sets.length; i++) {
        var s = sets[i], setBoxes = s.boxes, setLines = 0;
        boxes += setBoxes;
        for (var k = 0; k < s.items.length; k++) {
            var it = s.items[k];
            if (!it.on) continue;
            itemsTotal += it.price * it.per * setBoxes;
            setLines++;
        }
        breakdown.push({ n: i + 1, boxes: setBoxes, lines: setLines });
    }
    return { boxes: boxes, itemsTotal: itemsTotal, breakdown: breakdown };
}
function renderSummary() {
    var t = totals();
    var pack = parseInt(document.getElementById('packCharge').value, 10) || 0;
    document.getElementById('sweetNote').style.display = IS_SWEETS ? '' : 'none';
    document.getElementById('sumTotalWrap').parentNode.style.display = IS_SWEETS ? 'none' : '';
    document.getElementById('sumSets').textContent = sets.length;
    document.getElementById('sumBoxes').textContent = t.boxes;
    var html = '';
    if (!t.breakdown.length) html = '<div class="empty" style="padding:10px;">No sets added</div>';
    for (var i = 0; i < t.breakdown.length; i++) {
        html += '<div class="s-row"><span class="k"><span class="num" style="display:inline-flex;width:18px;height:18px;font-size:10px;border-radius:50%;background:var(--blue);color:#fff;align-items:center;justify-content:center;margin-right:6px;">' + t.breakdown[i].n + '</span>Set ' + t.breakdown[i].n + '</span><span class="v">' + t.breakdown[i].boxes + ' boxes</span></div>';
    }
    document.getElementById('sumBreak').innerHTML = html;
    document.getElementById('sumItems').textContent = money(t.itemsTotal);
    document.getElementById('sumExtraRow').style.display = pack > 0 ? '' : 'none';
    document.getElementById('sumExtra').textContent = money(pack);
    document.getElementById('sumTotal').textContent = money(t.itemsTotal + pack);
}

function saveBox(status, btn) {
    if (!sets.length) { showToast('Add at least one set', 'error'); return; }
    var items = [], any = false;
    for (var i = 0; i < sets.length; i++) {
        var s = sets[i];
        for (var k = 0; k < s.items.length; k++) {
            var it = s.items[k];
            if (!it.on) continue;
            any = true;
            items.push({
                inv_id: it.id,
                name: it.name,
                category: it.name,
                price: IS_SWEETS ? 0 : it.price,
                qty: it.per * s.boxes,
                flavor: '',
                shape: '',
                uom: it.unit,
                tiers: 1,
                cake_message: '',
                note: BOX_LABEL + ' Set ' + (i + 1) + ' • ' + s.boxes + ' boxes × ' + it.per + ' ' + it.unit + ' per box',
                image_data: '',
                audio_data: ''
            });
        }
    }
    if (!any) { showToast('Each set needs at least one checked item', 'error'); return; }
    var pack = IS_SWEETS ? 0 : (parseInt(document.getElementById('packCharge').value, 10) || 0);
    if (pack > 0) items.push({ inv_id: 0, name: 'Extra Charges', category: 'Extra Charges', price: pack, qty: 1, flavor: '', shape: '', uom: 'pcs', tiers: 1, cake_message: '', note: 'Extra charge — ' + BOX_LABEL.toLowerCase() + ' order', image_data: '', audio_data: '' });

    submitOrder(items, { status: status, btn: btn, extra_images: extraImages(), advance_method: document.getElementById('advMethod').value }, function (res) { defaultAfterSave(res, status); });
}

if (IS_SWEETS) document.body.classList.add('sweets');
bindCustomerLookup('custCell', 'custName');
initShortcuts({
    searchId: 'custName',
    onConfirm: function () { saveBox('confirmed', null); },
    onHold: function () { saveBox('hold', null); }
});
addSet();
</script>

<?php include 'includes/app_footer.php'; ?>
