<?php
require_once 'db.php';
require_once 'includes/cake_config.php';
requireRole(array(1, 3));

date_default_timezone_set('Asia/Karachi');

$sources = array('walk-in' => 'Walk-in', 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'online' => 'Online/Web', 'instagram' => 'Instagram', 'facebook' => 'Facebook');

$pageTitle  = 'Custom Sale';
$pageKey    = 'custom';
$shellNoNav = true;
include 'includes/app_shell.php';
?>

<div class="page-head">
    <div class="ph-ic">✎</div>
    <div>
        <h2>Custom Sale</h2>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-outline" href="custom_pos.php">+ New Sale</a>
</div>

<div class="order-cols">
    <div>
        <!-- customer -->
        <div class="card compact">
            <div class="grid-4">
                <div class="fld" style="position:relative;">
                    <label>Customer (F4)</label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <input class="inp" id="custCell" placeholder="Search name or phone..." autocomplete="off">
                        <button class="btn btn-outline btn-sm" style="flex:0 0 auto;" onclick="document.getElementById('custName').focus()">+ Add New</button>
                    </div>
                    <div class="row mt8" style="flex-wrap:nowrap;">
                        <input class="inp" id="custName" placeholder="Walk-in" value="Walk-in" style="flex:1;">
                        <input class="inp" id="custPhone" placeholder="Contact" inputmode="numeric" style="flex:1;">
                    </div>
                    <div id="suggestBox" class="suggest-box" style="display:none;"></div>
                </div>
                <div class="fld">
                    <label>Order Type</label>
                    <select class="inp" id="orderTypeSel" onchange="toggleAddrRow()">
                        <option value="pickup">Pickup</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
                <div class="fld">
                    <label>Delivery Branch</label>
                    <select class="inp" id="deliverBranch">
                        <option value="">— Same as order branch —</option>
                        <?php foreach (deliveryBranches() as $db): ?>
                        <option value="<?php echo htmlspecialchars($db, ENT_QUOTES); ?>"><?php echo htmlspecialchars($db); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fld">
                    <label>Date &amp; Time</label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <input type="date" class="inp" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                        <input type="time" class="inp" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                    </div>
                </div>
            </div>
            <div class="fld mt12" id="addrRow" style="display:none;">
                <label>Delivery Address</label>
                <input class="inp" id="deliveryAddress" placeholder="Full delivery address...">
            </div>
        </div>

        <!-- items -->
        <div class="card">
            <div class="card-title">Sale Items
                <span class="spacer"></span>
                <button type="button" class="btn btn-outline btn-sm" onclick="rowAdd()">+ Add Item</button>
            </div>
            <div id="itemRows"></div>
            <div class="hint" id="rowsEmpty" style="padding:4px 2px;">No items yet — add item name, description, quantity, price and note per line.</div>
        </div>

        <button class="collapse-btn" onclick="toggleCollapse('moreOpts', this)">▾ More options</button>
        <div class="collapse-body" id="moreOpts">
            <div class="card">
                <div class="grid-3">
                    <div class="fld"><label>Priority</label>
                        <select class="inp" id="priority"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="vip">VIP</option></select></div>
                    <div class="fld"><label>Occasion</label>
                        <select class="inp" id="occasion"><option value="">— None —</option><option>Birthday</option><option>Anniversary</option><option>Wedding</option><option>Corporate</option><option>Other</option></select></div>
                    <div class="fld"><label>Source</label>
                        <select class="inp" id="orderSource"><?php foreach ($sources as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
        </div>
    </div>

    <!-- summary -->
    <div>
        <div class="card summary">
            <div class="card-title">Sale Summary</div>
            <div id="sumLines"><div class="empty" style="padding:10px;">No items added</div></div>
            <div class="s-total"><span>Total Amount</span><span id="sTotal">Rs. 0</span></div>
            <div class="row mt8" style="flex-wrap:nowrap;">
                <div class="fld"><label>Advance (Rs.)</label><input class="inp" type="number" id="advance" value="0" min="0"></div>
                <div class="fld"><label>Method</label>
                    <select class="inp" id="advMethod">
                        <option value="cash" selected>Cash</option><option value="bank">Bank</option>
                        <option value="card">Card</option><option value="easypaisa">Easypaisa</option>
                    </select></div>
            </div>
            <button class="btn btn-primary btn-block mt12" onclick="saveCustom('confirmed', this)">Add to Order (F9)</button>
            <button class="btn btn-ghost btn-block mt8" onclick="saveCustom('hold', this)">Hold Order (F10)</button>
            <a class="btn btn-outline btn-block mt8" href="order_list.php">View All Orders</a>
        </div>
    </div>
</div>

<script>
var rows = [];

function rowEsc(v) { return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
function rowAdd() { rows.push({ name: '', desc: '', qty: 1, price: 0, note: '' }); rowRender(); }
function rowDel(i) { rows.splice(i, 1); rowRender(); }
function rowSet(i, k, v) { rows[i][k] = v; rowRenderTotals(); }

function rowRender() {
    var wrap = document.getElementById('itemRows');
    document.getElementById('rowsEmpty').style.display = rows.length ? 'none' : '';
    var html = '';
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        html += '<div class="card compact mt8" style="background:#f8fafd;">' +
            '<div class="row" style="flex-wrap:nowrap;gap:8px;align-items:center;">' +
            '<input class="inp" style="flex:1.2;" placeholder="Item name" value="' + rowEsc(r.name) + '" onchange="rowSet(' + i + ',\'name\',this.value)">' +
            '<input class="inp" style="width:70px;flex:0 0 auto;" type="number" min="1" value="' + (parseInt(r.qty, 10) || 1) + '" onchange="rowSet(' + i + ',\'qty\',this.value)">' +
            '<input class="inp" style="width:100px;flex:0 0 auto;" type="number" min="0" placeholder="Price" value="' + (parseFloat(r.price) || '') + '" onchange="rowSet(' + i + ',\'price\',this.value)">' +
            '<button type="button" class="btn btn-ghost btn-sm" onclick="rowDel(' + i + ')">x</button></div>' +
            '<div class="row mt8" style="flex-wrap:nowrap;gap:8px;">' +
            '<input class="inp" style="flex:1;" placeholder="Description" value="' + rowEsc(r.desc) + '" onchange="rowSet(' + i + ',\'desc\',this.value)">' +
            '<input class="inp" style="flex:1;" placeholder="Note on item (optional)" value="' + rowEsc(r.note) + '" onchange="rowSet(' + i + ',\'note\',this.value)">' +
            '</div></div>';
    }
    wrap.innerHTML = html;
    rowRenderTotals();
    if (window.refreshTabOrder) refreshTabOrder();
}

function rowTotal() {
    var t = 0;
    for (var i = 0; i < rows.length; i++) t += (parseFloat(rows[i].price) || 0) * (parseInt(rows[i].qty, 10) || 1);
    return t;
}

function rowRenderTotals() {
    var html = '';
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        if (!r.name) continue;
        html += '<div class="s-row"><span class="k">' + rowEsc(r.name) + ' x' + (parseInt(r.qty, 10) || 1) + '</span><span class="v">Rs. ' + Math.round((parseFloat(r.price) || 0) * (parseInt(r.qty, 10) || 1)).toLocaleString() + '</span></div>';
    }
    document.getElementById('sumLines').innerHTML = html || '<div class="empty" style="padding:10px;">No items added</div>';
    document.getElementById('sTotal').textContent = 'Rs. ' + Math.round(rowTotal()).toLocaleString();
}

function saveCustom(status, btn) {
    var items = [];
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        if (!r.name) continue;
        var note = 'CUSTOM: ' + (r.desc || '');
        if (r.note) note += ' | ' + r.note;
        items.push({
            inv_id: 0,
            name: r.name,
            category: r.name,
            price: parseFloat(r.price) || 0,
            qty: parseInt(r.qty, 10) || 1,
            flavor: '',
            shape: '',
            uom: 'pcs',
            tiers: 1,
            cake_message: '',
            note: note,
            image_data: '',
            audio_data: ''
        });
    }
    if (!items.length) { showToast('Add at least one item', 'error'); return; }
    if (btn) { btn.disabled = true; }
    submitOrder(items, {
        status: status,
        btn: btn,
        advance_method: document.getElementById('advMethod').value
    }, function(res) { defaultAfterSave(res, status); });
}

bindCustomerLookup('custCell', 'custName');
rowAdd();
initShortcuts({
    onConfirm: function() { saveCustom('confirmed', null); },
    onHold: function() { saveCustom('hold', null); }
});
</script>

<?php include 'includes/app_footer.php'; ?>
