<?php
require_once 'db.php';
require_once 'order_lines.php';
requireRole(array(2, 3)); // Kitchen and Admin only

// Show warning if kitchen user tried to access dashboard
$showAccessMsg = isset($_GET['msg']) && $_GET['msg'] == 'no_dashboard_access';

// Optional filter: only orders to deliver today
$todayOnly = isset($_GET['today']) && $_GET['today'] == '1';
$todayWhere = $todayOnly ? " AND co.deliver_date = CURDATE()" : '';


// Long item lists: allow up to 1 MB in GROUP_CONCAT (default is 1024 bytes and would cut long orders)
mysqli_query($mysqli, "SET SESSION group_concat_max_len = 1000000");

// Kitchen item rows: cake, eatable picture and other. Box groups, extra charges and
// weighed sweet boxes are not item lines; box groups are shown in their own block below.
$kitchenRow = "(co.sale_type IS NULL OR co.sale_type IN ('cake','eatable','other'))";
$sql = "SELECT co.bill_no, co.party_detail, co.deliver_date, co.delivery_time,
        co.status, co.priority, MAX(co.dateent) AS dateent, MAX(co.sale_type) AS sale_type,
        GROUP_CONCAT(CASE WHEN $kitchenRow THEN CONCAT(
            'qty = ', co.qty,
            ' , ', co.uom,
            ' = ', IFNULL(CAST(co.tiers AS DOUBLE), 0),
            ' , ', co.category,
            CASE WHEN co.flavor != '' THEN CONCAT(' (', co.flavor, ')') ELSE '' END,
            CASE WHEN co.cake_message != '' THEN CONCAT(' [', co.cake_message, ']') ELSE '' END
        ) ELSE NULL END SEPARATOR '||') AS items,
        GROUP_CONCAT(CASE WHEN $kitchenRow THEN IFNULL(co.notes,'') ELSE NULL END SEPARATOR '||') AS all_notes,
        GROUP_CONCAT(CASE WHEN $kitchenRow THEN IFNULL(co.thumb_data,'') ELSE NULL END SEPARATOR '||') AS thumbs,
        SUM(co.amount) AS total_amount,
        MAX(CASE WHEN co.audio_data IS NOT NULL AND co.audio_data != '' THEN 1 ELSE 0 END) AS has_audio,
        SUM(CASE WHEN $kitchenRow THEN 1 ELSE 0 END) AS item_count
        FROM cake_order co
        WHERE co.status IN ('confirmed', 'preparing')
        AND co.ordercancel = 0$todayWhere
        GROUP BY co.bill_no
        ORDER BY
            FIELD(MAX(co.priority), 'urgent', 'vip', 'normal'),
            co.deliver_date ASC,
            co.delivery_time ASC";

$result = mysqli_query($mysqli, $sql);
$orders = array();
$confirmedCount = 0;
$preparingCount = 0;

if ($result) {
    while ($r = mysqli_fetch_assoc($result)) {
        $orders[] = $r;
        if ($r['status'] == 'confirmed')
            $confirmedCount++;
        if ($r['status'] == 'preparing')
            $preparingCount++;
    }
}

// Lunch and sweet box groups for the cards
$boxByBill = array();
$billIds = array();
foreach ($orders as $o) {
    $billIds[] = intval($o['bill_no']);
}
if (!empty($billIds)) {
    $boxRes = mysqli_query($mysqli, "SELECT bill_no, sale_type, box_group, box_qty, category, qty, retail_price, amount
        FROM cake_order WHERE bill_no IN (" . implode(',', $billIds) . ")
        AND sale_type IN ('lunch','sweet') AND ordercancel = 0 ORDER BY bill_no, box_group, id");
    $boxRowsByBill = array();
    if ($boxRes) {
        while ($b = mysqli_fetch_assoc($boxRes)) {
            $boxRowsByBill[intval($b['bill_no'])][] = $b;
        }
    }
    foreach ($boxRowsByBill as $bn => $brows) {
        $boxByBill[$bn] = ot_box_groups($brows);
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Kitchen Display - BestPOS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Clickable thumbnail */
        .kitchen-item .thumb {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }

        .kitchen-item .thumb:hover {
            transform: scale(1.1);
            border-color: #6c3483;
            box-shadow: 0 4px 12px rgba(108, 52, 131, 0.3);
        }

        /* Audio button on item */
        .audio-mini-btn {
            background: #e67e22;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            font-size: 13px;
            margin-left: 6px;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .audio-mini-btn:hover {
            background: #d35400;
            transform: scale(1.1);
        }

        .audio-mini-btn.playing {
            background: #27ae60;
            animation: pulse-audio 1s infinite;
        }

        @keyframes pulse-audio {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.6);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(39, 174, 96, 0);
            }
        }

        /* Image Viewer Modal */
        .img-viewer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
        }

        .img-viewer-overlay.show {
            display: flex;
        }

        .img-viewer-overlay img {
            max-width: 90vw;
            max-height: 80vh;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .img-viewer-overlay .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            color: #fff;
            border: none;
            min-width: 50px;
            height: 50px;
            padding: 0 18px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .img-viewer-overlay .info {
            color: #fff;
            margin-top: 16px;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.6);
            padding: 8px 16px;
            border-radius: 6px;
        }

        .img-viewer-overlay .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: none;
            width: 50px;
            height: 80px;
            cursor: pointer;
            font-size: 28px;
            border-radius: 8px;
        }

        .img-viewer-overlay .nav-btn:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .img-viewer-overlay .prev-btn {
            left: 20px;
        }

        .img-viewer-overlay .next-btn {
            right: 20px;
        }

        .placeholder-thumb {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: #f5f0fa;
            color: #6c3483;
        }
    </style>
</head>

<body>

    <!-- <div class="topbar" style="background:#e67e22;">
    <h2> Kitchen Display</h2>
    <div class="topbar-right">
        <span style="font-size:14px;"> New: <?php echo $confirmedCount; ?> |  Preparing: <?php echo $preparingCount; ?></span>
        <?php if (!isKitchen()) { ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="order_list.php">Orders</a>
        <?php } ?>
    </div>
</div> -->

    <?php $branch = getBranchInfo(); ?>
    <div class="topbar" style="background:#e67e22;">
        <h2> <?php echo htmlspecialchars($branch['name']); ?> — Kitchen Display</h2>
        <div class="topbar-right">
            <span style="font-size:14px;background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:12px;">
                 New: <strong><?php echo $confirmedCount; ?></strong> |
                 Preparing: <strong><?php echo $preparingCount; ?></strong>
            </span>

            <span style="font-size:13px;background:rgba(255,255,255,0.15);padding:4px 10px;border-radius:12px;">
                <?php echo getRoleName(); ?>
            </span>

            <span style="font-size:13px;"> <?php echo htmlspecialchars($_SESSION['user']); ?></span>

            <?php if (isAdmin()): ?>
                <a href="dashboard.php"
                    style="color:#fff;text-decoration:none;padding:4px 10px;background:rgba(255,255,255,0.15);border-radius:6px;">
                    Dashboard</a>
                <a href="order_list.php"
                    style="color:#fff;text-decoration:none;padding:4px 10px;background:rgba(255,255,255,0.15);border-radius:6px;">
                    Orders</a>
            <?php endif; ?>

            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');"
                style="background:#c0392b;color:#fff;padding:6px 14px;border-radius:6px;text-decoration:none;font-weight:600;">
                 Logout
            </a>
        </div>
    </div>

    <div style="padding:20px;">
        <div style="padding:20px;">
            <?php if ($showAccessMsg): ?>
                <div
                    style="background:#fff3e0;border-left:5px solid #f39c12;padding:14px 20px;border-radius:6px;margin-bottom:16px;font-size:13px;color:#5d4037;">
                     <strong>Access Restricted:</strong> Kitchen staff cannot access Dashboard. Redirected to Kitchen
                    Display.
                    <button onclick="this.parentElement.style.display='none'"
                        style="float:right;background:none;border:none;font-size:16px;cursor:pointer;color:#f39c12;">Close</button>
                </div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div style="text-align:center;padding:80px;color:#999;">
                    <div style="font-size:60px;margin-bottom:16px;"></div>
                    <h3>No pending orders</h3>
                    <p>New orders will appear here automatically.</p>
                </div>
            <?php endif; ?>

            <div class="chip-row" style="margin-bottom:12px;">
                <a class="chip <?php echo $todayOnly ? '' : 'active'; ?>" href="kitchen_display.php">All deliveries</a>
                <a class="chip <?php echo $todayOnly ? 'active' : ''; ?>" href="kitchen_display.php?today=1">Deliver today</a>
            </div>

            <div style="margin-bottom: 20px;">
                <input type="text" id="kitchenSearch" placeholder=" Search bill no or text..." style="width: 100%; max-width: 400px; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px;" oninput="filterKitchen()">
            </div>

            <div class="kitchen-grid">
                <?php foreach ($orders as $o):
                    $isUrgent = $o['priority'] == 'urgent';
                    $itemsList = ($o['items'] === null || $o['items'] === '') ? array() : explode('||', $o['items']);
                    $thumbsList = ($o['thumbs'] === null || $o['thumbs'] === '') ? array() : explode('||', $o['thumbs']);
                    $notesList = array_filter(explode('||', (string) $o['all_notes']));
                    $billBoxes = isset($boxByBill[intval($o['bill_no'])]) ? $boxByBill[intval($o['bill_no'])] : array();
                    $kindLabel = 'Cake order';
                    if (!empty($billBoxes)) {
                        $kindLabel = $billBoxes[0]['type'] === 'sweet' ? 'Sweet boxes' : 'Lunch boxes';
                    } elseif ($o['sale_type'] === 'eatable') {
                        $kindLabel = 'Eatable picture';
                    } elseif ($o['sale_type'] === 'other') {
                        $kindLabel = 'Other';
                    }
                    $elapsed = time() - strtotime($o['dateent']);
                    $elapsedMin = floor($elapsed / 60);
                    ?>
                    <div class="kitchen-card <?php echo $isUrgent ? 'urgent' : ''; ?> fade-in">
                        <div class="kitchen-card-header"
                            style="background:<?php echo $o['status'] == 'preparing' ? '#fff3e0' : '#e3f2fd'; ?>;">
                            <div>
                                <h4>Order #<?php echo $o['bill_no']; ?></h4>
                                <small><?php echo htmlspecialchars($o['party_detail'] ? $o['party_detail'] : 'Walk-in'); ?></small>
                                <div style="font-size:11px;color:#6c3483;font-weight:600;margin-top:2px;"><?php echo $kindLabel; ?></div>
                            </div>
                            <div style="text-align:right;">
                                <?php echo getStatusBadge($o['status']); ?>
                                <?php if ($isUrgent): ?>
                                    <div style="color:#e74c3c;font-size:11px;font-weight:bold;margin-top:4px;"> URGENT</div>
                                <?php endif; ?>
                                <div style="font-size:11px;color:#888;margin-top:4px;"> <?php echo $elapsedMin; ?> min ago
                                </div>
                            </div>
                        </div>

                        <div class="kitchen-card-body">
                            <?php
                            // Get all item IDs and their audio/image flags for this bill
                            $itemDetailsSql = "SELECT id, 
					(image_data IS NOT NULL AND image_data != '') AS has_image,
					(audio_data IS NOT NULL AND audio_data != '') AS has_audio
					FROM cake_order WHERE bill_no = " . intval($o['bill_no']) . " AND (sale_type IS NULL OR sale_type IN ('cake','eatable','other')) AND ordercancel = 0 ORDER BY id";
                            $itemDetailsRes = mysqli_query($mysqli, $itemDetailsSql);
                            $itemDetails = array();
                            if ($itemDetailsRes) {
                                while ($d = mysqli_fetch_assoc($itemDetailsRes))
                                    $itemDetails[] = $d;
                            }
                            ?>
                            <?php foreach ($billBoxes as $grp): ?>
                                <div class="kitchen-item" style="display:block;background:#f7f1fb;">
                                    <div style="font-weight:600;font-size:13px;">
                                        <?php echo $grp['type'] === 'sweet' ? 'Sweet box' : 'Lunch box'; ?> <?php echo $grp['no']; ?>: <?php echo $grp['boxes']; ?> boxes
                                    </div>
                                    <div style="font-size:12px;margin-top:3px;">
                                        <?php
                                        $boxParts = array();
                                        foreach ($grp['items'] as $bi) {
                                            $boxParts[] = htmlspecialchars($bi['name']) . ' x' . ot_clean_number($bi['per_box']);
                                        }
                                        echo implode(', ', $boxParts);
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php foreach ($itemsList as $idx => $item):
                                $thumbHex = isset($thumbsList[$idx]) ? trim($thumbsList[$idx]) : '';
                                $itemId = isset($itemDetails[$idx]) ? $itemDetails[$idx]['id'] : 0;
                                $hasImage = isset($itemDetails[$idx]) ? $itemDetails[$idx]['has_image'] : 0;
                                $hasAudio = isset($itemDetails[$idx]) ? $itemDetails[$idx]['has_audio'] : 0;
                                ?>
                                <div class="kitchen-item">
                                    <?php if (!empty($thumbHex) && $hasImage): ?>
                                        <img src="show_image.php?type=thumb&id=<?php echo $itemId; ?>" class="thumb"
                                            title="Click to view full image"
                                            onclick="viewImage(<?php echo $itemId; ?>, '<?php echo addslashes(trim($item)); ?>', <?php echo $o['bill_no']; ?>)">
                                    <?php else: ?>
                                        <div class="thumb placeholder-thumb"></div>
                                    <?php endif; ?>

                                    <span style="font-size:13px;flex:1;">
                                        <?php 
                                            $safeItem = htmlspecialchars(trim($item));
                                            echo preg_replace('/\[(.*?)\]$/', '<strong style="color: #c0392b;">[$1]</strong>', $safeItem);
                                        ?>
                                    </span>

                                    <?php if ($hasAudio): ?>
                                        <button class="audio-mini-btn" title="Play voice instructions"
                                            onclick="playItemAudio(<?php echo $itemId; ?>, this)">
                                            
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!empty($notesList)): ?>
                                <div style="margin-top:8px;padding:8px;background:#fff8e1;border-radius:6px;font-size:12px;">
                                     <?php echo htmlspecialchars(implode(' | ', $notesList)); ?>
                                </div>
                            <?php endif; ?>

                            <?php // Audio is now per-item with individual buttons ?>

                            <!--  <?php if ($o['has_audio']): ?>
                <button class="btn btn-sm btn-warning" style="margin-top:8px;" onclick="playKitchenAudio(<?php echo $o['bill_no']; ?>)">
                     Play Voice Note
                </button>
                <?php endif; ?> -->

                            <div style="margin-top:8px;font-size:12px;color:#888;">
                                 Deliver: <?php echo date('d M', strtotime($o['deliver_date'])); ?> at
                                <?php echo $o['delivery_time']; ?>
                            </div>
                        </div>

                        <div class="kitchen-card-footer">
                            <span style="font-size:12px;color:#888;"><?php echo $o['item_count']; ?> items</span>
                            <div style="display:flex;gap:6px;">
                                <?php if ($o['status'] == 'confirmed'): ?>
                                    <button class="btn btn-sm btn-warning"
                                        onclick="kitchenAction(<?php echo $o['bill_no']; ?>, 'preparing')">
                                         Start Preparing
                                    </button>
                                <?php elseif ($o['status'] == 'preparing'): ?>
                                    <button class="btn btn-sm btn-success"
                                        onclick="kitchenAction(<?php echo $o['bill_no']; ?>, 'ready')">
                                         Mark Ready
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="toast" id="toast"></div>

        <!-- IMAGE VIEWER MODAL -->
        <div class="img-viewer-overlay" id="imgViewer" onclick="closeImageViewer(event)">
            <button class="close-btn" onclick="closeImageViewer(event, true)">Close</button>
            <img id="viewerImage" src="" alt="">
            <div class="info" id="viewerInfo"></div>
        </div>

        <!-- HIDDEN AUDIO PLAYER -->
        <audio id="kitchenAudio" style="display:none;"></audio>

        <script>
            var lastOrderCount = <?php echo count($orders); ?>;

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

            function playKitchenAudio(billNo) {
                var audio = new Audio('show_image.php?type=audio&bill=' + billNo);
                audio.play().catch(function () { showToast('No audio', 'info'); });
            }

            function showToast(msg, type) {
                var t = document.getElementById('toast');
                t.textContent = msg;
                t.className = 'toast ' + type + ' show';
                setTimeout(function () { t.classList.remove('show'); }, 3000);
            }


            // ===== IMAGE VIEWER =====
            function viewImage(itemId, itemName, billNo) {
                var img = document.getElementById('viewerImage');
                var info = document.getElementById('viewerInfo');
                img.src = 'show_image.php?type=image&id=' + itemId + '&t=' + Date.now();
                info.innerHTML = ' <strong>' + itemName + '</strong> &nbsp;|&nbsp; Order #' + billNo;
                document.getElementById('imgViewer').classList.add('show');
            }

            function closeImageViewer(e, force) {
                // Close only if clicking overlay (not image) or close button
                if (force || e.target.id === 'imgViewer' || e.target.classList.contains('close-btn')) {
                    document.getElementById('imgViewer').classList.remove('show');
                    document.getElementById('viewerImage').src = '';
                }
            }

            // ===== ITEM AUDIO PLAYER =====
            var currentAudioBtn = null;

            function playItemAudio(itemId, btn) {
                var audio = document.getElementById('kitchenAudio');

                // If clicking same button while playing - stop
                if (currentAudioBtn === btn && !audio.paused) {
                    audio.pause();
                    audio.currentTime = 0;
                    btn.classList.remove('playing');
                    btn.textContent = '';
                    currentAudioBtn = null;
                    return;
                }

                // Reset previous button
                if (currentAudioBtn) {
                    currentAudioBtn.classList.remove('playing');
                    currentAudioBtn.textContent = '';
                }

                audio.src = 'show_image.php?type=audio&id=' + itemId + '&t=' + Date.now();

                audio.play().then(function () {
                    btn.classList.add('playing');
                    btn.textContent = '';
                    currentAudioBtn = btn;
                    showToast(' Playing voice instructions', 'info');
                }).catch(function (err) {
                    showToast('Audio playback failed', 'error');
                });

                audio.onended = function () {
                    btn.classList.remove('playing');
                    btn.textContent = '';
                    currentAudioBtn = null;
                };
            }

            // ESC key to close viewer
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    document.getElementById('imgViewer').classList.remove('show');
                    var audio = document.getElementById('kitchenAudio');
                    if (!audio.paused) {
                        audio.pause();
                        if (currentAudioBtn) {
                            currentAudioBtn.classList.remove('playing');
                            currentAudioBtn.textContent = '';
                            currentAudioBtn = null;
                        }
                    }
                }
            });

            function filterKitchen() {
                var q = document.getElementById('kitchenSearch').value.toLowerCase();
                var cards = document.querySelectorAll('.kitchen-card');
                for (var i = 0; i < cards.length; i++) {
                    var text = cards[i].textContent.toLowerCase();
                    if (text.indexOf(q) > -1) {
                        cards[i].style.display = '';
                    } else {
                        cards[i].style.display = 'none';
                    }
                }
            }

            setInterval(function () { location.reload(); }, 60000);
        </script>
</body>

</html>