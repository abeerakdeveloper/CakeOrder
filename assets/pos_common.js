/* ============================================================
   Shared POS helpers — same behaviour as the original screens,
   only the look changed. Talks to the SAME php endpoints.
   ============================================================ */

function showToast(msg, type) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.className = 'toast ' + (type || 'info') + ' show';
    setTimeout(function () { t.classList.remove('show'); }, 3500);
}

function money(n) { return 'Rs. ' + Math.round(n || 0).toLocaleString(); }

function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

/* ---------- customer lookup (customer_lookup.php — unchanged) ----------
   NOTE: pages that define their own searchCustomer/selectCustomer/hideSuggest
   (the classic POS) keep theirs — these are only fallbacks. */
var custSearchTimer = null;

function bindCustomerLookup(cellId, nameId) {
    var cell = document.getElementById(cellId);
    if (!cell) return;
    cell.addEventListener('input', function () { searchCustomer(cell.value, cellId); });
    cell.addEventListener('blur', function () { setTimeout(hideSuggest, 200); });
}

if (typeof window.searchCustomer === 'undefined') {
    window.searchCustomer = function (q, cellId) {
    clearTimeout(custSearchTimer);
    if (!q || q.length < 2) { hideSuggest(); return; }
    custSearchTimer = setTimeout(function () {
        fetch('customer_lookup.php?action=search&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var box = document.getElementById('suggestBox');
                if (!box) return;
                if (!res.customers || res.customers.length === 0) { hideSuggest(); return; }
                var html = '';
                for (var i = 0; i < res.customers.length; i++) {
                    var c = res.customers[i];
                    html += '<div class="suggest-item" onmousedown="selectCustomer(\'' +
                        escHtml(c.cell).replace(/'/g, "\\'") + '\',\'' +
                        escHtml(c.name).replace(/'/g, "\\'") + '\')">';
                    html += '<div class="name">' + escHtml(c.name);
                    if (c.is_vip) html += '<span class="badge-vip">VIP</span>';
                    if (c.is_loyal) html += '<span class="badge-loyal">LOYAL</span>';
                    html += '</div>';
                    html += '<div class="meta">📱 ' + escHtml(c.cell) + ' • ' + c.orders + ' orders • Spent Rs. ' + Number(c.spent).toLocaleString() + '</div>';
                    if (c.fav_flavors) html += '<div class="meta">🍰 Likes: ' + escHtml(c.fav_flavors) + '</div>';
                    html += '</div>';
                }
                box.innerHTML = html;
                box.style.display = 'block';
            }).catch(function () { });
    }, 300);
    };

    window.selectCustomer = function (cell, name) {
        var c = document.getElementById('custCell');
        var n = document.getElementById('custName');
        var p = document.getElementById('custPhone');
        if (c) c.value = cell;
        if (n) n.value = name;
        if (p) p.value = cell;
        hideSuggest();
    };

    window.hideSuggest = function () {
        var box = document.getElementById('suggestBox');
        if (box) box.style.display = 'none';
    };
}

/* ---------- keyboard tab order: tab walks fields top-to-bottom, left-to-right ---------- */
function setupTabOrder() {
    var root = document.querySelector('main') || document.body;
    var els = Array.prototype.slice.call(root.querySelectorAll('input, select, textarea, button'));
    els = els.filter(function (el) {
        if (el.type === 'hidden' || el.disabled) return false;
        var st = window.getComputedStyle(el);
        return st.display !== 'none' && st.visibility !== 'hidden';
    });
    els.sort(function (a, b) {
        var ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
        var row = Math.round(ra.top / 12) - Math.round(rb.top / 12);
        if (row !== 0) return row;
        return ra.left - rb.left;
    });
    for (var i = 0; i < els.length; i++) els[i].tabIndex = i + 1;
}
window.refreshTabOrder = function () { setTimeout(setupTabOrder, 0); };
window.addEventListener('resize', function () { window.refreshTabOrder(); });

/* ---------- pickup / delivery toggle ---------- */
function setOrderType(type, btn) {
    document.querySelectorAll('.seg button').forEach(function (b) { b.classList.remove('on'); });
    btn.classList.add('on');
    var addr = document.getElementById('addrRow');
    if (addr) addr.style.display = (type === 'delivery') ? '' : 'none';
}
function getOrderType() {
    var sel = document.getElementById('orderTypeSel');
    if (sel) return sel.value;
    var on = document.querySelector('.seg button.on');
    return on ? on.getAttribute('data-type') : 'pickup';
}

/* ---------- image picking (resize to 1024 jpg, same as original) ---------- */
var pickedImageData = '';
var pickCallback = null;

function pickImage(cb) {
    pickCallback = cb;
    var inp = document.getElementById('imgFileInput');
    if (!inp) {
        inp = document.createElement('input');
        inp.type = 'file'; inp.id = 'imgFileInput'; inp.style.display = 'none';
        inp.accept = 'image/jpeg,image/jpg,image/png,.jpg,.jpeg,.png';
        inp.setAttribute('capture', 'environment');
        inp.onchange = function () { processImageFile(inp); };
        document.body.appendChild(inp);
    }
    inp.value = '';
    inp.click();
}

function processImageFile(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var okTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (okTypes.indexOf(file.type) === -1 && !/\.(jpg|jpeg|png)$/i.test(file.name)) {
        showToast('❌ Only JPG, JPEG or PNG images are allowed!', 'error'); input.value = ''; return;
    }
    if (file.size > 20 * 1024 * 1024) { showToast('❌ Image too large! Max 20MB.', 'error'); input.value = ''; return; }

    var reader = new FileReader();
    reader.onload = function (e) {
        var img = new Image();
        img.onload = function () {
            var maxSize = 1024, w = img.width, h = img.height;
            if (w > h) { if (w > maxSize) { h = Math.round(h * maxSize / w); w = maxSize; } }
            else { if (h > maxSize) { w = Math.round(w * maxSize / h); h = maxSize; } }
            var canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            pickedImageData = canvas.toDataURL('image/jpeg', 0.75);
            showToast('✅ Image ready (' + w + 'x' + h + ')', 'success');
            if (pickCallback) pickCallback(pickedImageData);
        };
        img.onerror = function () { showToast('❌ Invalid image file', 'error'); };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

/* ---------- save order (save_order.php — unchanged payload) ---------- */
function submitOrder(items, extra, onDone) {
    if (!items || items.length === 0) { showToast('Nothing to save — add at least one item.', 'error'); return; }

    var data = {
        items: items,
        extra_images: extra.extra_images || [],
        status: extra.status || 'confirmed',
        party_detail: val('custName') || 'Walk-in',
        cell_no: val('custPhone') || val('custCell'),
        deliver_date: val('deliverDate') || todayStr(),
        delivery_time: val('deliverTime') || '12:00',
        priority: val('priority') || 'normal',
        flat_disc: parseInt(val('flatDisc'), 10) || 0,
        advance: parseInt(val('advance'), 10) || 0,
        advance_method: extra.advance_method || '',
        occasion: val('occasion') || '',
        delivery_type: getOrderType(),
        delivery_address: (getOrderType() === 'delivery') ? val('deliveryAddress') : '',
        source: val('orderSource') || 'walk-in'
    };

    if (data.delivery_type === 'delivery' && !data.delivery_address) {
        showToast('Enter the delivery address', 'error'); return;
    }
    if (data.advance > 0 && !data.advance_method) {
        showToast('Select advance payment method', 'error'); return;
    }

    var btn = extra.btn || null;
    if (btn) { btn.disabled = true; btn.setAttribute('data-old', btn.innerHTML); btn.innerHTML = '⏳ Saving...'; }
    showToast('Saving order...', 'info');

    fetch('save_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                showToast('✅ Order #' + res.bill_no + ' saved!', 'success');
                if (onDone) onDone(res);
                else defaultAfterSave(res, data.status);
            } else {
                showToast('Error: ' + res.message, 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = btn.getAttribute('data-old'); }
            }
        })
        .catch(function () {
            showToast('Network error', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = btn.getAttribute('data-old'); }
        });
}

function defaultAfterSave(res, status) {
    if (status === 'confirmed') {
        if (confirm('Order #' + res.bill_no + ' saved!\n\nPrint receipt now?')) {
            window.open('receipt.php?bill=' + res.bill_no, '_blank');
        }
        setTimeout(function () { window.location.reload(); }, 1200);
    } else {
        setTimeout(function () { window.location.reload(); }, 900);
    }
}

/* ---------- tiny helpers ---------- */
function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
}
function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
function toggleCollapse(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    var open = el.classList.toggle('open');
    if (btn) btn.innerHTML = open ? '▴ Hide options' : '▾ More options';
}

/* ============================================================
   KEYBOARD CONTROL — work without the mouse
   F1 help · F2 search items · F4 customer · F9 confirm · F10 hold
   Esc close modal / clear search · Tab+Enter works on every tile
   ============================================================ */
function toggleHelp() {
    var m = document.getElementById('scHelpModal');
    if (!m) {
        m = document.createElement('div');
        m.id = 'scHelpModal';
        m.className = 'modal-overlay';
        m.innerHTML =
            '<div class="modal">' +
            '<h3>⌨ Keyboard Shortcuts</h3>' +
            '<table class="tbl"><tbody>' +
            '<tr><td><b>F1</b></td><td>Show / hide this help</td></tr>' +
            '<tr><td><b>F2</b></td><td>Focus item / product search</td></tr>' +
            '<tr><td><b>F4</b></td><td>Focus customer phone search</td></tr>' +
            '<tr><td><b>F9</b></td><td>Confirm &amp; save order</td></tr>' +
            '<tr><td><b>F10</b></td><td>Hold order</td></tr>' +
            '<tr><td><b>Tab / Shift+Tab</b></td><td>Move between fields &amp; tiles</td></tr>' +
            '<tr><td><b>Enter</b></td><td>Activate focused tile / button</td></tr>' +
            '<tr><td><b>Esc</b></td><td>Close popup, or clear the search box</td></tr>' +
            '</tbody></table>' +
            '<div class="modal-actions"><button class="btn btn-primary" onclick="toggleHelp()">Got it</button></div>' +
            '</div>';
        document.body.appendChild(m);
    }
    m.classList.toggle('show');
}

function initShortcuts(cfg) {
    cfg = cfg || {};
    document.addEventListener('keydown', function (e) {
        var tag = (e.target && e.target.tagName || '').toLowerCase();
        if (e.key === 'F1') { e.preventDefault(); toggleHelp(); return; }
        if (e.key === 'F2') {
            e.preventDefault();
            var s = cfg.searchId && document.getElementById(cfg.searchId);
            if (s) { s.focus(); if (s.select) s.select(); }
            return;
        }
        if (e.key === 'F4') { e.preventDefault(); var c = document.getElementById('custCell'); if (c) c.focus(); return; }
        if (e.key === 'F9') { e.preventDefault(); if (cfg.onConfirm) cfg.onConfirm(); return; }
        if (e.key === 'F10') { e.preventDefault(); if (cfg.onHold) cfg.onHold(); return; }
        if (e.key === 'Escape') {
            var open = document.querySelector('.modal-overlay.show');
            if (open) { open.classList.remove('show'); return; }
            if (cfg.searchId && e.target && e.target.id === cfg.searchId) {
                e.target.value = '';
                e.target.dispatchEvent(new Event('input'));
            }
        }
    });
    if (document.readyState === 'complete' || document.readyState === 'interactive') setupTabOrder();
    else document.addEventListener('DOMContentLoaded', setupTabOrder);
    window.addEventListener('load', setupTabOrder);
}

/* ---------- order type via combobox (space saver) ---------- */
function toggleAddrRow() {
    var a = document.getElementById('addrRow');
    if (a) a.style.display = (getOrderType() === 'delivery') ? '' : 'none';
    window.refreshTabOrder();
}

/* ---------- multi image set (primary + extras) ----------
   imageSet[0] = primary (saved in cake_order.image_data)
   the rest are sent as extra_images and stored per bill      */
var imageSet = [];

function imgAdd(dataUrl) { imageSet.push(dataUrl); imgRender(); showToast('📷 Photo added', 'success'); }
function imgSetPrimary(i) { var t = imageSet.splice(i, 1)[0]; imageSet.unshift(t); imgRender(); }
function imgRemove(i) { imageSet.splice(i, 1); imgRender(); }
function imgRender() {
    var prim = document.getElementById('imgPrimary');
    var strip = document.getElementById('imgStrip');
    if (prim) {
        prim.innerHTML = imageSet.length ? '<img src="' + imageSet[0] + '" alt="">' : (prim.getAttribute('data-empty') || '📷');
    }
    if (strip) {
        var h = '';
        for (var i = 0; i < imageSet.length; i++) {
            h += '<div class="thb' + (i === 0 ? ' on' : '') + '" title="' + (i === 0 ? 'Primary photo' : 'Click to make primary') + '" onclick="imgSetPrimary(' + i + ')">' +
                 '<img src="' + imageSet[i] + '" alt="">' +
                 (i > 0 ? '<button type="button" class="rm" onclick="event.stopPropagation();imgRemove(' + i + ')">✕</button>' : '') +
                 '</div>';
        }
        strip.innerHTML = h;
    }
    if (window.onImageSetChange) window.onImageSetChange();
}
function extraImages() { return imageSet.slice(1); }
function primaryImage() { return imageSet.length ? imageSet[0] : ''; }
