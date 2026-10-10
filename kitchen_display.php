<?php
require_once 'db.php';
requireRole(array(2, 3)); // Kitchen and Admin only

$showAccessMsg = isset($_GET['msg']) && $_GET['msg'] == 'no_dashboard_access';

// Filter: which statuses the board shows
$kf = isset($_GET['kf']) ? $_GET['kf'] : 'kitchen';
if ($kf === 'ready')     $statusWhere = "co.status = 'ready'";
elseif ($kf === 'all')   $statusWhere = "co.status IN ('confirmed','preparing','ready')";
else                     $statusWhere = "co.status IN ('confirmed','preparing')";

$sql = "SELECT co.bill_no, co.party_detail, co.deliver_date, co.delivery_time,
        co.status, co.priority, MAX(co.dateent) AS dateent,
        GROUP_CONCAT(CONCAT(
            'qty = ', co.qty,
            ' , ', co.uom,
            ' = ', IFNULL(co.tiers, 0),
            ' , ', co.category,
            CASE WHEN co.flavor != '' THEN CONCAT(' (', co.flavor, ')') ELSE '' END,
            CASE WHEN co.cake_message != '' THEN CONCAT(' [', co.cake_message, ']') ELSE '' END
        ) SEPARATOR '||') AS items,
        GROUP_CONCAT(IFNULL(co.notes,'') SEPARATOR '||') AS all_notes,
        GROUP_CONCAT(IFNULL(co.thumb_data,'') SEPARATOR '||') AS thumbs,
        SUM(co.amount) AS total_amount,
        SUM(co.qty) AS total_qty,
        GROUP_CONCAT(DISTINCT CONCAT(IFNULL(co.tiers,1), ' ', co.uom)) AS sizes,
        GROUP_CONCAT(DISTINCT NULLIF(co.flavor,'')) AS flavors,
        MAX(CASE WHEN co.audio_data IS NOT NULL AND co.audio_data != '' THEN 1 ELSE 0 END) AS has_audio,
        MAX(CASE WHEN co.notes LIKE '%DELIVERY ADDR:%' THEN 1 ELSE 0 END) AS is_delivery,
        COUNT(*) AS item_count
        FROM cake_order co
        WHERE $statusWhere AND co.ordercancel = 0
        GROUP BY bill_no
        ORDER BY co.bill_no DESC";

$result = mysqli_query($mysqli, $sql);
$orders = array();
if ($result) while ($r = mysqli_fetch_assoc($result)) $orders[] = $r;

// Progress counts (always over the whole active board)
$progRes = mysqli_query($mysqli, "SELECT status, COUNT(DISTINCT bill_no) c FROM cake_order
    WHERE status IN ('confirmed','preparing','ready') AND ordercancel = 0 GROUP BY status");
$prog = array('confirmed' => 0, 'preparing' => 0, 'ready' => 0);
if ($progRes) while ($p = mysqli_fetch_assoc($progRes)) $prog[$p['status']] = intval($p['c']);

$preparingBills = array();
foreach ($orders as $o) if ($o['status'] === 'preparing') $preparingBills[] = intval($o['bill_no']);

function kdShort($s, $n) {
    $s = trim((string)$s);
    if (strlen($s) <= $n) return $s;
    return substr($s, 0, $n) . '…';
}
function orderKind($items, $notes) {
    if (stripos($notes, 'Lunch Box Set') !== false)  return 'lunch';
    if (stripos($notes, 'Sweets Box Set') !== false) return 'sweets';
    if (preg_match('/cake/i', $items))               return 'cake';
    return 'custom';
}
$kindMeta = array(
    'cake'   => array('label' => 'Cake Order',        'cls' => 'kd-cake'),
    'lunch'  => array('label' => 'Lunch Box Order',   'cls' => 'kd-lunch'),
    'sweets' => array('label' => 'Sweets Box Order',  'cls' => 'kd-sweets'),
    'custom' => array('label' => 'Custom Order',      'cls' => 'kd-custom'),
);

$pageTitle = 'Kitchen Orders';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kitchen Orders — BestPOS</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/app.css">
<script src="assets/pos_common.js"></script>
<style>
.kd-cols { display: block; grid-template-columns: minmax(0,1fr) 300px; gap: 16px; align-items: start; }
.kd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(430px, 1fr)); gap: 14px; }
.ko-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
.ko-card.urgent { border-color: var(--red); box-shadow: 0 0 0 1px var(--red) inset; }
.ko-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--line); }
.ko-head .bill { font-size: 15px; font-weight: 800; }
.ko-head .spacer { flex: 1; }
.ko-head .ago { font-size: 11.5px; color: var(--muted); }
.kd-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.kd-cake { background: #fdeef3; color: #d34f7d; }
.kd-lunch { background: #e7f7ef; color: #12814b; }
.kd-sweets { background: #fdf3e3; color: #b26a05; }
.kd-custom { background: #eaf1fe; color: #1a5fd0; }
.ko-body { display: grid; grid-template-columns: minmax(0,1.25fr) minmax(0,1fr); gap: 12px; padding: 14px; }
.ko-media { display: flex; gap: 10px; }
.ko-media .ph { width: 86px; height: 86px; border-radius: 10px; background: var(--blue-soft); display: flex; align-items: center; justify-content: center; font-size: 34px; overflow: hidden; flex-shrink: 0; cursor: pointer; }
.ko-media .ph img { width: 100%; height: 100%; object-fit: cover; }
.ko-media .tt h5 { font-size: 14px; color: var(--blue); line-height: 1.25; }
.ko-media .tt .sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
.ko-media .tt .ln { font-size: 11.5px; color: var(--text); margin-top: 5px; display: flex; gap: 6px; align-items: flex-start; }
.ko-media .tt .ln .ic { color: var(--muted); }
.ko-detail { background: #f8fafc; border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; }
.ko-detail h6 { font-size: 12px; font-weight: 700; margin-bottom: 6px; }
.ko-detail .r { display: flex; gap: 6px; font-size: 11.5px; padding: 2.5px 0; color: var(--text); }
.ko-detail .r .ic { color: var(--muted); width: 14px; }
.ko-detail .r .k { color: var(--muted); min-width: 52px; }
.ko-chips { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 14px 12px; }
.ko-chips .chip { background: var(--blue-soft); color: var(--blue); border-radius: 8px; padding: 5px 10px; font-size: 11px; font-weight: 600; display: inline-flex; gap: 6px; align-items: center; }
.ko-note { margin: 0 14px 12px; background: #fdf8e8; border: 1px solid #f3e3b3; border-radius: 8px; padding: 8px 10px; font-size: 11.5px; color: #7a5b13; }
.ko-foot { display: flex; gap: 8px; padding: 12px 14px; background: #f8fafc; border-top: 1px solid var(--line); }
.ko-foot .btn { flex: 1; }
.audio-mini-btn { background: var(--amber); color: #fff; border: none; border-radius: 50%; width: 26px; height: 26px; cursor: pointer; font-size: 12px; flex-shrink: 0; }
.audio-mini-btn.playing { background: var(--green); animation: pulse-audio 1s infinite; }
@keyframes pulse-audio { 0%,100% { box-shadow: 0 0 0 0 rgba(24,160,94,.55); } 50% { box-shadow: 0 0 0 8px rgba(24,160,94,0); } }
.qa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qa-btn { border: 1px solid var(--line); background: #fff; border-radius: 10px; padding: 14px 8px; text-align: center; cursor: pointer; font-size: 11.5px; font-weight: 600; color: var(--text); }
.qa-btn:hover { border-color: var(--blue); background: var(--blue-soft); }
.qa-btn .ic { display: block; font-size: 20px; margin-bottom: 6px; }
.donut-wrap { display: flex; align-items: center; gap: 14px; }
.donut { width: 108px; height: 108px; border-radius: 50%; position: relative; flex-shrink: 0;
    background: conic-gradient(var(--amber) 0 var(--a1), var(--blue) var(--a1) var(--a2), var(--green) var(--a2) 100%); }
.donut::after { content: ''; position: absolute; inset: 14px; background: #fff; border-radius: 50%; }
.donut .ctr { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1; font-size: 15px; font-weight: 800; color: var(--amber); }
.donut .ctr small { font-size: 9px; color: var(--muted); font-weight: 600; }
.dlegend .r { display: flex; align-items: center; gap: 7px; font-size: 12px; padding: 3px 0; }
.dlegend .dot { width: 9px; height: 9px; border-radius: 50%; }
.dlegend .n { margin-left: auto; font-weight: 700; }
.img-viewer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.92); display: none; align-items: center; justify-content: center; z-index: 9999; flex-direction: column; }
.img-viewer-overlay.show { display: flex; }
.img-viewer-overlay img { max-width: 90vw; max-height: 80vh; border-radius: 8px; }
.img-viewer-overlay .close-btn { position: absolute; top: 20px; right: 20px; background: var(--red); color: #fff; border: none; width: 46px; height: 46px; border-radius: 50%; font-size: 20px; cursor: pointer; }
.img-viewer-overlay .info { color: #fff; margin-top: 14px; font-size: 13px; background: rgba(0,0,0,.6); padding: 8px 16px; border-radius: 6px; }
@media (max-width: 1100px) { .kd-cols { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<?php if ($showAccessMsg): ?>
<div class="card" style="background:#fdf3e3;border-color:#f3e3b3;color:#7a5b13;margin-bottom:14px;">
    ⚠ <strong>Access Restricted:</strong> Kitchen staff cannot access Dashboard — redirected to Kitchen Orders.
    <button onclick="this.parentElement.style.display='none'" style="float:right;background:none;border:none;font-size:15px;cursor:pointer;">✕</button>
</div>
<?php endif; ?>

<div class="page-head">
    <div class="ph-ic">👨‍🍳</div>
    <div>
        <h2>Kitchen Orders</h2>
    </div>
    <div class="spacer"></div>
    <span class="bdg confirmed">📥 New: <?php echo $prog['confirmed']; ?></span>
    <span class="bdg preparing">🔥 Preparing: <?php echo $prog['preparing']; ?></span>
    <span class="bdg ready">✅ Ready: <?php echo $prog['ready']; ?></span>
</div>

<div class="kd-cols">
    <div>
        <!-- filters -->
        <div class="card">
            <div class="row" style="flex-wrap:wrap;align-items:flex-end;">
                <div class="fld" style="flex:1.4;min-width:220px;">
                    <label>🔍 Search</label>
                    <input class="inp" id="kitchenSearch" placeholder="Search by cake name, item or order #..." oninput="filterKitchen()">
                </div>
                <div class="fld" style="flex:1.4;min-width:240px;">
                    <label>Order Type</label>
                    <div class="tabs" id="typeTabs">
                        <button class="active" data-type="all" onclick="setTypeFilter('all', this)">All</button>
                        <button data-type="cake" onclick="setTypeFilter('cake', this)">Cake</button>
                        <button data-type="lunch" onclick="setTypeFilter('lunch', this)">Lunch Box</button>
                        <button data-type="sweets" onclick="setTypeFilter('sweets', this)">Sweets Box</button>
                        <button data-type="custom" onclick="setTypeFilter('custom', this)">Custom</button>
                    </div>
                </div>
                <div class="fld" style="flex:0 0 130px;">
                    <label>Priority</label>
                    <select class="inp" id="prioSel" onchange="filterKitchen()">
                        <option value="all">All</option>
                        <option value="vip">⭐ VIP</option>
                        <option value="urgent">🔴 Urgent</option>
                        <option value="normal">Normal</option>
                    </select>
                </div>
                <div class="fld" style="flex:1;min-width:160px;">
                    <label>Status</label>
                    <select class="inp" onchange="location.search='?kf='+this.value">
                        <option value="kitchen" <?php echo $kf === 'kitchen' ? 'selected' : ''; ?>>In Kitchen (new + preparing)</option>
                        <option value="ready" <?php echo $kf === 'ready' ? 'selected' : ''; ?>>Ready (to pack / hand over)</option>
                        <option value="all" <?php echo $kf === 'all' ? 'selected' : ''; ?>>All active</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- cards -->
        <?php if (empty($orders)): ?>
        <div class="card mt16 empty"><span class="big">👨‍🍳</span>No orders in this view.<br>New orders appear automatically.</div>
        <?php endif; ?>

        <div class="kd-grid mt16">
            <?php foreach ($orders as $o):
                $kind = orderKind($o['items'], $o['all_notes']);
                $meta = $kindMeta[$kind];
                $isUrgent = $o['priority'] == 'urgent';
                $itemsList = explode('||', $o['items']);
                $thumbsList = explode('||', $o['thumbs']);
                $notesList = array_values(array_filter(explode('||', $o['all_notes'])));
                $elapsedMin = floor((time() - strtotime($o['dateent'])) / 60);
                $firstItem = trim($itemsList[0]);
                // pretty first line: category (flavor)
                $firstPretty = preg_replace('/^qty = [0-9.]+ , [a-z]+ = [0-9.]+ , /i', '', $firstItem);
                $ladi = '';
                if (preg_match('/LADI: ([^|]+)/', $o['all_notes'], $lm)) $ladi = trim($lm[1]);

                $itemDetailsSql = "SELECT id,
                    (image_data IS NOT NULL AND image_data != '') AS has_image,
                    (audio_data IS NOT NULL AND audio_data != '') AS has_audio
                    FROM cake_order WHERE bill_no = " . intval($o['bill_no']) . " AND ordercancel = 0 ORDER BY id";
                $itemDetailsRes = mysqli_query($mysqli, $itemDetailsSql);
                $itemDetails = array();
                if ($itemDetailsRes) while ($d = mysqli_fetch_assoc($itemDetailsRes)) $itemDetails[] = $d;
            ?>
            <div class="ko-card <?php echo $isUrgent ? 'urgent' : ''; ?> fade-in" data-type="<?php echo $kind; ?>" data-priority="<?php echo htmlspecialchars($o['priority']); ?>" data-bill="<?php echo $o['bill_no']; ?>">
                <div class="ko-head">
                    <span class="bill">#<?php echo $o['bill_no']; ?></span>
                    <span class="kd-badge <?php echo $meta['cls']; ?>"><?php echo $meta['label']; ?></span>
                    <?php if ($isUrgent): ?><span class="bdg cancelled">🔴 URGENT</span><?php endif; ?>
                    <?php if ($o['priority'] == 'vip'): ?><span class="bdg prio-vip">⭐ VIP</span><?php endif; ?>
                    <span class="spacer"></span>
                    <span class="ago">🕐 <?php echo $elapsedMin; ?> mins ago</span>
                </div>

                <div class="ko-body">
                    <div class="ko-media">
                        <?php
                        $thumbHex = isset($thumbsList[0]) ? trim($thumbsList[0]) : '';
                        $itemId0 = isset($itemDetails[0]) ? $itemDetails[0]['id'] : 0;
                        $hasImage0 = isset($itemDetails[0]) ? $itemDetails[0]['has_image'] : 0;
                        ?>
                        <div class="ph" <?php if (!empty($thumbHex) && $hasImage0): ?>onclick="viewImage(<?php echo $itemId0; ?>, '<?php echo addslashes($firstPretty); ?>', <?php echo $o['bill_no']; ?>)" title="Click to view full image"<?php endif; ?>>
                            <?php if (!empty($thumbHex) && $hasImage0): ?>
                                <img src="show_image.php?type=thumb&id=<?php echo $itemId0; ?>" alt="">
                            <?php else: ?><?php echo $kind === 'lunch' ? '🍱' : ($kind === 'sweets' ? '🍬' : '🎂'); ?><?php endif; ?>
                        </div>
                        <div class="tt">
                            <h5><?php echo htmlspecialchars(kdShort($firstPretty, 60)); ?></h5>
                            <div class="sub"><?php echo htmlspecialchars($o['party_detail'] ? $o['party_detail'] : 'Walk-in'); ?> · <?php echo $o['item_count']; ?> items</div>
                            <?php foreach ($itemsList as $idx => $it): if ($idx === 0) continue;
                                $idxItemId = isset($itemDetails[$idx]) ? $itemDetails[$idx]['id'] : 0;
                                $idxHasAudio = isset($itemDetails[$idx]) ? $itemDetails[$idx]['has_audio'] : 0;
                                $safe = htmlspecialchars(preg_replace('/^qty = [0-9.]+ , [a-z]+ = [0-9.]+ , /i', '', trim($it)));
                            ?>
                            <div class="ln"><span class="ic"></span><span style="flex:1;"><?php echo preg_replace('/\[(.*?)\]$/', '<strong style="color:var(--red);">[$1]</strong>', $safe); ?></span>
                                <?php if ($idxHasAudio): ?><button class="audio-mini-btn" title="Play voice instructions" onclick="playItemAudio(<?php echo $idxItemId; ?>, this)">🔊</button><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php $idxHasAudio0 = isset($itemDetails[0]) ? $itemDetails[0]['has_audio'] : 0; ?>
                            <?php if ($idxHasAudio0): ?>
                            <div class="ln"><span class="ic">🎤</span><span style="flex:1;">Voice note attached</span><button class="audio-mini-btn" onclick="playItemAudio(<?php echo $itemId0; ?>, this)">🔊</button></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ko-detail">
                        <h6>Order Details</h6>
                        <div class="r"><span class="ic">🧾</span><span class="k">Quantity:</span> <?php echo intval($o['total_qty']); ?></div>
                        <div class="r"><span class="ic">📐</span><span class="k">Size:</span> <?php echo htmlspecialchars($o['sizes']); ?></div>
                        <div class="r"><span class="ic">🚚</span><span class="k">Delivery:</span> <?php echo $o['is_delivery'] ? 'Home Delivery' : 'Pickup'; ?></div>
                        <div class="r"><span class="ic">🕐</span><span class="k">Time:</span> <?php echo date('d M', strtotime($o['deliver_date'])); ?>, <?php echo $o['delivery_time']; ?></div>
                        <?php if (!empty($notesList)): ?>
                        <div class="r"><span class="ic">📝</span><span class="k">Note:</span> <?php echo htmlspecialchars(kdShort($notesList[0], 46)); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ko-chips">
                    <?php if ($o['flavors']): ?><span class="chip"> Type: <?php echo htmlspecialchars($o['flavors']); ?></span><?php endif; ?>
                    <?php if ($ladi): ?><span class="chip"> Ladi: <?php echo htmlspecialchars($ladi); ?></span><?php endif; ?>
                    <?php if ($kind === 'lunch' || $kind === 'sweets'): ?>
                    <span class="chip"> Total Boxes: <?php echo intval($o['total_qty']); ?></span>
                    <?php endif; ?>
                    <?php if ($o['total_amount'] > 0): ?><span class="chip">💰 Rs. <?php echo number_format($o['total_amount']); ?></span><?php else: ?><span class="chip">️ After weight</span><?php endif; ?>
                </div>

                <?php if (!empty($notesList)): ?>
                <div class="ko-note">📝 <?php echo htmlspecialchars(implode(' | ', $notesList)); ?></div>
                <?php endif; ?>

                <div class="ko-foot">
                    <a class="btn btn-outline" href="order_detail.php?bill=<?php echo $o['bill_no']; ?>">👁 View Details</a>
                    <?php if ($o['status'] == 'confirmed'): ?>
                    <button class="btn btn-primary" onclick="kitchenAction(<?php echo $o['bill_no']; ?>, 'preparing')">🔥 Mark as Preparing</button>
                    <?php elseif ($o['status'] == 'preparing'): ?>
                    <button class="btn btn-success" onclick="kitchenAction(<?php echo $o['bill_no']; ?>, 'ready')">✅ Mark as Ready</button>
                    <?php else: ?>
                    <a class="btn btn-success" href="pickup_queue.php">Pickup Queue</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- right rail -->
    <div style="display:none;">
        <div class="card" style="display:none;">
            <div class="card-title"><span class="ic"></span> Quick Actions</div>
            <div class="qa-grid">
                <button class="qa-btn" onclick="markAllReady()"><span class="ic" style="color:var(--green);">✅</span>Mark All Ready</button>
                <a class="qa-btn" href="order_list.php"><span class="ic" style="color:var(--blue);">📋</span>View All Orders</a>
                <button class="qa-btn" id="pauseBtn" onclick="togglePause()"><span class="ic" style="color:var(--amber);">⏸</span>Pause Refresh</button>
                <button class="qa-btn" onclick="location.reload()"><span class="ic" style="color:var(--blue);">🔄</span>Refresh Now</button>
            </div>
        </div>

        <div class="card mt16">
            <div class="card-title"><span class="ic">📈</span> Order Progress</div>
            <?php
            $tot = max(1, $prog['confirmed'] + $prog['preparing'] + $prog['ready']);
            $a1 = round($prog['confirmed'] / $tot * 100);
            $a2 = $a1 + round($prog['preparing'] / $tot * 100);
            ?>
            <div class="donut-wrap">
                <div class="donut" style="--a1:<?php echo $a1; ?>%;--a2:<?php echo $a2; ?>%;">
                    <div class="ctr"><?php echo $prog['preparing']; ?>/<?php echo $prog['confirmed'] + $prog['preparing'] + $prog['ready']; ?><small>In Progress</small></div>
                </div>
                <div class="dlegend" style="flex:1;">
                    <div class="r"><span class="dot" style="background:var(--amber);"></span> Pending <span class="n"><?php echo $prog['confirmed']; ?></span></div>
                    <div class="r"><span class="dot" style="background:var(--blue);"></span> In Progress <span class="n"><?php echo $prog['preparing']; ?></span></div>
                    <div class="r"><span class="dot" style="background:var(--green);"></span> Ready <span class="n"><?php echo $prog['ready']; ?></span></div>
                </div>
            </div>
        </div>

        <div class="card mt16 center" style="background:var(--blue-soft);border-color:var(--blue-line);padding:26px 16px;">
            <div style="font-size:30px;">👨‍🍳</div>
            <div style="font-size:13px;font-weight:600;color:var(--blue);margin-top:8px;line-height:1.5;">Great food<br>makes happy customers!</div>
            <div style="color:var(--blue);margin-top:6px;">♥</div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<!-- IMAGE VIEWER MODAL -->
<div class="img-viewer-overlay" id="imgViewer" onclick="closeImageViewer(event)">
    <button class="close-btn" onclick="closeImageViewer(event, true)">✕</button>
    <img id="viewerImage" src="" alt="">
    <div class="info" id="viewerInfo"></div>
</div>
<audio id="kitchenAudio" style="display:none;"></audio>

</main>
</div>

<script>
var PREPARING_BILLS = <?php echo json_encode($preparingBills); ?>;
var refreshTimer = setInterval(function () { location.reload(); }, 60000);
var paused = false;
var typeFilter = 'all';

function togglePause() {
    paused = !paused;
    var b = document.getElementById('pauseBtn');
    if (paused) { clearInterval(refreshTimer); b.innerHTML = '<span class="ic" style="color:var(--green);">▶</span>Resume Refresh'; showToast('Auto-refresh paused', 'info'); }
    else { refreshTimer = setInterval(function () { location.reload(); }, 60000); b.innerHTML = '<span class="ic" style="color:var(--amber);">⏸</span>Pause Refresh'; showToast('Auto-refresh resumed', 'info'); }
}

function setTypeFilter(t, btn) {
    typeFilter = t;
    document.querySelectorAll('#typeTabs button').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    filterKitchen();
}

function filterKitchen() {
    var q = document.getElementById('kitchenSearch').value.toLowerCase();
    var pr = document.getElementById('prioSel').value;
    document.querySelectorAll('.ko-card').forEach(function (card) {
        var okType = (typeFilter === 'all' || card.dataset.type === typeFilter);
        var okPrio = (pr === 'all' || card.dataset.priority === pr);
        var okText = !q || card.textContent.toLowerCase().indexOf(q) > -1;
        card.style.display = (okType && okPrio && okText) ? '' : 'none';
    });
}

function kitchenAction(billNo, newStatus) {
    var msg = newStatus === 'preparing' ? 'Start preparing order #' + billNo + '?' : 'Mark order #' + billNo + ' as ready?';
    if (!confirm(msg)) return;
    fetch('update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bill_no: billNo, status: newStatus, source: 'kitchen' })
    }).then(function (r) { return r.json(); }).then(function (res) {
        if (res.success) { showToast('Order ' + newStatus.toUpperCase(), 'success'); setTimeout(function () { location.reload(); }, 500); }
        else showToast(res.message, 'error');
    });
}

function markAllReady() {
    if (!PREPARING_BILLS.length) { showToast('No orders are in preparing state', 'info'); return; }
    if (!confirm('Mark ALL ' + PREPARING_BILLS.length + ' preparing orders as READY?')) return;
    var done = 0;
    PREPARING_BILLS.forEach(function (b) {
        fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bill_no: b, status: 'ready', source: 'kitchen' })
        }).then(function () { if (++done === PREPARING_BILLS.length) { showToast('All orders marked ready ✅', 'success'); setTimeout(function () { location.reload(); }, 600); } });
    });
}

// ===== IMAGE VIEWER =====
function viewImage(itemId, itemName, billNo) {
    var img = document.getElementById('viewerImage');
    img.src = 'show_image.php?type=image&id=' + itemId + '&t=' + Date.now();
    document.getElementById('viewerInfo').innerHTML = '🧁 <strong>' + itemName + '</strong> &nbsp;|&nbsp; Order #' + billNo;
    document.getElementById('imgViewer').classList.add('show');
}
function closeImageViewer(e, force) {
    if (force || e.target.id === 'imgViewer' || e.target.classList.contains('close-btn')) {
        document.getElementById('imgViewer').classList.remove('show');
        document.getElementById('viewerImage').src = '';
    }
}

// ===== ITEM AUDIO =====
var currentAudioBtn = null;
function playItemAudio(itemId, btn) {
    var audio = document.getElementById('kitchenAudio');
    if (currentAudioBtn === btn && !audio.paused) {
        audio.pause(); audio.currentTime = 0;
        btn.classList.remove('playing'); btn.textContent = '🔊';
        currentAudioBtn = null; return;
    }
    if (currentAudioBtn) { currentAudioBtn.classList.remove('playing'); currentAudioBtn.textContent = '🔊'; }
    audio.src = 'show_image.php?type=audio&id=' + itemId + '&t=' + Date.now();
    audio.play().then(function () {
        btn.classList.add('playing'); btn.textContent = ''; currentAudioBtn = btn;
        showToast('🔊 Playing voice instructions', 'info');
    }).catch(function () { showToast('Audio playback failed', 'error'); });
    audio.onended = function () { btn.classList.remove('playing'); btn.textContent = '🔊'; currentAudioBtn = null; };
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.getElementById('imgViewer').classList.remove('show');
        var audio = document.getElementById('kitchenAudio');
        if (!audio.paused) {
            audio.pause();
            if (currentAudioBtn) { currentAudioBtn.classList.remove('playing'); currentAudioBtn.textContent = '🔊'; currentAudioBtn = null; }
        }
    }
});

initShortcuts({ searchId: 'kitchenSearch' });
</script>
</body>
</html>
