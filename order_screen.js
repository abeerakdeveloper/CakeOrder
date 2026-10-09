/* New order screen (index.php). Builds the order form, keeps the summary up to date and sends
   the same data to save_order.php and save_order_edit.php as before. Business rules stay on the server. */
(function () {
    'use strict';

    var CFG = window.ORDER_CONFIG || {};
    var EDIT = CFG.edit || null;
    var FLAVORS = CFG.flavors || [];
    var SHAPES = CFG.shapes || [];
    var UOMS = CFG.uoms || [];
    var CAKES = CFG.cakes || [];
    var TOP_SELLERS = CFG.topSellers || [];
    var PAST_NAMES = { lunch: CFG.lunchNames || [], sweet: CFG.sweetNames || [] };
    var PRESET_CHARGES = CFG.presetCharges || [];
    var HEADS = {
        cake: ['Create Cake Order', 'Select a category, flavour and other details to create a new cake order.'],
        lunch: ['Create Lunch Box Order', 'Build lunch box sets and quantities in a few steps.'],
        sweet: ['Create Sweet Box Order', 'Build sweet box sets. Sweet boxes are priced after weighing.'],
        eatable: ['Create Eatable Picture Order', 'Add each picture with its photo, size and quantity.'],
        other: ['Create Other Order', 'Add the items that are not cakes or boxes.']
    };
    var CARDS_TITLE = { cake: 'Cake details', eatable: 'Eatable pictures', other: 'Other items' };
    var HINTS = {
        cake: 'Choose a category above, or search for a cake. Each cake you add appears here.',
        eatable: 'Click "Add picture" to add an eatable picture with its photo.',
        other: 'Click "Add item" to add an item.'
    };
    var ICON = {
        x: '<svg class="ico" aria-hidden="true"><use href="#i-x"/></svg>',
        minus: '<svg class="ico" aria-hidden="true"><use href="#i-minus"/></svg>',
        plus: '<svg class="ico" aria-hidden="true"><use href="#i-plus"/></svg>',
        upload: '<svg class="ico" aria-hidden="true"><use href="#i-upload"/></svg>',
        mic: '<svg class="ico" aria-hidden="true"><use href="#i-mic"/></svg>'
    };

    var currentType = CFG.type || 'cake';
    var itemCounter = 0;
    var boxCounter = 0;
    var lastItemsTotal = 0;
    var currentImageItem = null;
    var currentAudioItem = null;
    var mediaRecorder = null;
    var audioChunks = [];
    var tempImageData = '';
    var selectedAdvanceMethod = '';
    var currentCustomerCell = '';
    var searchTimer = null;
    var boxSearchTimer = null;
    var boxSearchSeq = 0;
    var catalogTimer = null;
    var catalogSeq = 0;
    var catalogAll = null;
    var catalogLoading = false;
    var catalogError = false;
    var toastTimer = null;
    var activeCardId = '';
    var activeGroupId = '';

    // ===== HELPERS =====
    function $(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s === undefined || s === null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function money(n) { return Math.round(n).toLocaleString('en-US'); }
    function boxesText(n) { return n + (n === 1 ? ' box' : ' boxes'); }
    function isBoxType(t) { return t === 'lunch' || t === 'sweet'; }
    function removeEl(el) { if (el && el.parentNode) el.parentNode.removeChild(el); }
    function closestEl(el, sel) {
        while (el && el.nodeType === 1) {
            if (el.matches(sel)) return el;
            el = el.parentNode;
        }
        return null;
    }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function showToast(msg, type) {
        var toast = $('toast');
        toast.textContent = msg;
        toast.className = 'toast ' + (type || 'info') + ' show';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 3500);
    }
    function hideBox(box) { if (box) box.hidden = true; }
    function initialOf(name) {
        var s = String(name || '').trim();
        return s ? s.charAt(0).toUpperCase() : '';
    }
    // ===== ITEM SEARCH HELPERS =====
    // The product list from get_products.php. Anything else (an error page, a login page) counts as a failed search.
    function productsFrom(res) {
        if (!res || !Array.isArray(res.products)) throw new Error('no product list');
        return res.products;
    }
    // Inventory rows in the shape the suggestion lists use
    function inventoryItems(products) {
        var out = [];
        for (var i = 0; i < (products || []).length; i++) {
            var p = products[i];
            out.push({
                name: String(p.prod_name || ''),
                price: num(p.retail_price),
                uom: p.uom || '',
                barcode: (p.barcode === null || p.barcode === undefined) ? '' : String(p.barcode),
                src: 'inv'
            });
        }
        return out;
    }
    // Words typed in a search, in lower case. Every word must appear (so "choc cake" finds "Chocolate Cake").
    function searchWords(q) {
        return String(q || '').toLowerCase().split(/\s+/).filter(Boolean);
    }
    function matchesWords(text, words) {
        var t = String(text || '').toLowerCase();
        for (var i = 0; i < words.length; i++) if (t.indexOf(words[i]) === -1) return false;
        return true;
    }
    function hasName(items, name) {
        var lower = String(name || '').toLowerCase();
        for (var i = 0; i < items.length; i++) if (String(items[i].name).toLowerCase() === lower) return true;
        return false;
    }
    // Position of the item whose name or barcode is exactly the text typed (-1 if none)
    function exactIndex(items, q) {
        var text = String(q || '').trim();
        if (text === '') return -1;
        var lower = text.toLowerCase();
        for (var i = 0; i < items.length; i++) {
            if ((items[i].barcode !== '' && items[i].barcode === text) || String(items[i].name).toLowerCase() === lower) return i;
        }
        return -1;
    }

    function field(text, control, cls) {
        return '<label class="field' + (cls ? ' ' + cls : '') + '"><span class="lbl">' + esc(text) + '</span>' + control + '</label>';
    }
    function fieldDiv(text, control, cls) {
        return '<div class="field' + (cls ? ' ' + cls : '') + '"><span class="lbl">' + esc(text) + '</span>' + control + '</div>';
    }
    function selectHtml(cls, options, selected) {
        var h = '<select class="' + cls + '"><option value="">Choose</option>';
        for (var i = 0; i < options.length; i++) {
            h += '<option value="' + esc(options[i]) + '"' + (options[i] === selected ? ' selected' : '') + '>' + esc(options[i]) + '</option>';
        }
        return h + '</select>';
    }
    function qtyControlHtml(id, qty) {
        return '<div class="stepper qty-control">' +
            '<button type="button" data-action="qty" data-id="' + esc(id) + '" data-delta="-1" aria-label="Less">' + ICON.minus + '</button>' +
            '<input type="number" class="qty-input" min="1" step="1" value="' + esc(qty) + '" aria-label="Quantity">' +
            '<button type="button" data-action="qty" data-id="' + esc(id) + '" data-delta="1" aria-label="More">' + ICON.plus + '</button>' +
            '</div>';
    }
    function shapeChipsHtml(id, selected) {
        var h = '<input type="hidden" class="shape" value="' + esc(selected) + '"><div class="chips">';
        for (var i = 0; i < SHAPES.length; i++) {
            h += '<button type="button" class="chip' + (SHAPES[i] === selected ? ' is-active' : '') + '" data-action="shape" data-id="' + esc(id) + '" data-value="' + esc(SHAPES[i]) + '">' + esc(SHAPES[i]) + '</button>';
        }
        return h + '</div>';
    }
    function removeHeadHtml(id) {
        return '<button type="button" class="btn-text danger" data-action="remove-item" data-id="' + esc(id) + '">Remove</button>';
    }

    // ===== ORDER TYPE =====
    function hasAnyItems() {
        return $('cartItems').querySelectorAll('.item-card').length > 0 ||
               $('boxGroups').querySelectorAll('.box-group').length > 0;
    }
    function selectType(type) {
        if (EDIT || type === currentType) return;
        if (hasAnyItems()) {
            if (!confirm('Change order type?\n\nThe items you added will be removed.')) return;
            clearItems();
        }
        setType(type);
    }
    function applyTypeVisibility(type) {
        var nodes = document.querySelectorAll('[data-show]');
        for (var i = 0; i < nodes.length; i++) {
            var shown = nodes[i].getAttribute('data-show').split(' ');
            nodes[i].hidden = shown.indexOf(type) === -1;
        }
    }
    function setType(type) {
        currentType = type;
        document.body.setAttribute('data-type', type);
        var tabs = document.querySelectorAll('.type-tab');
        for (var i = 0; i < tabs.length; i++) {
            var on = tabs[i].getAttribute('data-type') === type;
            tabs[i].classList.toggle('is-active', on);
            tabs[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        if (!EDIT) {
            $('pageTitle').textContent = HEADS[type][0];
            $('pageSub').textContent = HEADS[type][1];
        }
        $('cardsTitle').textContent = CARDS_TITLE[type] || '';
        var sweet = type === 'sweet';
        $('boxTitle').textContent = sweet ? 'Sweet Box Sets' : 'Lunch Box Sets';
        $('setCountLabel').textContent = sweet ? 'Sweet Box Sets' : 'Lunch Box Sets';
        $('boxTotalLabel').textContent = sweet ? 'Total Sweet Boxes' : 'Total Lunch Boxes';
        $('addEatableBtn').textContent = type === 'cake' ? '+ Add eatable picture to this cake' : '+ Add picture';
        $('work').classList.toggle('has-catalog', isBoxType(type));
        $('itemsLabel').textContent = isBoxType(type) ? 'Boxes total' : 'Items total';
        applyTypeVisibility(type);
        if (isBoxType(type) && $('boxGroups').querySelectorAll('.box-group').length === 0) {
            addBoxGroup();
        }
        if (isBoxType(type)) renderDefaultItems();
        updateEmptyMessage();
        calcTotals();
        if (!EDIT && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', 'index.php?type=' + type);
        }
    }
    function clearItems() {
        $('cartItems').innerHTML = '';
        $('boxGroups').innerHTML = '';
        activeCardId = '';
        activeGroupId = '';
        showPreview(null);
        updateEmptyMessage();
        calcTotals();
    }
    function updateEmptyMessage() {
        var box = $('emptyMsg');
        var hasCards = $('cartItems').querySelectorAll('.item-card').length > 0;
        box.hidden = hasCards || isBoxType(currentType);
        box.textContent = HINTS[currentType] || '';
    }
    function newOrder() {
        if (hasAnyItems() && !confirm('Start a new order? The items you added will be removed.')) return;
        window.location.href = 'index.php?type=cake';
    }

    // ===== CAKE PICKER (cakes from Cake Products only) =====
    function cakeMatches(q) {
        var words = searchWords(q);
        var out = [];
        for (var i = 0; i < CAKES.length; i++) {
            var c = CAKES[i];
            if (matchesWords(String(c.name || '') + ' ' + (c.barcode ? String(c.barcode) : ''), words)) out.push(c);
        }
        return out;
    }
    function findCakeByBarcode(code) {
        for (var i = 0; i < CAKES.length; i++) {
            if (CAKES[i].barcode && String(CAKES[i].barcode) === code) return CAKES[i];
        }
        return null;
    }
    function findCakeById(id) {
        for (var i = 0; i < CAKES.length; i++) {
            if (String(CAKES[i].id) === String(id)) return CAKES[i];
        }
        return null;
    }
    function renderCakeSuggest() {
        var box = $('cakeSuggest');
        var q = $('cakeSearch').value.trim();
        box.innerHTML = '';
        if (!q) { hideBox(box); return; }
        var hits = cakeMatches(q).slice(0, 40);
        if (!hits.length) {
            box.innerHTML = '<div class="suggest-empty"></div>';
            box.firstChild.textContent = 'No cake matches "' + q + '". For other items, choose Lunch box, Sweet box or Other.';
            box.hidden = false;
            return;
        }
        for (var i = 0; i < hits.length; i++) {
            var c = hits[i];
            var el = document.createElement('div');
            el.className = 'suggest-item' + (i === 0 ? ' is-active' : '');
            el.setAttribute('data-id', String(c.id));
            el.innerHTML = '<span class="s-name"></span><span class="s-meta"></span>';
            el.querySelector('.s-name').textContent = c.name;
            el.querySelector('.s-meta').textContent = 'Rs. ' + money(c.price) + (c.uom ? ' / ' + c.uom : '');
            box.appendChild(el);
        }
        box.hidden = false;
    }
    function addCakeFromCatalog(p) {
        if (currentType !== 'cake') setType('cake');
        addCakeItem(p.id, p.name, p.price, p.uom, null);
    }
    function clearCakeSearch() {
        $('cakeSearch').value = '';
        hideBox($('cakeSuggest'));
    }
    function submitCakeSearch() {
        var input = $('cakeSearch');
        var q = input.value.trim();
        if (!q) return;
        var box = $('cakeSuggest');
        var found = findCakeByBarcode(q);
        if (!found && !box.hidden) {
            var active = box.querySelector('.suggest-item.is-active');
            if (active) { pickCakeSuggestion(active); return; }
        }
        if (!found) {
            var matches = cakeMatches(q);
            if (matches.length === 1) found = matches[0];
        }
        if (found) {
            addCakeFromCatalog(found);
            clearCakeSearch();
            input.focus();
            return;
        }
        showToast('No cake matches "' + q + '". For other items, choose Lunch box, Sweet box or Other.', 'error');
    }
    function pickCakeSuggestion(el) {
        var p = findCakeById(el.getAttribute('data-id'));
        if (p) addCakeFromCatalog(p);
        clearCakeSearch();
        $('cakeSearch').focus();
    }
    function renderTiles() {
        var wrap = $('tiles');
        wrap.innerHTML = '';
        var seen = {};
        var list = [];
        for (var t = 0; t < TOP_SELLERS.length; t++) {
            list.push({ id: TOP_SELLERS[t].id, name: TOP_SELLERS[t].name, price: TOP_SELLERS[t].price, uom: TOP_SELLERS[t].uom, top: true });
            seen[String(TOP_SELLERS[t].id)] = true;
        }
        for (var c = 0; c < CAKES.length && list.length < 150; c++) {
            if (seen[String(CAKES[c].id)]) continue;
            list.push({ id: CAKES[c].id, name: CAKES[c].name, price: CAKES[c].price, uom: CAKES[c].uom, top: false });
        }
        for (var i = 0; i < list.length; i++) {
            var it = list[i];
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tile';
            b.setAttribute('data-action', 'tile');
            b.setAttribute('data-id', String(it.id));
            b.setAttribute('data-name', String(it.name || ''));
            b.setAttribute('data-price', String(it.price || 0));
            b.setAttribute('data-uom', String(it.uom || ''));
            b.innerHTML = '<span class="t-name"></span><span class="t-meta"></span><span class="t-count" hidden></span>';
            b.querySelector('.t-name').textContent = it.name || '';
            b.querySelector('.t-meta').textContent = (it.top ? 'Top seller, ' : '') + 'Rs. ' + money(it.price || 0) + (it.uom ? ' / ' + it.uom : '');
            wrap.appendChild(b);
        }
    }
    function updateTileCounts(counts) {
        var tiles = $('tiles').querySelectorAll('.tile');
        for (var i = 0; i < tiles.length; i++) {
            var c = counts[tiles[i].getAttribute('data-id')] || 0;
            var badge = tiles[i].querySelector('.t-count');
            badge.textContent = String(c);
            badge.hidden = c === 0;
            tiles[i].classList.toggle('is-active', c > 0);
        }
    }

    // ===== CUSTOMER LOOKUP =====
    function searchCustomer(q) {
        clearTimeout(searchTimer);
        if (q.length < 2) { hideBox($('suggestBox')); return; }
        searchTimer = setTimeout(function () {
            getJson('customer_lookup.php?action=search&q=' + encodeURIComponent(q)).then(function (res) {
                renderCustomers((res && res.customers) || []);
            }).catch(function () { hideBox($('suggestBox')); });
        }, 300);
    }
    function tagEl(text, cls) {
        var s = document.createElement('span');
        s.className = 'tag ' + cls;
        s.textContent = text;
        return s;
    }
    function renderCustomers(list) {
        var box = $('suggestBox');
        box.innerHTML = '';
        if (!list.length) { hideBox(box); return; }
        for (var i = 0; i < list.length; i++) {
            var c = list[i];
            var el = document.createElement('div');
            el.className = 'suggest-item';
            el.setAttribute('data-cell', String(c.cell || ''));
            el.setAttribute('data-name', String(c.name || ''));
            el.innerHTML = '<span class="s-name"></span>';
            el.querySelector('.s-name').textContent = c.name || '';
            if (c.is_vip) el.querySelector('.s-name').appendChild(tagEl('VIP', 'vip'));
            if (c.is_loyal) el.querySelector('.s-name').appendChild(tagEl('Loyal', 'loyal'));
            var meta = document.createElement('span');
            meta.className = 's-meta';
            meta.textContent = (c.cell || '') + ' · ' + (c.orders || 0) + ' orders · Spent Rs. ' + Number(c.spent || 0).toLocaleString('en-US');
            el.appendChild(meta);
            box.appendChild(el);
        }
        box.hidden = false;
    }
    function selectCustomer(cell, name) {
        $('custCell').value = cell;
        $('custName').value = name;
        currentCustomerCell = cell;
        hideBox($('suggestBox'));
        showCustomerSummary(cell);
    }
    function addNewCustomer() {
        $('custCell').value = '';
        $('custName').value = 'Walk-in';
        currentCustomerCell = '';
        $('customerInfoCard').hidden = true;
        hideBox($('suggestBox'));
        $('custName').focus();
        $('custName').select();
    }
    function showCustomerSummary(cell) {
        getJson('customer_lookup.php?action=search&q=' + encodeURIComponent(cell)).then(function (res) {
            if (!res || !res.customers || res.customers.length === 0) return;
            var c = res.customers[0];
            var html = '<strong>' + esc(c.name) + '</strong> &mdash; ';
            html += esc(c.orders) + ' previous orders, total spent <strong>Rs. ' + Number(c.spent).toLocaleString('en-US') + '</strong>';
            if (c.fav_flavors) html += ' &middot; Favourite flavours: ' + esc(c.fav_flavors);
            if (c.is_vip) html += ' <span class="tag vip">VIP</span>';
            if (c.last_order) html += ' &middot; Last order: ' + esc(c.last_order);
            $('customerInfoText').innerHTML = html;
            $('customerInfoCard').hidden = false;
        }).catch(function () {});
    }
    function showCustomerHistory() {
        var cell = $('custCell').value.trim();
        if (!cell) { showToast('Enter customer phone first', 'error'); return; }
        getJson('customer_lookup.php?action=history&cell=' + encodeURIComponent(cell)).then(function (res) {
            var box = $('historyContent');
            box.innerHTML = '';
            var orders = (res && res.orders) || [];
            if (!orders.length) {
                box.innerHTML = '<p class="hint center">No previous orders</p>';
            }
            for (var i = 0; i < orders.length; i++) {
                var o = orders[i];
                var el = document.createElement('div');
                el.className = 'hist-item';
                // status_badge is HTML made by the server
                el.innerHTML = '<div class="hist-head"><strong>Order #' + esc(o.bill_no) + '</strong>' + (o.status_badge || '') + '</div>' +
                    '<div class="hint" style="margin-top:4px;">' + esc(o.date) + ' &middot; Rs. ' + esc(o.total) + '</div>' +
                    '<div class="hint">' + esc(o.items) + '</div>' +
                    (o.flavors ? '<div class="hint">Flavours: ' + esc(o.flavors) + '</div>' : '') +
                    '<div style="margin-top:8px;"><button type="button" class="btn btn-outline btn-sm" data-action="copy-order" data-bill="' + esc(o.bill_no) + '">Copy this order</button></div>';
                box.appendChild(el);
            }
            $('historyModal').classList.add('show');
        }).catch(function () { showToast('Could not load the order history.', 'error'); });
    }

    // Copy a previous order: its cakes, other items and box sets (eatable pictures and charges are not copied)
    function duplicateOrder(billNo) {
        getJson('customer_lookup.php?action=duplicate&bill_no=' + billNo).then(function (res) {
            if (hasAnyItems() && !confirm('Replace the current items with a copy of order #' + billNo + '?')) return;
            clearItems();
            setType((res && res.type) || 'cake');
            // setType adds one empty set for box types; the copy replaces it
            $('boxGroups').innerHTML = '';
            calcTotals();
            var skipped = 0;
            var items = (res && res.items) || [];
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                if (it.kind === 'cake') {
                    addCakeItem(it.inv_id, it.name, it.price, it.uom, it);
                } else if (it.kind === 'other') {
                    addOtherItem({ name: it.name, price: it.price, qty: it.qty, note: it.note });
                } else {
                    skipped++;
                }
            }
            var boxes = (res && res.boxes) || [];
            for (var b = 0; b < boxes.length; b++) addBoxGroup(boxes[b]);
            if (isBoxType(currentType) && $('boxGroups').querySelectorAll('.box-group').length === 0) addBoxGroup();
            $('historyModal').classList.remove('show');
            var msg = 'Copied from order #' + billNo;
            if (skipped) msg += '. Eatable pictures are not copied; add the picture again.';
            showToast(msg, 'success');
        }).catch(function () { showToast('Could not copy the order.', 'error'); });
    }

    // ===== DELIVERY (pickup or delivery) =====
    function syncDelivery() {
        var v = $('deliveryType').value;
        var buttons = document.querySelectorAll('[data-action="delivery"]');
        for (var i = 0; i < buttons.length; i++) {
            var on = buttons[i].getAttribute('data-value') === v;
            buttons[i].classList.toggle('is-active', on);
            buttons[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        $('deliveryAddrRow').hidden = v !== 'delivery';
    }
    function setDeliveryType(value) {
        $('deliveryType').value = value === 'delivery' ? 'delivery' : 'pickup';
        syncDelivery();
    }
    function toggleDeliveryAddress() { syncDelivery(); }

    // ===== CARDS: CAKES, EATABLE PICTURES, OTHER ITEMS =====
    function makeCard(kind) {
        itemCounter++;
        var card = document.createElement('div');
        card.className = 'item-card';
        card.id = 'item_' + itemCounter;
        card.dataset.kind = kind;
        card.dataset.invId = 0;
        card.dataset.name = '';
        card.dataset.price = 0;
        card.dataset.image = '';
        card.dataset.audio = '';
        card.dataset.rowId = 0;
        return card;
    }
    function cardPhotoSrc(card) {
        if (card.dataset.image) return card.dataset.image;
        var rowId = parseInt(card.dataset.rowId, 10) || 0;
        if (card.dataset.keepImage === '1' && rowId > 0) return 'show_image.php?type=thumb&id=' + rowId;
        return '';
    }
    function cardTitle(card) {
        if (card.dataset.kind === 'cake') return card.dataset.name || 'Cake';
        if (card.dataset.kind === 'eatable') return 'Eatable picture';
        var d = card.querySelector('.desc');
        return (d && d.value.trim()) || 'Other item';
    }
    function showPreview(card) {
        var box = $('previewBox');
        var src = card ? cardPhotoSrc(card) : '';
        box.innerHTML = '';
        if (src) {
            var img = document.createElement('img');
            img.alt = 'Photo of the item';
            img.src = src;
            box.appendChild(img);
        } else {
            var span = document.createElement('span');
            span.className = 'preview-empty';
            span.textContent = 'Upload a reference photo to see it here.';
            box.appendChild(span);
        }
        $('previewChangeBtn').hidden = !(card && src);
        $('previewCaption').textContent = card ? cardTitle(card) : '';
    }
    function setActiveCard(card) {
        if (!card) return;
        var all = $('cartItems').querySelectorAll('.item-card');
        for (var i = 0; i < all.length; i++) all[i].classList.toggle('is-active', all[i] === card);
        activeCardId = card.id;
        showPreview(card);
    }
    function refreshPhoto(card) {
        var slot = card.querySelector('.photo-slot');
        if (!slot) return;
        var src = cardPhotoSrc(card);
        if (src) {
            slot.innerHTML = '<div class="photo-thumb"><img alt="Photo" src="' + esc(src) + '">' +
                '<div><p class="hint">Photo added</p>' +
                '<button type="button" class="btn-text" data-action="photo" data-id="' + esc(card.id) + '">Change image</button></div></div>';
        } else if (card.dataset.kind === 'eatable') {
            slot.innerHTML = '<button type="button" class="btn-photo dropzone required" data-action="photo" data-id="' + esc(card.id) + '">' +
                '<span class="up">' + ICON.upload + '</span><strong>Add picture (required)</strong><small>Click to choose a JPG or PNG</small></button>';
        } else {
            slot.innerHTML = '<button type="button" class="btn-photo dropzone" data-action="photo" data-id="' + esc(card.id) + '">' +
                '<span class="up">' + ICON.upload + '</span><strong>Upload or choose a reference image</strong><small>Click to choose a JPG or PNG</small></button>';
        }
        if (card.id === activeCardId) showPreview(card);
    }
    function setCardImage(id, dataUrl) {
        var card = $(id);
        if (!card) return;
        card.dataset.image = dataUrl;
        refreshPhoto(card);
        setActiveCard(card);
    }
    function showSavedImage(cardId, rowId) {
        var card = $(cardId);
        if (!card) return;
        card.dataset.keepImage = '1';
        refreshPhoto(card);
    }
    function setVoiceButtons(card, hasVoice) {
        var rec = card.querySelector('.btn-voice');
        var play = card.querySelector('.btn-play-voice');
        if (rec) rec.lastChild.textContent = hasVoice ? 'Re-record voice' : 'Voice message';
        if (play) play.hidden = !hasVoice;
    }
    function showSavedAudio(cardId, rowId) {
        var card = $(cardId);
        if (!card) return;
        card.dataset.audioUrl = 'show_image.php?type=audio&id=' + rowId;
        setVoiceButtons(card, true);
    }

    function addCakeItem(invId, name, basePrice, uom, preset) {
        preset = preset || {};
        var card = makeCard('cake');
        card.dataset.invId = invId || 0;
        card.dataset.name = name || '';
        card.dataset.price = basePrice || 0;
        card.dataset.rowId = preset.id || 0;
        card.dataset.image = preset.image_data || '';
        var tiers = preset.tiers || 1;
        card.innerHTML =
            '<div class="item-head">' +
                '<div><h3 class="item-title">' + esc(name || 'Cake') + '</h3><p class="item-sub">Cake</p></div>' +
                removeHeadHtml(card.id) +
            '</div>' +
            '<div class="item-grid">' +
                field('Flavour', selectHtml('flavor', FLAVORS, preset.flavor || '')) +
                field('Size and unit', selectHtml('uom', UOMS, preset.uom || uom || '')) +
                field('Weight', '<input type="number" class="tiers" min="0" step="0.25" value="' + esc(tiers) + '">') +
                fieldDiv('Shape', shapeChipsHtml(card.id, preset.shape || ''), 'span-3') +
                fieldDiv('Quantity', qtyControlHtml(card.id, preset.qty || 1)) +
                field('Price per cake (Rs)', '<input type="number" class="price-edit" min="0" step="1" value="' + esc(num(basePrice) * tiers) + '">') +
                fieldDiv('Total (Rs)', '<div class="readout line-amount">Rs. 0</div>') +
                field('Cake message', '<input type="text" class="cake-msg" maxlength="200" placeholder="Written on the cake" value="' + esc(preset.cake_message || '') + '">', 'span-2') +
                field('Material (optional)', '<input type="text" class="material" maxlength="100" value="' + esc(preset.material || '') + '">') +
                field('Kitchen note', '<input type="text" class="note" placeholder="Decoration, colour, allergy" value="' + esc(preset.note || '') + '">', 'span-3') +
            '</div>' +
            '<div class="photo-slot"></div>' +
            '<div class="voice-row">' +
                '<button type="button" class="btn btn-outline btn-sm btn-voice" data-action="voice" data-id="' + esc(card.id) + '">' + ICON.mic + 'Voice message</button>' +
                '<button type="button" class="btn btn-outline btn-sm btn-play-voice" data-action="play-voice" data-id="' + esc(card.id) + '" hidden>Play voice message</button>' +
            '</div>';
        $('cartItems').appendChild(card);
        if (preset.image_data) setCardImage(card.id, preset.image_data);
        if (preset.has_image && preset.id) showSavedImage(card.id, preset.id);
        if (preset.has_audio && preset.id) showSavedAudio(card.id, preset.id);
        refreshPhoto(card);
        setActiveCard(card);
        updateEmptyMessage();
        calcTotals();
        return card.id;
    }

    // Eatable pictures: their own type, or an add-on to a cake
    function addEatableItem(preset) {
        if (currentType !== 'cake' && currentType !== 'eatable') setType('eatable');
        preset = preset || {};
        var card = makeCard('eatable');
        card.dataset.name = 'Eatable picture';
        card.dataset.price = num(preset.price);
        card.dataset.rowId = preset.id || 0;
        card.innerHTML =
            '<div class="item-head">' +
                '<div><h3 class="item-title">Eatable picture</h3><p class="item-sub">Add the picture and its size</p></div>' +
                removeHeadHtml(card.id) +
            '</div>' +
            '<div class="item-grid">' +
                field('Price (Rs)', '<input type="number" class="price-edit" min="0" step="1" value="' + esc(num(preset.price)) + '">') +
                field('Size (optional)', '<input type="text" class="size" placeholder="e.g. 8 x 10 inch" value="' + esc(preset.size || '') + '">') +
                fieldDiv('Quantity', qtyControlHtml(card.id, preset.qty || 1)) +
                fieldDiv('Total (Rs)', '<div class="readout line-amount">Rs. 0</div>') +
                field('Kitchen note', '<input type="text" class="note" value="' + esc(preset.note || '') + '">', 'span-3') +
            '</div>' +
            '<div class="photo-slot"></div>';
        $('cartItems').appendChild(card);
        if (preset.has_image && preset.id) showSavedImage(card.id, preset.id);
        refreshPhoto(card);
        setActiveCard(card);
        updateEmptyMessage();
        calcTotals();
        return card.id;
    }

    function addOtherItem(preset) {
        if (currentType !== 'other') setType('other');
        preset = preset || {};
        var card = makeCard('other');
        card.dataset.name = preset.name || '';
        card.dataset.price = num(preset.price);
        card.dataset.rowId = preset.id || 0;
        card.innerHTML =
            '<div class="item-head">' +
                '<div><h3 class="item-title">Other item</h3></div>' +
                removeHeadHtml(card.id) +
            '</div>' +
            '<div class="item-grid">' +
                fieldDiv('Description', '<div class="desc-wrap"><input type="text" class="desc" autocomplete="off" placeholder="Type a name to search items, or describe it" value="' + esc(preset.name || '') + '" aria-label="Description"><div class="suggest" data-kind="desc" hidden></div></div>', 'span-2') +
                field('Price (Rs)', '<input type="number" class="price-edit" min="0" step="1" value="' + esc(num(preset.price)) + '">') +
                fieldDiv('Quantity', qtyControlHtml(card.id, preset.qty || 1)) +
                fieldDiv('Total (Rs)', '<div class="readout line-amount">Rs. 0</div>') +
                field('Kitchen note', '<input type="text" class="note" value="' + esc(preset.note || '') + '">', 'span-3') +
            '</div>';
        $('cartItems').appendChild(card);
        setActiveCard(card);
        updateEmptyMessage();
        calcTotals();
        return card.id;
    }

    function addCakeFromShape(card, value) {
        var input = card.querySelector('.shape');
        if (input) input.value = value;
        var chips = card.querySelectorAll('.chip');
        for (var i = 0; i < chips.length; i++) chips[i].classList.toggle('is-active', chips[i].getAttribute('data-value') === value);
    }

    function unitMultiplier(item) {
        if (item.dataset.kind === 'cake') {
            var t = item.querySelector('.tiers');
            var w = t ? num(t.value) : 1;
            return w > 0 ? w : 0;
        }
        return 1;
    }
    function updatePrice(id, newPrice) {
        var item = $(id);
        if (!item) return;
        var m = unitMultiplier(item);
        var p = num(newPrice);
        item.dataset.price = m > 0 ? p / m : p;
        calcTotals();
    }
    function updateQtyDirect(id, newQty) {
        var card = $(id);
        if (!card) return;
        var q = parseInt(newQty, 10) || 1;
        if (q < 1) q = 1;
        card.querySelector('.qty-input').value = q;
        calcTotals();
    }
    function removeItem(id) {
        removeEl($(id));
        if (activeCardId === id) {
            activeCardId = '';
            var rest = $('cartItems').querySelectorAll('.item-card');
            if (rest.length) setActiveCard(rest[rest.length - 1]); else showPreview(null);
        }
        updateEmptyMessage();
        calcTotals();
    }
    function changeQty(id, delta) {
        var card = $(id);
        if (!card) return;
        var input = card.querySelector('.qty-input');
        var q = (parseInt(input.value, 10) || 1) + delta;
        if (q < 1) { removeItem(id); return; }
        input.value = q;
        calcTotals();
    }

    // ===== BOX SETS (lunch and sweet boxes) =====
    function addBoxGroup(preset) {
        preset = preset || {};
        boxCounter++;
        var gid = 'box_' + boxCounter;
        var lunch = currentType === 'lunch';
        var boxes = preset.boxes || 1;
        var div = document.createElement('div');
        div.className = 'set-card box-group' + (lunch ? ' price-col' : '');
        div.id = gid;
        div.innerHTML =
            '<div class="set-head">' +
                '<span class="set-badge box-no">1</span>' +
                '<div class="set-title"><h3 class="box-title">Set 1</h3><p class="box-sub"></p></div>' +
                '<div class="set-qty"><span class="lbl">Quantity</span>' +
                    '<div class="stepper">' +
                        '<button type="button" data-action="box-step" data-id="' + gid + '" data-delta="-1" aria-label="Fewer boxes">' + ICON.minus + '</button>' +
                        '<input type="number" class="box-count" min="1" step="1" value="' + esc(boxes) + '" aria-label="Number of boxes">' +
                        '<button type="button" data-action="box-step" data-id="' + gid + '" data-delta="1" aria-label="More boxes">' + ICON.plus + '</button>' +
                    '</div>' +
                    '<span class="unit-note">(' + (lunch ? 'lunch' : 'sweet') + ' boxes)</span>' +
                '</div>' +
                '<button type="button" class="btn-text danger" data-action="remove-group" data-id="' + gid + '">Remove set</button>' +
            '</div>' +
            '<p class="set-items-lbl">Items in this set</p>' +
            '<div class="box-cols"><span>Item (type to search)</span><span>Qty per box</span>' +
                (lunch ? '<span>Price each (Rs)</span>' : '') + '<span></span></div>' +
            '<div class="box-rows"></div>' +
            '<div class="box-foot">' +
                '<button type="button" class="btn btn-outline btn-sm" data-action="add-box-item" data-id="' + gid + '">' + ICON.plus + 'Add item</button>' +
                '<span class="box-summary"></span>' +
            '</div>';
        $('boxGroups').appendChild(div);
        activeGroupId = gid;
        var items = (preset.items && preset.items.length) ? preset.items : [{}];
        for (var i = 0; i < items.length; i++) addBoxItem(gid, items[i]);
        calcTotals();
        return gid;
    }

    function addBoxItem(gid, preset) {
        preset = preset || {};
        var group = $(gid);
        if (!group) return;
        var name = preset.name || '';
        var row = document.createElement('div');
        row.className = 'box-row';
        row.dataset.rowId = preset.id || 0;
        var priceHtml = currentType === 'lunch'
            ? '<input type="number" class="bi-price" min="0" step="1" placeholder="Rs" title="Price for one piece" value="' + (preset.price ? esc(preset.price) : '') + '" aria-label="Price each">'
            : '';
        row.innerHTML =
            '<div class="bi-wrap">' +
                '<span class="avatar-sm" aria-hidden="true">' + esc(initialOf(name)) + '</span>' +
                '<input type="text" class="bi-name" autocomplete="off" placeholder="Type an item name" value="' + esc(name) + '" aria-label="Item name">' +
                '<div class="suggest" data-kind="box" hidden></div>' +
            '</div>' +
            '<input type="number" class="bi-qty" min="1" step="1" title="Quantity in each box" value="' + esc(preset.qty || 1) + '" aria-label="Quantity in each box">' +
            priceHtml +
            '<button type="button" class="btn-x" data-action="remove-box-item" aria-label="Remove item">' + ICON.x + '</button>';
        group.querySelector('.box-rows').appendChild(row);
        calcTotals();
    }

    function removeBoxItem(btn) {
        removeEl(closestEl(btn, '.box-row'));
        calcTotals();
    }
    function removeBoxGroup(gid) {
        removeEl($(gid));
        if (activeGroupId === gid) activeGroupId = '';
        if ($('boxGroups').querySelectorAll('.box-group').length === 0) addBoxGroup();
        calcTotals();
    }
    function stepBoxes(gid, delta) {
        var group = $(gid);
        if (!group) return;
        var input = group.querySelector('.box-count');
        var v = (parseInt(input.value, 10) || 1) + delta;
        if (v < 1) v = 1;
        input.value = v;
        calcTotals();
    }
    function ensureActiveGroup() {
        if (activeGroupId && $(activeGroupId)) return activeGroupId;
        var groups = $('boxGroups').querySelectorAll('.box-group');
        if (groups.length) {
            activeGroupId = groups[groups.length - 1].id;
            return activeGroupId;
        }
        return addBoxGroup();
    }
    function boxGroupNumbers(group) {
        var boxes = Math.max(0, Math.floor(num(group.querySelector('.box-count').value)));
        var each = 0;
        var rows = group.querySelectorAll('.box-row');
        for (var i = 0; i < rows.length; i++) {
            var name = rows[i].querySelector('.bi-name').value.trim();
            if (!name) continue;
            var perBox = Math.max(1, num(rows[i].querySelector('.bi-qty').value) || 1);
            var priceEl = rows[i].querySelector('.bi-price');
            var price = (currentType === 'sweet' || !priceEl) ? 0 : num(priceEl.value);
            each += price * perBox;
        }
        return { boxes: boxes, each: each, total: each * boxes };
    }

    // Item name search on a box row, and on the Other item description.
    // Inventory matches come first, then names used before (sets only).
    function suggestBoxFor(input) {
        if (input.id === 'cakeSearch') return $('cakeSuggest');
        if (input.id === 'custCell') return $('suggestBox');
        if (input.classList.contains('desc')) {
            var wrap = closestEl(input, '.desc-wrap');
            return wrap ? wrap.querySelector('.suggest') : null;
        }
        var row = closestEl(input, '.box-row');
        return row ? row.querySelector('.suggest') : null;
    }
    // Runs the item search after a short pause while typing. With pickNow (Enter pressed before the list
    // was ready, for example a fast barcode scan), the highlighted match is picked as soon as results arrive.
    function scheduleBoxSearch(input, delay, pickNow) {
        clearTimeout(boxSearchTimer);
        var q = input.value.trim();
        var box = suggestBoxFor(input);
        if (!q) { boxSearchSeq++; hideBox(box); return; }
        var seq = ++boxSearchSeq;
        boxSearchTimer = setTimeout(function () {
            getJson('get_products.php?search=' + encodeURIComponent(q)).then(function (res) {
                if (seq !== boxSearchSeq) return;
                var el = renderBoxSuggest(input, inventoryItems(productsFrom(res)), q, false);
                if (pickNow && el) pickSuggestion(el);
            }).catch(function () {
                if (seq === boxSearchSeq) renderBoxSuggest(input, [], q, true);
            });
        }, delay);
    }
    // Shows the suggestions for the text in the field. Returns the highlighted item, if there is one.
    function renderBoxSuggest(input, inv, q, failed) {
        var box = suggestBoxFor(input);
        if (!box || input.value.trim() !== q) return null;
        var isDesc = input.classList.contains('desc');
        var words = searchWords(q);
        var list = inv.slice(0, 12);
        var past = isDesc ? [] : (PAST_NAMES[currentType] || []);
        var pastAdded = 0;
        for (var i = 0; i < past.length && pastAdded < 5; i++) {
            var nm = String(past[i] || '');
            if (nm === '' || !matchesWords(nm, words) || hasName(list, nm)) continue;
            list.push({ name: nm, price: 0, uom: '', barcode: '', src: 'past' });
            pastAdded++;
        }
        box.innerHTML = '';
        box.removeAttribute('data-q');
        if (!failed && !list.length && isDesc) { box.hidden = true; return null; }
        if (failed || !list.length) {
            box.innerHTML = '<div class="suggest-empty"></div>';
            box.firstChild.textContent = failed ? 'Could not search items. Try again.' : 'No matching item. You can type the name yourself.';
            box.hidden = false;
            return null;
        }
        // An exact name or barcode is highlighted first. Otherwise the first match is highlighted,
        // except in the free-text description, where Enter must not replace what the user typed.
        var exact = exactIndex(list, q);
        var active = exact >= 0 ? exact : (isDesc ? -1 : 0);
        var activeEl = null;
        for (i = 0; i < list.length; i++) {
            var it = list[i];
            var el = document.createElement('div');
            el.className = 'suggest-item' + (i === active ? ' is-active' : '');
            el.setAttribute('data-name', it.name);
            el.setAttribute('data-price', String(it.price));
            el.setAttribute('data-src', it.src);
            el.innerHTML = '<span class="s-name"></span><span class="s-meta"></span>';
            el.querySelector('.s-name').textContent = it.name;
            el.querySelector('.s-meta').textContent = it.src === 'inv'
                ? (it.price ? 'Rs. ' + money(it.price) : '') + (it.uom ? ' / ' + it.uom : '')
                : 'Used before';
            box.appendChild(el);
            if (i === active) activeEl = el;
        }
        box.setAttribute('data-q', q);
        box.hidden = false;
        return activeEl;
    }
    // Other item: the name and the inventory price fill in the item. The item is not linked to inventory.
    function pickDescSuggestion(el) {
        var card = closestEl(el, '.item-card');
        if (!card) return;
        card.querySelector('.desc').value = el.getAttribute('data-name');
        var price = num(el.getAttribute('data-price'));
        if (price > 0 && el.getAttribute('data-src') === 'inv') {
            var rounded = Math.round(price);
            card.querySelector('.price-edit').value = rounded;
            updatePrice(card.id, rounded);
        }
        hideBox(closestEl(el, '.suggest'));
        setActiveCard(card);
        calcTotals();
    }

    function pickBoxSuggestion(el) {
        var row = closestEl(el, '.box-row');
        if (!row) return;
        row.querySelector('.bi-name').value = el.getAttribute('data-name');
        var priceEl = row.querySelector('.bi-price');
        var price = num(el.getAttribute('data-price'));
        if (priceEl && el.getAttribute('data-src') === 'inv' && price > 0) priceEl.value = Math.round(price);
        hideBox(closestEl(el, '.suggest'));
        row.querySelector('.avatar-sm').textContent = initialOf(row.querySelector('.bi-name').value);
        row.querySelector('.bi-qty').focus();
        calcTotals();
    }

    // ===== ITEM CATALOGUE (lunch and sweet boxes) =====
    function renderCatalogRows(items, headText) {
        $('catalogHead').textContent = headText;
        var list = $('catalogList');
        list.innerHTML = '';
        if (!items.length) {
            list.innerHTML = '<p class="hint">No items found.</p>';
            return;
        }
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var row = document.createElement('div');
            row.className = 'catalog-row';
            row.innerHTML = '<span class="avatar-sm" aria-hidden="true"></span>' +
                '<div class="cat-main"><div class="cat-name"></div><div class="cat-meta"></div></div>' +
                '<button type="button" class="btn btn-outline btn-sm" data-action="catalog-add">Add</button>';
            row.querySelector('.avatar-sm').textContent = initialOf(it.name);
            row.querySelector('.cat-name').textContent = it.name;
            row.querySelector('.cat-meta').textContent = it.src === 'past'
                ? 'Used before'
                : (it.price ? 'Rs. ' + money(it.price) : '') + (it.uom ? ' / ' + it.uom : '');
            row.setAttribute('data-name', it.name);
            row.setAttribute('data-price', String(it.price || 0));
            row.setAttribute('data-src', it.src);
            list.appendChild(row);
        }
    }
    // A message in place of the list, for example when a search fails
    function renderCatalogMessage(headText, text) {
        $('catalogHead').textContent = headText;
        var list = $('catalogList');
        list.innerHTML = '';
        var p = document.createElement('p');
        p.className = 'hint';
        p.textContent = text;
        list.appendChild(p);
    }
    // The default list is the finished products (the same list the search uses), loaded once per page
    function loadCatalog() {
        if (catalogAll !== null || catalogLoading) return;
        catalogLoading = true;
        getJson('get_products.php').then(function (res) {
            catalogAll = inventoryItems(productsFrom(res));
            catalogError = false;
        }).catch(function () {
            catalogAll = [];
            catalogError = true;
        }).then(function () {
            catalogLoading = false;
            if (isBoxType(currentType) && !$('catalogSearch').value.trim()) renderDefaultItems();
        });
    }
    function renderDefaultItems() {
        if (catalogAll === null) { loadCatalog(); return; }
        if (catalogError) {
            renderCatalogMessage('All items', 'Could not load the item list. Refresh the page to try again.');
            return;
        }
        renderCatalogRows(catalogAll, 'All items');
    }
    // Inventory matches first, then names used before that are not in inventory
    function renderCatalogResults(q, inv) {
        var items = inv.slice(0, 40);
        var words = searchWords(q);
        var past = PAST_NAMES[currentType] || [];
        for (var i = 0; i < past.length && items.length < 40; i++) {
            var nm = String(past[i] || '');
            if (nm !== '' && matchesWords(nm, words) && !hasName(items, nm)) {
                items.push({ name: nm, price: 0, uom: '', barcode: '', src: 'past' });
            }
        }
        renderCatalogRows(items, 'Search results');
    }
    // Any text searches the inventory, from the first letter on
    function scheduleCatalogSearch() {
        clearTimeout(catalogTimer);
        var q = $('catalogSearch').value.trim();
        var seq = ++catalogSeq;
        if (!q) { renderDefaultItems(); return; }
        catalogTimer = setTimeout(function () {
            getJson('get_products.php?search=' + encodeURIComponent(q)).then(function (res) {
                if (seq !== catalogSeq) return;
                renderCatalogResults(q, inventoryItems(productsFrom(res)));
            }).catch(function () {
                if (seq === catalogSeq) renderCatalogMessage('Search results', 'Could not search items. Try again.');
            });
        }, 250);
    }
    // Enter adds the item whose name or barcode is exactly what was typed. A barcode scanner ends with Enter.
    function catalogEnter() {
        var q = $('catalogSearch').value.trim();
        if (!q) return;
        clearTimeout(catalogTimer);
        var seq = ++catalogSeq;
        getJson('get_products.php?search=' + encodeURIComponent(q)).then(function (res) {
            if (seq !== catalogSeq) return;
            var items = inventoryItems(productsFrom(res));
            var idx = exactIndex(items, q);
            if (idx >= 0) {
                addBoxItem(ensureActiveGroup(), { name: items[idx].name, price: items[idx].price });
                clearCatalogSearch();
                showToast('Added ' + items[idx].name + '.', 'success');
            } else if (items.length) {
                showToast('Click Add on the item you need.', 'info');
            } else {
                showToast('No item matches "' + q + '". Check the name or the barcode.', 'error');
            }
        }).catch(function () {
            if (seq === catalogSeq) showToast('Could not search items. Try again.', 'error');
        });
    }
    function clearCatalogSearch() {
        clearTimeout(catalogTimer);
        catalogSeq++;
        $('catalogSearch').value = '';
        renderDefaultItems();
    }

    function catalogAdd(row) {
        if (!row) return;
        var name = row.getAttribute('data-name') || '';
        var price = num(row.getAttribute('data-price'));
        if (row.getAttribute('data-src') === 'past' && currentType === 'lunch') {
            // A name used before has no price saved: look it up in the item list
            getJson('get_products.php?search=' + encodeURIComponent(name)).then(function (res) {
                var found = null;
                var products = (res && res.products) || [];
                for (var i = 0; i < products.length; i++) {
                    if (String(products[i].prod_name).toLowerCase() === name.toLowerCase()) { found = products[i]; break; }
                }
                addBoxItem(ensureActiveGroup(), { name: name, price: found ? num(found.retail_price) : 0 });
            }).catch(function () {
                addBoxItem(ensureActiveGroup(), { name: name, price: 0 });
            });
            return;
        }
        addBoxItem(ensureActiveGroup(), { name: name, price: price });
    }

    // ===== EXTRA CHARGES (order summary) =====
    function addCharge(preset) {
        preset = preset || {};
        var row = document.createElement('div');
        row.className = 'charge-row';
        row.dataset.rowId = preset.id || 0;
        row.innerHTML =
            '<input type="text" class="ch-label" list="chargeNames" placeholder="Name, e.g. Delivery" value="' + esc(preset.label || '') + '" aria-label="Charge name">' +
            '<input type="number" class="ch-amount" min="0" step="1" placeholder="Rs" value="' + (preset.amount ? esc(preset.amount) : '') + '" aria-label="Charge amount">' +
            '<button type="button" class="btn-x" data-action="remove-charge" aria-label="Remove charge">' + ICON.x + '</button>';
        $('chargeRows').appendChild(row);
        calcTotals();
    }
    function renderChargePresets() {
        var wrap = $('chargePresets');
        if (!wrap) return;
        wrap.innerHTML = '';
        for (var i = 0; i < PRESET_CHARGES.length; i++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'chip';
            b.setAttribute('data-action', 'charge-preset');
            b.setAttribute('data-label', PRESET_CHARGES[i]);
            b.textContent = '+ ' + PRESET_CHARGES[i];
            wrap.appendChild(b);
        }
    }
    // A preset adds its row once; pressing it again goes to the amount already there
    function addChargePreset(label) {
        var rows = $('chargeRows').querySelectorAll('.charge-row');
        var row = null;
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].querySelector('.ch-label').value.trim() === label) row = rows[i];
        }
        if (!row) {
            addCharge({ label: label });
            row = $('chargeRows').lastChild;
        }
        row.querySelector('.ch-amount').focus();
    }
    function resetCharges() {
        $('chargeRows').innerHTML = '';
        calcTotals();
    }
    function removeCharge(btn) {
        removeEl(closestEl(btn, '.charge-row'));
        calcTotals();
    }
    function collectCharges() {
        var out = [];
        var rows = $('chargeRows').querySelectorAll('.charge-row');
        for (var i = 0; i < rows.length; i++) {
            var amt = num(rows[i].querySelector('.ch-amount').value);
            if (amt > 0) {
                out.push({ label: rows[i].querySelector('.ch-label').value.trim(), amount: amt, id: parseInt(rows[i].dataset.rowId, 10) || 0 });
            }
        }
        return out;
    }

    // ===== SUMMARY =====
    function lineFor(item, unit, qty, amount) {
        var kind = item.dataset.kind;
        var name;
        var meta;
        if (kind === 'cake') {
            var t = item.querySelector('.tiers');
            var u = item.querySelector('.uom');
            name = item.dataset.name || 'Cake';
            meta = (t && t.value ? t.value + ' ' : '') + (u && u.value ? u.value + ' · ' : '') + qty + ' × Rs. ' + money(unit);
        } else if (kind === 'eatable') {
            var sz = item.querySelector('.size');
            name = 'Eatable picture' + (sz && sz.value.trim() ? ' (' + sz.value.trim() + ')' : '');
            meta = qty + ' × Rs. ' + money(unit);
        } else {
            name = cardTitle(item);
            meta = qty + ' × Rs. ' + money(unit);
        }
        return { name: name, meta: meta, amount: amount };
    }
    function renderLines(lines) {
        var wrap = $('orderLines');
        wrap.innerHTML = '';
        wrap.hidden = isBoxType(currentType) || lines.length === 0;
        for (var i = 0; i < lines.length; i++) {
            var row = document.createElement('div');
            row.className = 'line-row';
            row.innerHTML = '<div class="line-main"><div><div class="line-name"></div><div class="line-meta"></div></div></div><strong class="line-amt"></strong>';
            row.querySelector('.line-name').textContent = lines[i].name;
            row.querySelector('.line-meta').textContent = lines[i].meta;
            row.querySelector('.line-amt').textContent = 'Rs. ' + money(lines[i].amount);
            wrap.appendChild(row);
        }
    }
    function renderBreakdown(rows, waiting) {
        var wrap = $('setBreakdown');
        wrap.innerHTML = '';
        wrap.hidden = !isBoxType(currentType) || rows.length === 0;
        for (var i = 0; i < rows.length; i++) {
            var row = document.createElement('div');
            row.className = 'line-row';
            row.innerHTML = '<div class="line-main"><span class="set-badge sm"></span><div><div class="line-name"></div><div class="line-meta"></div></div></div><strong class="line-amt"></strong>';
            row.querySelector('.set-badge').textContent = String(rows[i].no);
            row.querySelector('.line-name').textContent = 'Set ' + rows[i].no;
            row.querySelector('.line-meta').textContent = boxesText(rows[i].boxes);
            row.querySelector('.line-amt').textContent = waiting ? 'After weighing' : 'Rs. ' + money(rows[i].total);
            wrap.appendChild(row);
        }
    }

    // ===== CALCULATIONS =====
    function calcTotals() {
        // Sweet boxes are waiting for weighing until a weighed amount is saved (edit shows it)
        var weighed = (EDIT && EDIT.weighed) ? num(EDIT.weighed) : 0;
        var waitingWeight = currentType === 'sweet' && weighed <= 0;

        // Cake, eatable and other cards
        var itemsTotal = 0;
        var lines = [];
        var counts = {};
        var cards = $('cartItems').querySelectorAll('.item-card');
        for (var i = 0; i < cards.length; i++) {
            var item = cards[i];
            var unit = num(item.dataset.price) * unitMultiplier(item);
            var pe = item.querySelector('.price-edit');
            if (pe && document.activeElement !== pe) pe.value = Math.round(unit * 100) / 100;
            var qtyEl = item.querySelector('.qty-input');
            var qty = qtyEl ? (parseInt(qtyEl.value, 10) || 1) : 1;
            var amount = unit * qty;
            itemsTotal += amount;
            var amtEl = item.querySelector('.line-amount');
            if (amtEl) amtEl.textContent = 'Rs. ' + money(amount);
            if (item.dataset.kind === 'cake') {
                var key = String(item.dataset.invId);
                counts[key] = (counts[key] || 0) + 1;
            }
            lines.push(lineFor(item, unit, qty, amount));
        }

        // Box sets: lunch boxes are priced; sweet boxes are priced after weighing (no price entered)
        var boxCount = 0;
        var boxTotal = 0;
        var groups = $('boxGroups').querySelectorAll('.box-group');
        var breakdown = [];
        for (var g = 0; g < groups.length; g++) {
            var t2 = boxGroupNumbers(groups[g]);
            boxCount += t2.boxes;
            boxTotal += t2.total;
            groups[g].querySelector('.box-title').textContent = 'Set ' + (g + 1);
            groups[g].querySelector('.box-no').textContent = String(g + 1);
            groups[g].querySelector('.box-sub').textContent = boxesText(t2.boxes);
            groups[g].querySelector('.box-summary').textContent = waitingWeight
                ? 'Priced after weighing.'
                : 'Each box Rs. ' + money(t2.each) + '. Group total Rs. ' + money(t2.total) + '.';
            breakdown.push({ no: g + 1, boxes: t2.boxes, total: t2.total });
        }
        itemsTotal += boxTotal + weighed;
        lastItemsTotal = itemsTotal;
        $('setCount').textContent = String(groups.length);
        $('boxTotalCount').textContent = String(boxCount);
        renderBreakdown(breakdown, waitingWeight);
        renderLines(lines);
        updateTileCounts(counts);

        // Extra charges
        var charges = 0;
        var chargeRows = $('chargeRows').querySelectorAll('.charge-row');
        for (var c = 0; c < chargeRows.length; c++) {
            var amt = num(chargeRows[c].querySelector('.ch-amount').value);
            if (amt > 0) charges += amt;
        }

        var discount = parseInt($('flatDisc').value, 10) || 0;
        var advance = parseInt($('advance').value, 10) || 0;
        var total = Math.max(0, itemsTotal + charges - discount);
        var balance = total - advance;
        var isSweet = waitingWeight;

        $('subtotal').textContent = isSweet ? 'After weighing' : 'Rs. ' + money(itemsTotal);
        $('chargesDisplay').textContent = 'Rs. ' + money(charges);
        $('discountDisplay').textContent = 'Rs. ' + money(discount);
        if (isSweet) {
            $('total').textContent = total > 0 ? 'Rs. ' + money(total) + ' + weighing' : 'After weighing';
            $('balanceDisplay').textContent = 'After weighing';
        } else {
            $('total').textContent = 'Rs. ' + money(total);
            $('balanceDisplay').textContent = 'Rs. ' + money(Math.max(0, balance));
        }
        $('advanceDisplay').textContent = 'Rs. ' + money(advance);
    }

    function applyDiscPercent() {
        var pct = num($('discPercent').value);
        $('flatDisc').value = Math.round(lastItemsTotal * pct / 100);
        calcTotals();
    }

    function toggleAdvanceMethod() {
        var adv = parseInt($('advance').value, 10) || 0;
        $('advanceSection').hidden = EDIT ? true : !(adv > 0);
        calcTotals();
    }

    function setAdvanceMethod(method, btn) {
        selectedAdvanceMethod = method;
        var chips = $('advMethods').querySelectorAll('[data-method]');
        for (var i = 0; i < chips.length; i++) {
            chips[i].classList.toggle('is-active', chips[i] === btn);
        }
    }
    function resetAdvanceChips() {
        selectedAdvanceMethod = '';
        var chips = $('advMethods').querySelectorAll('[data-method]');
        for (var i = 0; i < chips.length; i++) chips[i].classList.remove('is-active');
    }

    function clearAll() {
        if (!confirm('Clear all items, charges and discounts?')) return;
        clearItems();
        resetCharges();
        if (!EDIT) $('advance').value = 0;
        $('flatDisc').value = 0;
        $('discPercent').value = 0;
        resetAdvanceChips();
        toggleAdvanceMethod();
    }

    // ===== IMAGE / AUDIO =====
    function openImageModal(itemId) {
        currentImageItem = itemId;
        $('imageModal').classList.add('show');
        $('imgPreview').hidden = true;
        $('imageFile').value = '';
        tempImageData = '';
    }
    function closeImageModal() {
        $('imageModal').classList.remove('show');
        currentImageItem = null;
    }
    function previewImage(input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        var allowedExts = /\.(jpg|jpeg|png)$/i;
        if (allowedTypes.indexOf(file.type) === -1 && !allowedExts.test(file.name)) {
            showToast('Only JPG or PNG images are allowed.', 'error');
            input.value = '';
            return;
        }
        var maxFileSizeMB = 20;
        if (file.size > maxFileSizeMB * 1024 * 1024) {
            showToast('Image too large. Maximum ' + maxFileSizeMB + ' MB.', 'error');
            input.value = '';
            return;
        }
        showToast('Processing image...', 'info');
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                var canvas = document.createElement('canvas');
                var maxSize = 1024;
                var w = img.width, h = img.height;
                if (w > h) {
                    if (w > maxSize) { h = Math.round(h * maxSize / w); w = maxSize; }
                } else {
                    if (h > maxSize) { w = Math.round(w * maxSize / h); h = maxSize; }
                }
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                tempImageData = canvas.toDataURL('image/jpeg', 0.75);
                $('imgPreview').src = tempImageData;
                $('imgPreview').hidden = false;
                showToast('Photo ready (' + w + ' x ' + h + ' px)', 'success');
            };
            img.onerror = function () {
                showToast('Invalid image file', 'error');
                input.value = '';
            };
            img.src = e.target.result;
        };
        reader.onerror = function () { showToast('Could not read the file', 'error'); };
        reader.readAsDataURL(file);
    }
    function saveImage() {
        if (!tempImageData || !currentImageItem) { closeImageModal(); return; }
        setCardImage(currentImageItem, tempImageData);
        showToast('Photo attached', 'success');
        closeImageModal();
    }

    function openAudioModal(itemId) {
        currentAudioItem = itemId;
        $('audioModal').classList.add('show');
        $('audioPlayback').hidden = true;
        $('recordStatus').textContent = 'Click to record';
        audioChunks = [];
    }
    function closeAudioModal() {
        $('audioModal').classList.remove('show');
        if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
        currentAudioItem = null;
    }
    function toggleRecord() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            $('recordBtn').textContent = 'Record';
            $('recordStatus').textContent = 'Recording saved';
            return;
        }
        if (!navigator.mediaDevices) { showToast('Microphone not supported', 'error'); return; }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = function (e) { audioChunks.push(e.data); };
            mediaRecorder.onstop = function () {
                var blob = new Blob(audioChunks, { type: 'audio/webm' });
                var audio = $('audioPlayback');
                audio.src = URL.createObjectURL(blob);
                audio.hidden = false;
                var reader = new FileReader();
                reader.onload = function () {
                    if (currentAudioItem && $(currentAudioItem)) $(currentAudioItem).dataset.audio = reader.result;
                };
                reader.readAsDataURL(blob);
                stream.getTracks().forEach(function (t) { t.stop(); });
            };
            mediaRecorder.start();
            $('recordBtn').textContent = 'Stop';
            $('recordStatus').textContent = 'Recording...';
        }).catch(function () { showToast('Microphone access was denied', 'error'); });
    }
    function saveAudio() {
        if (currentAudioItem) {
            var card = $(currentAudioItem);
            if (card && card.dataset.audio) {
                setVoiceButtons(card, true);
                showToast('Voice message saved', 'success');
            }
        }
        closeAudioModal();
    }
    function playAudio(itemId) {
        var el = $(itemId);
        if (!el) return;
        var src = el.dataset.audio || el.dataset.audioUrl;
        if (src) new Audio(src).play();
    }

    // ===== COLLECT ORDER DATA (same fields as before) =====
    function collectItems() {
        var list = [];
        var cards = $('cartItems').querySelectorAll('.item-card');
        for (var i = 0; i < cards.length; i++) {
            var item = cards[i];
            var kind = item.dataset.kind;
            var entry = {
                kind: kind,
                inv_id: parseInt(item.dataset.invId, 10) || 0,
                name: '',
                price: num(item.querySelector('.price-edit').value),
                qty: parseInt(item.querySelector('.qty-input').value, 10) || 1,
                note: item.querySelector('.note') ? item.querySelector('.note').value.trim() : '',
                image_data: item.dataset.image || '',
                id: parseInt(item.dataset.rowId, 10) || 0,
                keep_image: item.dataset.keepImage === '1' && !item.dataset.image,
                keep_audio: !!item.dataset.audioUrl && !item.dataset.audio
            };
            if (kind === 'cake') {
                entry.name = item.dataset.name;
                entry.flavor = item.querySelector('.flavor').value;
                entry.shape = item.querySelector('.shape').value;
                entry.uom = item.querySelector('.uom').value;
                // no default here: a weight of 0 or empty must be refused by the check below
                entry.tiers = num(item.querySelector('.tiers').value);
                entry.material = item.querySelector('.material').value.trim();
                entry.cake_message = item.querySelector('.cake-msg').value.trim();
                entry.audio_data = item.dataset.audio || '';
            } else if (kind === 'eatable') {
                entry.name = 'Eatable picture';
                entry.size = item.querySelector('.size').value.trim();
            } else {
                entry.name = item.querySelector('.desc').value.trim();
            }
            list.push(entry);
        }
        return list;
    }

    function collectBoxes() {
        var out = [];
        var groups = $('boxGroups').querySelectorAll('.box-group');
        for (var g = 0; g < groups.length; g++) {
            var items = [];
            var rows = groups[g].querySelectorAll('.box-row');
            for (var r = 0; r < rows.length; r++) {
                var name = rows[r].querySelector('.bi-name').value.trim();
                if (!name) continue;
                var priceEl = rows[r].querySelector('.bi-price');
                items.push({
                    name: name,
                    qty: Math.max(1, parseInt(rows[r].querySelector('.bi-qty').value, 10) || 1),
                    price: (currentType === 'sweet' || !priceEl) ? 0 : num(priceEl.value),
                    id: parseInt(rows[r].dataset.rowId, 10) || 0
                });
            }
            out.push({
                boxes: Math.floor(num(groups[g].querySelector('.box-count').value)),
                items: items
            });
        }
        return out;
    }

    // ===== SAVE ORDER =====
    function resetAfterSave() {
        clearItems();
        resetCharges();
        $('advance').value = 0;
        $('flatDisc').value = 0;
        $('discPercent').value = 0;
        resetAdvanceChips();
        toggleAdvanceMethod();
    }

    // Checks the order form. Returns the order data, or null after a message is shown.
    function collectForSave() {
        var type = currentType;
        var items = collectItems();
        var boxes = isBoxType(type) ? collectBoxes() : [];
        var charges = collectCharges();

        if (!isBoxType(type) && items.length === 0) { showToast('Add an item first.', 'error'); return null; }
        if (isBoxType(type) && boxes.length === 0) { showToast('Add a box group first.', 'error'); return null; }
        for (var i = 0; i < items.length; i++) {
            if (items[i].kind === 'cake' && (!items[i].name || items[i].tiers <= 0)) {
                showToast('Enter the weight for each cake.', 'error');
                return null;
            }
            if (items[i].kind === 'eatable' && !items[i].image_data && !items[i].keep_image) {
                showToast('Add the picture for each eatable picture.', 'error');
                return null;
            }
            if (items[i].kind === 'other' && !items[i].name) {
                showToast('Describe each other item.', 'error');
                return null;
            }
        }
        for (var b = 0; b < boxes.length; b++) {
            if (boxes[b].boxes < 1) { showToast('Set ' + (b + 1) + ': enter how many boxes.', 'error'); return null; }
            if (boxes[b].items.length === 0) { showToast('Set ' + (b + 1) + ': add at least one item.', 'error'); return null; }
        }

        var deliveryType = $('deliveryType').value;
        var deliveryAddress = $('deliveryAddress').value.trim();
        if (deliveryType === 'delivery' && !deliveryAddress) { showToast('Enter the delivery address.', 'error'); return null; }

        return {
            type: type,
            items: items,
            boxes: boxes,
            charges: charges,
            header: {
                party_detail: $('custName').value.trim() || 'Walk-in',
                cell_no: $('custCell').value.trim(),
                deliver_date: $('deliverDate').value,
                delivery_time: $('deliverTime').value,
                priority: $('priority').value,
                flat_disc: parseInt($('flatDisc').value, 10) || 0,
                occasion: $('occasion').value,
                delivery_type: deliveryType,
                delivery_address: deliveryAddress,
                delivery_branch: $('deliveryBranch').value.trim(),
                source: $('orderSource').value
            }
        };
    }

    function setSaving(on) {
        var btn = $('confirmBtn');
        btn.disabled = on;
        btn.textContent = on ? 'Saving...' : (EDIT ? 'Save changes (F9)' : 'Confirm order (F9)');
        if ($('holdBtn')) $('holdBtn').disabled = on;
    }

    function saveOrder(status) {
        if (EDIT) { saveEdit(); return; }
        var c = collectForSave();
        if (!c) return;
        var advance = parseInt($('advance').value, 10) || 0;
        if (advance > 0 && !selectedAdvanceMethod) { showToast('Select the advance payment method.', 'error'); return; }

        var data = c.header;
        data.type = c.type;
        data.items = c.items;
        data.boxes = c.boxes;
        data.charges = c.charges;
        data.status = status;
        data.advance = advance;
        data.advance_method = selectedAdvanceMethod;

        showToast('Saving order...', 'info');
        setSaving(true);
        fetch('save_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            setSaving(false);
            if (res.success) {
                showToast('Order #' + res.bill_no + ' saved', 'success');
                if (status === 'confirmed') {
                    if (confirm('Order #' + res.bill_no + ' saved.\n\nPrint the invoice now?')) {
                        window.open('invoice.php?bill=' + res.bill_no, '_blank');
                    }
                    setTimeout(function () { window.location.href = 'index.php?type=' + c.type; }, 800);
                } else {
                    resetAfterSave();
                }
            } else {
                showToast(res.message || 'Could not save the order.', 'error');
            }
        })
        .catch(function () {
            setSaving(false);
            showToast('Network error. The order was not saved.', 'error');
        });
    }

    // Edit mode: the whole order is sent; the server checks the status again and records the changes
    function saveEdit() {
        var c = collectForSave();
        if (!c) return;
        var payload = c.header;
        payload.bill_no = EDIT.bill_no;
        payload.type = c.type;
        payload.items = c.items;
        payload.boxes = c.boxes;
        payload.charges = c.charges;

        showToast('Saving changes...', 'info');
        setSaving(true);
        fetch('save_order_edit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            setSaving(false);
            if (res.success) {
                showToast(res.message, 'success');
                if (res.changed) {
                    setTimeout(function () { window.location.href = 'order_detail.php?bill=' + EDIT.bill_no; }, 1000);
                }
            } else {
                showToast(res.message || 'Could not save the changes.', 'error');
            }
        })
        .catch(function () {
            setSaving(false);
            showToast('Network error. The changes were not saved.', 'error');
        });
    }

    // Edit mode: the saved order is shown on this screen
    function selectValue(id, value) {
        var sel = $(id);
        value = value || '';
        var found = false;
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === value) found = true;
        }
        if (!found && value !== '') sel.appendChild(new Option(value, value));
        sel.value = value;
    }

    function applyEdit(E) {
        var h = E.header;
        $('custCell').value = h.cell_no;
        $('custName').value = h.party_detail;
        $('deliverDate').value = h.deliver_date;
        $('deliverTime').value = h.delivery_time;
        $('deliveryType').value = h.delivery_type;
        syncDelivery();
        $('deliveryAddress').value = h.delivery_address;
        $('deliveryBranch').value = h.delivery_branch;
        selectValue('priority', h.priority);
        selectValue('occasion', h.occasion);
        selectValue('orderSource', h.source);
        $('flatDisc').value = h.flat_disc;
        $('discPercent').value = 0;
        $('advance').value = h.advance;

        // The order type of a saved order does not change
        var tabs = document.querySelectorAll('.type-tab');
        for (var t = 0; t < tabs.length; t++) tabs[t].disabled = true;
        setType(E.type);
        clearItems();

        for (var i = 0; i < E.items.length; i++) {
            var it = E.items[i];
            if (it.kind === 'cake') {
                addCakeItem(it.inv_id, it.name, it.price, it.uom, it);
            } else if (it.kind === 'eatable') {
                addEatableItem(it);
            } else {
                addOtherItem(it);
            }
        }
        // The first cake is shown in the preview when the order opens
        var firstCard = $('cartItems').querySelector('.item-card');
        if (firstCard) setActiveCard(firstCard);
        for (var b = 0; b < E.boxes.length; b++) addBoxGroup(E.boxes[b]);
        for (var c = 0; c < E.charges.length; c++) addCharge(E.charges[c]);

        if (E.weighed !== null && E.weighed !== undefined) {
            $('weighedNote').textContent = 'Sweet boxes weighed: Rs. ' + money(E.weighed) + '. If the boxes changed, weigh them again on the Payment page.';
            $('weighedNote').hidden = false;
        }
        calcTotals();
    }

    // ===== SUGGESTION KEYBOARD AND MOUSE =====
    function moveActive(box, dir) {
        if (!box || box.hidden) return;
        var items = box.querySelectorAll('.suggest-item');
        if (!items.length) return;
        var cur = -1;
        for (var i = 0; i < items.length; i++) if (items[i].classList.contains('is-active')) cur = i;
        var next = cur + dir;
        if (next < 0) next = items.length - 1;
        if (next >= items.length) next = 0;
        for (var j = 0; j < items.length; j++) items[j].classList.toggle('is-active', j === next);
    }
    function pickSuggestion(el) {
        var parentId = el.parentNode.id;
        var kind = el.parentNode.getAttribute('data-kind');
        if (parentId === 'cakeSuggest') pickCakeSuggestion(el);
        else if (parentId === 'suggestBox') selectCustomer(el.getAttribute('data-cell'), el.getAttribute('data-name'));
        else if (kind === 'box') pickBoxSuggestion(el);
        else if (kind === 'desc') pickDescSuggestion(el);
    }

    // ===== EVENTS =====
    document.addEventListener('click', function (e) {
        var btn = closestEl(e.target, '[data-action]');
        if (!btn) {
            var plain = closestEl(e.target, '.item-card');
            if (plain) setActiveCard(plain);
            return;
        }
        var action = btn.getAttribute('data-action');
        var id = btn.getAttribute('data-id');
        if (action === 'type') selectType(btn.getAttribute('data-type'));
        else if (action === 'charge-preset') addChargePreset(btn.getAttribute('data-label'));
        else if (action === 'delivery') setDeliveryType(btn.getAttribute('data-value'));
        else if (action === 'tile') {
            addCakeFromCatalog({
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                price: num(btn.getAttribute('data-price')),
                uom: btn.getAttribute('data-uom')
            });
        }
        else if (action === 'remove-item') removeItem(id);
        else if (action === 'photo') { setActiveCard($(id)); openImageModal(id); }
        else if (action === 'voice') openAudioModal(id);
        else if (action === 'play-voice') playAudio(id);
        else if (action === 'qty') changeQty(id, parseInt(btn.getAttribute('data-delta'), 10));
        else if (action === 'shape') addCakeFromShape($(id), btn.getAttribute('data-value'));
        else if (action === 'remove-group') removeBoxGroup(id);
        else if (action === 'box-step') stepBoxes(id, parseInt(btn.getAttribute('data-delta'), 10));
        else if (action === 'add-box-item') { activeGroupId = id; addBoxItem(id); }
        else if (action === 'remove-box-item') removeBoxItem(btn);
        else if (action === 'catalog-add') catalogAdd(closestEl(btn, '.catalog-row'));
        else if (action === 'remove-charge') removeCharge(btn);
        else if (action === 'copy-order') duplicateOrder(parseInt(btn.getAttribute('data-bill'), 10));
        else if (action === 'method') setAdvanceMethod(btn.getAttribute('data-method'), btn);
    });

    // Suggestions pick on mousedown so the input keeps focus and blur does not hide the list first
    document.addEventListener('mousedown', function (e) {
        var item = closestEl(e.target, '.suggest-item');
        if (!item) return;
        e.preventDefault();
        pickSuggestion(item);
    });

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t.id === 'cakeSearch') { renderCakeSuggest(); return; }
        if (t.id === 'custCell') { searchCustomer(t.value.trim()); return; }
        if (t.id === 'catalogSearch') { scheduleCatalogSearch(); return; }
        if (t.id === 'advance') { toggleAdvanceMethod(); return; }
        if (t.id === 'discPercent') { applyDiscPercent(); return; }
        if (t.classList.contains('bi-name')) {
            var av = closestEl(t, '.bi-wrap');
            if (av) av.querySelector('.avatar-sm').textContent = initialOf(t.value);
            scheduleBoxSearch(t, 250);
            calcTotals();
            return;
        }
        if (t.classList.contains('desc')) {
            scheduleBoxSearch(t, 250);
            var dc = closestEl(t, '.item-card');
            if (dc && dc.id === activeCardId) $('previewCaption').textContent = cardTitle(dc);
            return;
        }
        if (t.classList.contains('price-edit')) {
            var card = closestEl(t, '.item-card');
            if (card) updatePrice(card.id, t.value);
            return;
        }
        if (closestEl(t, '#cartItems, #boxGroups, #chargeRows, #summaryPanel')) calcTotals();
    });

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t.classList.contains('qty-input')) {
            var card = closestEl(t, '.item-card');
            if (card) updateQtyDirect(card.id, t.value);
            return;
        }
        if (t.classList.contains('price-edit')) {
            var item = closestEl(t, '.item-card');
            if (item) updatePrice(item.id, t.value);
        }
    });

    document.addEventListener('focusin', function (e) {
        var card = closestEl(e.target, '.item-card');
        if (card) setActiveCard(card);
        var group = closestEl(e.target, '.box-group');
        if (group) activeGroupId = group.id;
        if (e.target.id === 'cakeSearch' && e.target.value.trim() !== '') renderCakeSuggest();
    });

    document.addEventListener('blur', function (e) {
        var t = e.target;
        if (t.id === 'cakeSearch') {
            setTimeout(function () { hideBox($('cakeSuggest')); }, 150);
        } else if (t.id === 'custCell') {
            setTimeout(function () { hideBox($('suggestBox')); }, 200);
        } else if (t.classList && t.classList.contains('bi-name')) {
            var box = suggestBoxFor(t);
            setTimeout(function () { hideBox(box); }, 150);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        var t = e.target;
        if (t && t.tagName === 'INPUT' && t.id === 'catalogSearch') {
            if (e.key === 'Enter') { e.preventDefault(); catalogEnter(); return; }
            if (e.key === 'Escape') { clearCatalogSearch(); return; }
        }
        var isSearch = t && t.tagName === 'INPUT' && (t.id === 'cakeSearch' || t.id === 'custCell' || t.classList.contains('bi-name') || t.classList.contains('desc'));
        if (isSearch) {
            var box = suggestBoxFor(t);
            if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(box, 1); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(box, -1); return; }
            if (e.key === 'Escape') {
                hideBox(box);
                if (t.id === 'cakeSearch') t.value = '';
                return;
            }
            if (e.key === 'Enter') {
                if (t.id === 'cakeSearch') { e.preventDefault(); submitCakeSearch(); return; }
                if (t.id === 'custCell') {
                    var customer = (box && !box.hidden) ? box.querySelector('.suggest-item.is-active') : null;
                    if (customer) { e.preventDefault(); pickSuggestion(customer); }
                    return;
                }
                // Item name (box row or Other description): pick the highlighted item. If the list is not
                // ready yet (a fast barcode scan), search now and pick the match.
                e.preventDefault();
                var ready = box && !box.hidden && box.getAttribute('data-q') === t.value.trim();
                var active = ready ? box.querySelector('.suggest-item.is-active') : null;
                if (active) pickSuggestion(active);
                else scheduleBoxSearch(t, 0, true);
                return;
            }
        }
        if (e.key === 'F2') { e.preventDefault(); if (currentType === 'cake') $('cakeSearch').focus(); }
        if (e.key === 'F4') { e.preventDefault(); $('custCell').focus(); }
        if (e.key === 'F9') { e.preventDefault(); saveOrder('confirmed'); }
        if (e.key === 'F10' && !EDIT) { e.preventDefault(); saveOrder('hold'); }
    });

    // A suggestion list closes when focus leaves its field, so a stale list cannot cover the fields below it
    document.addEventListener('focusout', function (e) {
        var t = e.target;
        if (!t || t.tagName !== 'INPUT') return;
        if (t.id === 'cakeSearch') { hideBox($('cakeSuggest')); return; }
        if (t.classList.contains('bi-name') || t.classList.contains('desc')) {
            clearTimeout(boxSearchTimer);
            boxSearchSeq++;
            hideBox(suggestBoxFor(t));
        }
    });

    function bindStatic() {
        $('addEatableBtn').addEventListener('click', function () { addEatableItem(); });
        $('addOtherBtn').addEventListener('click', function () { addOtherItem(); });
        $('addGroupBtn').addEventListener('click', function () { addBoxGroup(); });
        $('addChargeBtn').addEventListener('click', function () { addCharge(); });
        $('clearAllBtn').addEventListener('click', clearAll);
        $('historyBtn').addEventListener('click', showCustomerHistory);
        $('addNewCustomerBtn').addEventListener('click', addNewCustomer);
        if ($('newOrderBtn')) $('newOrderBtn').addEventListener('click', newOrder);
        $('previewChangeBtn').addEventListener('click', function () { if (activeCardId) openImageModal(activeCardId); });
        $('confirmBtn').addEventListener('click', function () { saveOrder('confirmed'); });
        if ($('holdBtn')) $('holdBtn').addEventListener('click', function () { saveOrder('hold'); });
        $('imageFile').addEventListener('change', function () { previewImage(this); });
        $('imgCancel').addEventListener('click', closeImageModal);
        $('imgSave').addEventListener('click', saveImage);
        $('recordBtn').addEventListener('click', toggleRecord);
        $('audioCancel').addEventListener('click', closeAudioModal);
        $('audioSave').addEventListener('click', saveAudio);
        $('historyClose').addEventListener('click', function () { $('historyModal').classList.remove('show'); });
    }

    // ===== START =====
    bindStatic();
    renderTiles();
    renderChargePresets();
    showPreview(null);
    if (EDIT) {
        applyEdit(EDIT);
    } else {
        setType(currentType);
        if (currentType === 'cake') $('cakeSearch').focus();
    }
})();
