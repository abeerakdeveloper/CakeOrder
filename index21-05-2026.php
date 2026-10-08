<?php
require_once 'db.php';
requireRole(array(1, 3)); // POS user and Admin only
// Next bill no
$res = mysqli_query($mysqli, "SELECT MAX(bill_no) AS max_bill FROM cake_order");
$row = mysqli_fetch_assoc($res);
$nextBillNo = (intval($row['max_bill']) > 0 ? intval($row['max_bill']) : 9920) + 1;

// Load products from inventory
$prodSql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom 
            FROM inventory 
            WHERE manufacture = 'Finish Product' AND active = 1 
            ORDER BY prod_name ASC LIMIT 200";
$prodRes = mysqli_query($mysqli, $prodSql);
$products = array();
if ($prodRes) {
    while ($p = mysqli_fetch_assoc($prodRes)) {
        $products[] = $p;
    }
}

$flavors = array('Vanilla', 'Chocolate', 'Strawberry', 'Red Velvet', 'Mango', 'Butterscotch', 'Pineapple', 'Coffee');
$shapes = array('Round', 'Square', 'Heart', 'Rectangle', 'Custom');
$uoms = array('pound', 'kg', 'pcs', 'dozen');
$priorities = array('normal', 'urgent', 'vip');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>New Order - Salman Bakery POS</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="topbar">
    <h2>🧁 Salman Bakery POS</h2>
    <div class="topbar-right">
        <a href="dashboard.php">Dashboard</a>
        <a href="kitchen_display.php">Kitchen</a>
        <a href="pickup_queue.php">Pickup Queue</a>
        <a href="order_list.php">Orders</a>
        <button class="btn-user">👤 <?php echo $_SESSION['user']; ?></button>
    </div>
</div>

<div class="pos-layout">
    <!-- SIDEBAR -->
    <div class="pos-sidebar">
        <input type="text" id="searchItems" class="search" placeholder="🔍 Search products or barcode...">
        <h4 class="quick-title">PRODUCTS (<?php echo count($products); ?>)</h4>
        <div class="product-list" id="productList">
            <?php foreach ($products as $p): ?>
            <div class="product-item" 
                 data-id="<?php echo $p['inv_id']; ?>"
                 data-name="<?php echo htmlspecialchars($p['prod_name'], ENT_QUOTES); ?>"
                 data-price="<?php echo $p['retail_price']; ?>"
                 data-uom="<?php echo htmlspecialchars($p['uom'], ENT_QUOTES); ?>"
                 data-barcode="<?php echo htmlspecialchars($p['barcode'], ENT_QUOTES); ?>"
                 onclick="addItemFromProduct(this)">
                <div class="name"><?php echo htmlspecialchars($p['prod_name']); ?></div>
                <div class="price">Rs. <?php echo number_format($p['retail_price'], 0); ?></div>
                <div class="meta">
                    <?php echo htmlspecialchars($p['uom']); ?>
                    <?php if (!empty($p['barcode'])): ?>
                    | <?php echo htmlspecialchars($p['barcode']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <div style="text-align:center;padding:20px;color:#999;font-size:12px;">
                No products found.<br>
                Add items in inventory with:<br>
                manufacture = 'Finish Product'<br>
                active = 1
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN AREA -->
    <div class="pos-main">
        <div class="stepper">
            <div class="step active"><span>1</span> Draft</div>
            <div class="line"></div>
            <div class="step"><span>2</span> Order</div>
            <div class="line"></div>
            <div class="step"><span>3</span> Payment</div>
            <div class="line"></div>
            <div class="step"><span>4</span> Receipt</div>
        </div>

        <div class="customer-bar">
            <div class="field">
                <label>Customer Name</label>
                <input type="text" id="custName" placeholder="Walk-in" value="Walk-in">
            </div>
            <div class="field">
                <label>Cell No</label>
                <input type="text" id="custCell" placeholder="0300-0000000">
            </div>
            <div class="field">
                <label>Delivery Date</label>
                <input type="date" id="deliverDate" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="field">
                <label>Delivery Time</label>
                <input type="time" id="deliverTime" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
            </div>
            <div class="field">
                <label>Priority</label>
                <select id="priority">
                    <?php foreach ($priorities as $p): ?>
                    <option value="<?php echo $p; ?>"><?php echo ucfirst($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="transaction-area">
            <div class="transaction-header">
                <div>
                    <h3>Current Transaction</h3>
                    <p>Order #<span id="billNo"><?php echo $nextBillNo; ?></span> • Customer: <span id="custDisplay">Walk-in</span></p>
                </div>
                <button class="btn-clear" onclick="clearAll()">🗑 Clear All</button>
            </div>
            <div id="cartItems" class="cart-items"></div>
            <div id="emptyMsg" style="text-align:center;padding:40px;color:#999;">
                👈 Click products from the left to add to order
            </div>
        </div>

        <div class="footer-bar">
            <div class="footer-inner">
                <div class="totals">
                    <div class="row">Subtotal <span id="subtotal">Rs. 0</span></div>
                    <div class="row">Discount <span id="discountDisplay">Rs. 0</span></div>
                    <div class="row grand">Total <span id="total">Rs. 0</span></div>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <div class="field" style="display:flex;flex-direction:column;gap:2px;">
                        <label style="font-size:11px;color:#6c3483;font-weight:600;">Discount</label>
                        <input type="number" id="flatDisc" value="0" style="width:80px;padding:6px;border:1px solid #ddd;border-radius:6px;" onchange="calcTotals()">
                    </div>
                    <div class="field" style="display:flex;flex-direction:column;gap:2px;">
                        <label style="font-size:11px;color:#6c3483;font-weight:600;">Advance</label>
                        <input type="number" id="advance" value="0" style="width:80px;padding:6px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                </div>
                <div class="action-btns">
                    <button class="btn-hold" onclick="saveOrder('hold')">⏸ Hold</button>
                    <button class="btn-confirm" onclick="saveOrder('confirmed')">Confirm Order →</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- IMAGE UPLOAD MODAL -->
<div class="modal-overlay" id="imageModal">
    <div class="modal">
        <h3>📷 Attach Image</h3>
        <div class="form-group">
            <label>Take Photo or Choose File</label>
            <input type="file" id="imageFile" accept="image/*" capture="environment" onchange="previewImage(this)">
        </div>
        <img id="imgPreview" class="img-preview" style="display:none;">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeImageModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveImage()">Save Image</button>
        </div>
    </div>
</div>

<!-- AUDIO RECORDING MODAL -->
<div class="modal-overlay" id="audioModal">
    <div class="modal">
        <h3>🎤 Voice Instructions</h3>
        <p style="margin-bottom:12px;color:#888;font-size:13px;">Record voice note for this item</p>
        <div style="text-align:center;margin:20px 0;">
            <button class="btn btn-danger" id="recordBtn" onclick="toggleRecord()" style="width:60px;height:60px;border-radius:50%;font-size:24px;">🎤</button>
            <p id="recordStatus" style="margin-top:8px;font-size:12px;color:#888;">Click to start recording</p>
        </div>
        <audio id="audioPlayback" controls style="width:100%;display:none;margin:10px 0;"></audio>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeAudioModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveAudio()">Save</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var FLAVORS = <?php echo json_encode($flavors); ?>;
var SHAPES = <?php echo json_encode($shapes); ?>;
var UOMS = <?php echo json_encode($uoms); ?>;
var itemCounter = 0;
var currentImageItem = null;
var currentAudioItem = null;
var mediaRecorder = null;
var audioChunks = [];
var tempImageData = '';

document.getElementById('custName').addEventListener('input', function() {
    document.getElementById('custDisplay').textContent = this.value || 'Walk-in';
});

function addItemFromProduct(el) {
    var invId = el.dataset.id;
    var name = el.dataset.name;
    var price = parseFloat(el.dataset.price);
    var uom = el.dataset.uom;
    addItem(invId, name, price, uom);
}

function addItem(invId, name, price, uom) {
    document.getElementById('emptyMsg').style.display = 'none';
    
    itemCounter++;
    var id = 'item_' + itemCounter;
    var cart = document.getElementById('cartItems');
    
    var flavorOpts = '<option value="">Flavor...</option>';
    for (var i = 0; i < FLAVORS.length; i++) {
        flavorOpts += '<option value="' + FLAVORS[i] + '">' + FLAVORS[i] + '</option>';
    }
    
    var shapeOpts = '<option value="">Shape...</option>';
    for (var j = 0; j < SHAPES.length; j++) {
        shapeOpts += '<option value="' + SHAPES[j] + '">' + SHAPES[j] + '</option>';
    }
    
    var uomOpts = '';
    for (var k = 0; k < UOMS.length; k++) {
        var sel = (UOMS[k] === uom) ? 'selected' : '';
        uomOpts += '<option value="' + UOMS[k] + '" ' + sel + '>' + UOMS[k] + '</option>';
    }

    var div = document.createElement('div');
    div.className = 'cart-item fade-in';
    div.id = id;
    div.dataset.invId = invId;
    div.dataset.price = price;
    div.dataset.name = name;
    div.dataset.image = '';
    div.dataset.audio = '';
    
    div.innerHTML = 
        '<div class="item-image" onclick="openImageModal(\'' + id + '\')" id="imgArea_' + id + '">' +
            '<span class="placeholder">📷</span>' +
            '<span class="upload-icon">+</span>' +
        '</div>' +
        '<div class="item-details">' +
            '<div class="item-top">' +
                '<h4>' + name + '</h4>' +
                '<button class="btn-play" onclick="openAudioModal(\'' + id + '\')" id="audioBtn_' + id + '">🎤 Record</button>' +
                '<button class="btn-play" style="display:none;" onclick="playAudio(\'' + id + '\')" id="audioPlay_' + id + '">▶ Play</button>' +
                '<span class="price">Rs. ' + price.toLocaleString() + '</span>' +
                '<button class="btn-remove" onclick="removeItem(\'' + id + '\')">✕</button>' +
            '</div>' +
            '<div class="item-options">' +
                '<select class="flavor" style="flex:1;min-width:100px;">' + flavorOpts + '</select>' +
                '<select class="shape" style="flex:1;min-width:100px;">' + shapeOpts + '</select>' +
                '<div class="qty-control">' +
                    '<button onclick="changeQty(\'' + id + '\',-1)">−</button>' +
                    '<span class="qty">1</span>' +
                    '<button onclick="changeQty(\'' + id + '\',1)">+</button>' +
                '</div>' +
            '</div>' +
            '<div class="item-options">' +
                '<select class="uom" style="width:90px;">' + uomOpts + '</select>' +
                '<input type="number" class="tiers" value="1" min="1" max="5" style="width:55px;" title="Tiers">' +
                '<input type="text" class="cake-msg" placeholder="Cake message..." style="flex:1;min-width:120px;">' +
            '</div>' +
            '<input type="text" class="note" placeholder="📝 Special Instructions...">' +
        '</div>';
    
    cart.appendChild(div);
    calcTotals();
    showToast('Added: ' + name, 'success');
}

function removeItem(id) {
    var el = document.getElementById(id);
    if (el) el.remove();
    calcTotals();
    if (document.querySelectorAll('.cart-item').length === 0) {
        document.getElementById('emptyMsg').style.display = 'block';
    }
}

function changeQty(id, delta) {
    var item = document.getElementById(id);
    var span = item.querySelector('.qty');
    var q = parseInt(span.textContent) + delta;
    if (q < 1) { removeItem(id); return; }
    span.textContent = q;
    calcTotals();
}

function calcTotals() {
    var subtotal = 0;
    var items = document.querySelectorAll('.cart-item');
    for (var i = 0; i < items.length; i++) {
        var price = parseFloat(items[i].dataset.price);
        var qty = parseInt(items[i].querySelector('.qty').textContent);
        subtotal += price * qty;
    }
    var discount = parseInt(document.getElementById('flatDisc').value) || 0;
    var total = subtotal - discount;
    document.getElementById('subtotal').textContent = 'Rs. ' + subtotal.toLocaleString();
    document.getElementById('discountDisplay').textContent = 'Rs. ' + discount.toLocaleString();
    document.getElementById('total').textContent = 'Rs. ' + Math.max(0, total).toLocaleString();
}

function clearAll() {
    if (!confirm('Clear all items?')) return;
    document.getElementById('cartItems').innerHTML = '';
    document.getElementById('emptyMsg').style.display = 'block';
    calcTotals();
}

/* IMAGE HANDLING */
function openImageModal(itemId) {
    currentImageItem = itemId;
    document.getElementById('imageModal').classList.add('show');
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imageFile').value = '';
    tempImageData = '';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('show');
    currentImageItem = null;
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            // Resize before saving (max 800x800)
            var img = new Image();
            img.onload = function() {
                var canvas = document.createElement('canvas');
                var maxSize = 800;
                var w = img.width, h = img.height;
                if (w > h) {
                    if (w > maxSize) { h *= maxSize / w; w = maxSize; }
                } else {
                    if (h > maxSize) { w *= maxSize / h; h = maxSize; }
                }
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                tempImageData = canvas.toDataURL('image/jpeg', 0.8);
                document.getElementById('imgPreview').src = tempImageData;
                document.getElementById('imgPreview').style.display = 'block';
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveImage() {
    if (!tempImageData || !currentImageItem) {
        closeImageModal();
        return;
    }
    var item = document.getElementById(currentImageItem);
    item.dataset.image = tempImageData;
    // Update preview
    var imgArea = document.getElementById('imgArea_' + currentImageItem);
    imgArea.innerHTML = '<img src="' + tempImageData + '"><span class="upload-icon">✎</span>';
    showToast('Image attached', 'success');
    closeImageModal();
}

/* AUDIO HANDLING */
function openAudioModal(itemId) {
    currentAudioItem = itemId;
    document.getElementById('audioModal').classList.add('show');
    document.getElementById('audioPlayback').style.display = 'none';
    document.getElementById('recordStatus').textContent = 'Click to start recording';
    audioChunks = [];
}

function closeAudioModal() {
    document.getElementById('audioModal').classList.remove('show');
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
    }
    currentAudioItem = null;
}

function toggleRecord() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        document.getElementById('recordBtn').textContent = '🎤';
        document.getElementById('recordStatus').textContent = 'Recording saved';
    } else {
        if (!navigator.mediaDevices) {
            showToast('Microphone not supported in this browser', 'error');
            return;
        }
        navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = function(e) { audioChunks.push(e.data); };
            mediaRecorder.onstop = function() {
                var blob = new Blob(audioChunks, {type: 'audio/webm'});
                var url = URL.createObjectURL(blob);
                var audio = document.getElementById('audioPlayback');
                audio.src = url;
                audio.style.display = 'block';
                // Convert to base64
                var reader = new FileReader();
                reader.onload = function() {
                    if (currentAudioItem) {
                        document.getElementById(currentAudioItem).dataset.audio = reader.result;
                    }
                };
                reader.readAsDataURL(blob);
                stream.getTracks().forEach(function(t) { t.stop(); });
            };
            mediaRecorder.start();
            document.getElementById('recordBtn').textContent = '⏹';
            document.getElementById('recordStatus').textContent = '🔴 Recording...';
        }).catch(function() {
            showToast('Microphone access denied', 'error');
        });
    }
}

function saveAudio() {
    if (currentAudioItem) {
        var item = document.getElementById(currentAudioItem);
        if (item.dataset.audio) {
            document.getElementById('audioBtn_' + currentAudioItem).style.display = 'none';
            document.getElementById('audioPlay_' + currentAudioItem).style.display = 'inline-block';
            showToast('Voice note saved', 'success');
        }
    }
    closeAudioModal();
}

function playAudio(itemId) {
    var audioData = document.getElementById(itemId).dataset.audio;
    if (!audioData) return;
    new Audio(audioData).play();
}

/* SAVE ORDER */
function collectOrderData() {
    var items = [];
    var elements = document.querySelectorAll('.cart-item');
    for (var i = 0; i < elements.length; i++) {
        var item = elements[i];
        items.push({
            inv_id: item.dataset.invId,
            name: item.dataset.name,
            category: item.dataset.name,
            price: parseFloat(item.dataset.price),
            qty: parseInt(item.querySelector('.qty').textContent),
            flavor: item.querySelector('.flavor').value,
            shape: item.querySelector('.shape').value,
            uom: item.querySelector('.uom').value,
            tiers: parseInt(item.querySelector('.tiers').value) || 1,
            cake_message: item.querySelector('.cake-msg').value,
            note: item.querySelector('.note').value,
            image_data: item.dataset.image || '',
            audio_data: item.dataset.audio || ''
        });
    }
    return items;
}

function saveOrder(status) {
    var items = collectOrderData();
    if (items.length === 0) { showToast('Cart is empty!', 'error'); return; }

    // Validate delivery date and time is not in the past
    var delDate = document.getElementById('deliverDate').value;
    var delTime = document.getElementById('deliverTime').value;
    var now = new Date();
    var selectedDateTime = new Date(delDate + 'T' + delTime);
    if (selectedDateTime < now) {
        showToast('Delivery time cannot be in the past!', 'error');
        return;
    }

    var data = {
        items: items,
        status: status,
        party_detail: document.getElementById('custName').value || 'Walk-in',
        cell_no: document.getElementById('custCell').value,
        deliver_date: document.getElementById('deliverDate').value,
        delivery_time: document.getElementById('deliverTime').value,
        priority: document.getElementById('priority').value,
        flat_disc: parseInt(document.getElementById('flatDisc').value) || 0,
        advance: parseInt(document.getElementById('advance').value) || 0
    };

    showToast('Saving... (image upload may take a moment)', 'info');

    fetch('save_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast('Order #' + res.bill_no + ' saved!', 'success');
            if (status === 'confirmed') {
                setTimeout(function() { window.location.href = 'order_list.php'; }, 1000);
            } else {
                document.getElementById('cartItems').innerHTML = '';
                document.getElementById('emptyMsg').style.display = 'block';
                calcTotals();
                document.getElementById('billNo').textContent = res.bill_no + 1;
            }
        } else {
            showToast('Error: ' + res.message, 'error');
        }
    })
    .catch(function() { showToast('Network error', 'error'); });
}

function showToast(msg, type) {
    var toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast ' + type + ' show';
    setTimeout(function() { toast.classList.remove('show'); }, 3000);
}

// Search products
document.getElementById('searchItems').addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    var items = document.querySelectorAll('.product-item');
    for (var i = 0; i < items.length; i++) {
        var text = items[i].textContent.toLowerCase();
        items[i].style.display = text.indexOf(q) > -1 ? '' : 'none';
    }
});
</script>
</body>
</html>