<?php
require_once 'db.php';

$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) { header('Location: order_list.php'); exit; }

$res = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 ORDER BY id");
$items = array();
while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
if (empty($items)) { die("Not found"); }

// ---- invoice footer / branch contact (edit here) ----
$INV = array(
    'uan'          => 'UAN: 111-11-BAKERS (2253)',
    'web'          => 'www.salmanbakers.com',
    'fb'           => 'facebook.com/salmanbakers',
    'branch_phone' => '091-1234567',
);

$first = $items[0];
$branch = getBranchInfo();

$totalAmount = 0;
foreach ($items as $i) $totalAmount += $i['amount'];
$netTotal = $totalAmount - $first['flat_disc'];
$advance  = intval($first['advance']);
$paid     = intval($first['paid']);
$balance  = $netTotal - $advance - $paid;

$isSweets = false; $isLunch = false;
foreach ($items as $i) {
    if (stripos($i['notes'], 'Sweets Box Set') !== false) $isSweets = true;
    if (stripos($i['notes'], 'Lunch Box Set') !== false)  $isLunch  = true;
}
$kind = $isSweets ? 'sweets' : ($isLunch ? 'lunch' : 'cake');

if ($balance <= 0 && $netTotal > 0)      $payStatus = 'Paid';
elseif ($advance > 0 || $paid > 0)       $payStatus = 'Advance';
else                                     $payStatus = $netTotal > 0 ? 'Unpaid' : 'Advance';
if ($first['status'] === 'paid')         $payStatus = 'Paid';

// lunch/sweets box count (sum of set boxes from notes)
$boxSets = array();
foreach ($items as $i) {
    if (preg_match('/Set (\d+) • (\d+) boxes/', $i['notes'], $m)) $boxSets[$m[1]] = intval($m[2]);
}
$totalBoxes = array_sum($boxSets);

// ladi / material from notes
$ladi = '';
if (preg_match('/LADI: ([^|]+)/', $first['notes'], $m)) $ladi = trim($m[1]);

// first attached photo (if any)
$photoId = 0;
foreach ($items as $i) { if (!empty($i['image_data'])) { $photoId = $i['id']; break; } }

// extra photos
$extras = array();
$er = @mysqli_query($mysqli, "SELECT id FROM order_extra_images WHERE bill_no = $billNo ORDER BY id");
if ($er) while ($e = mysqli_fetch_assoc($er)) $extras[] = $e['id'];

function invDate($d) { return date('d-m-Y', strtotime($d)); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice #<?php echo $billNo; ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #eef0f3; color: #222; padding: 24px 12px; }
.sheet { max-width: 760px; margin: 0 auto; background: #fbfbfc; box-shadow: 0 4px 22px rgba(0,0,0,.14); padding: 34px 40px 0; }
.maroon { color: #9b1c2e; }

/* header */
.hd { display: flex; gap: 16px; align-items: center; margin-bottom: 20px; }
.hd .logo { width: 84px; height: 84px; border-radius: 50%; background: #9b1c2e; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 40px; flex-shrink: 0; }
.hd h1 { color: #9b1c2e; font-size: 34px; line-height: 1.05; font-weight: 800; }

/* info columns */
.info { display: flex; justify-content: space-between; gap: 30px; font-size: 13px; margin-bottom: 22px; }
.info table td { padding: 2.5px 0; vertical-align: top; }
.info .k { font-weight: 700; padding-right: 8px; white-space: nowrap; }
.info .c { padding-right: 8px; }
.info .v { }

.mid { text-align: center; margin: 6px 0 18px; }
.mid .copy { font-weight: 700; font-size: 15px; margin-bottom: 6px; }
.mid .inv { font-size: 14px; font-weight: 700; }
.mid .inv span { font-weight: 600; margin-left: 26px; }
.mid .boxes { font-size: 14px; font-weight: 800; margin-top: 6px; }
.mid .boxes span { margin-left: 10px; }

.photo { margin: 4px 0 14px; }
.photo img { width: 150px; height: 110px; object-fit: cover; border: 1px solid #ddd; }
.photo .more img { width: 64px; height: 48px; margin: 6px 6px 0 0; }

/* tables */
table.lines { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 16px; }
table.lines th { background: #f6e7ea; color: #9b1c2e; text-align: left; padding: 7px 10px; font-size: 12.5px; }
table.lines th.r, table.lines td.r { text-align: right; }
table.lines td { padding: 6px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
table.lines .dk { font-weight: 700; width: 130px; }
table.lines .plain td { border-bottom: none; padding: 5px 10px; }

/* simple list (lunch / sweets) */
ul.plist { list-style: none; max-width: 420px; margin: 0 auto 18px; font-size: 13.5px; }
ul.plist li { display: flex; gap: 14px; padding: 5px 0; }
ul.plist li .q { width: 20px; text-align: right; }
ul.plist li .n { flex: 1; }
ul.plist li .a { width: 70px; text-align: right; }

/* totals */
.tot { display: flex; justify-content: flex-end; margin: 6px 0 20px; }
.tot table { font-size: 13.5px; border-collapse: collapse; }
.tot td { padding: 4px 0; }
.tot .k { font-weight: 700; padding-right: 26px; text-align: right; }
.tot .v { font-weight: 700; min-width: 130px; }

/* stamp + signatures */
.signrow { display: flex; justify-content: space-between; align-items: flex-end; padding: 10px 0 30px; position: relative; }
.stamp { position: absolute; left: 50%; top: -34px; transform: translateX(-50%) rotate(-4deg); border: 3px double #1a56c9; color: #1a56c9; padding: 8px 22px; text-align: center; font-weight: 800; border-radius: 4px; background: rgba(255,255,255,.6); }
.stamp .d { font-size: 11px; font-weight: 700; }
.stamp .p { font-size: 22px; letter-spacing: 3px; }
.sig { text-align: center; font-size: 11.5px; font-weight: 700; line-height: 1.5; }
.sig .line { width: 120px; border-top: 1px solid #444; margin: 26px auto 4px; }
.sig.left { text-align: left; }
.sig.left .line { margin: 26px 0 4px; width: 110px; }

/* footer */
.ft { margin: 0 -40px; background: #9b1c2e; color: #fff; padding: 10px 26px; display: flex; align-items: center; justify-content: center; gap: 26px; font-size: 12.5px; font-weight: 600; }
.ft .sep { opacity: .6; }
.no-print { max-width: 760px; margin: 14px auto 0; text-align: center; }
.no-print button { padding: 9px 22px; border: none; border-radius: 8px; background: #9b1c2e; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; }
@media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; } .no-print { display: none; } }
</style>
</head>
<body>

<div class="sheet">
    <!-- header -->
    <div class="hd">
        <div class="logo">🌾</div>
        <h1><?php echo htmlspecialchars($branch['name']); ?></h1>
    </div>

    <!-- info -->
    <div class="info">
        <table>
            <tr><td class="k">Salesman Name</td><td class="c">:</td><td class="v"><?php echo htmlspecialchars($first['order_taker'] ? $first['order_taker'] : $first['user']); ?></td></tr>
            <tr><td class="k">Customer Name</td><td class="c">:</td><td class="v"><?php echo htmlspecialchars($first['party_detail']); ?></td></tr>
            <tr><td class="k">Phone Number</td><td class="c">:</td><td class="v"><?php echo htmlspecialchars($first['cell_no']); ?></td></tr>
            <tr><td class="k">Order Number</td><td class="c">:</td><td class="v">#<?php echo $billNo; ?></td></tr>
            <tr><td class="k">Payment Status</td><td class="c">:</td><td class="v"><?php echo $payStatus; ?></td></tr>
        </table>
        <table>
            <tr><td class="k">Order Branch</td><td class="c">:</td><td class="v"><?php echo htmlspecialchars($branch['name']); ?></td></tr>
            <tr><td class="k">Date</td><td class="c">:</td><td class="v"><?php echo invDate($first['inv_date']); ?></td></tr>
            <tr><td class="k">Branch Phone</td><td class="c">:</td><td class="v"><?php echo htmlspecialchars($INV['branch_phone']); ?></td></tr>
            <tr><td colspan="3" style="height:8px;"></td></tr>
            <tr><td class="k">Delivery Branch</td><td class="c">:</td><td class="v"><?php echo htmlspecialchars($branch['name']); ?></td></tr>
            <tr><td class="k">Delivery Date</td><td class="c">:</td><td class="v"><?php echo invDate($first['deliver_date']); ?></td></tr>
            <tr><td class="k">Delivery Time</td><td class="c">:</td><td class="v"><?php echo date('g:i A', strtotime($first['delivery_time'])); ?></td></tr>
        </table>
    </div>

    <!-- middle title -->
    <div class="mid">
        <div class="copy">Branch Copy</div>
        <div class="inv">Invoice Number #<span><?php echo $billNo; ?></span></div>
        <?php if ($kind === 'lunch'): ?><div class="boxes">Lunch Boxes :<span><?php echo $totalBoxes; ?></span></div><?php endif; ?>
        <?php if ($kind === 'sweets'): ?><div class="boxes">Sweet's Boxes :<span><?php echo $totalBoxes; ?></span></div><?php endif; ?>
    </div>

    <?php if ($kind === 'cake' && $photoId): ?>
    <div class="photo">
        <img src="show_image.php?type=thumb&id=<?php echo $photoId; ?>" alt="">
        <?php if (!empty($extras)): ?>
        <div class="more"><?php foreach ($extras as $x): ?><img src="show_image.php?type=extrathumb&id=<?php echo $x; ?>" alt=""><?php endforeach; ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($kind === 'cake'): ?>
    <!-- ============ CAKE / CUSTOM LAYOUT ============ -->
    <table class="lines">
        <thead><tr><th>Description</th><th style="width:90px;">Weight</th><th style="width:80px;" class="r">Quantity</th><th style="width:90px;" class="r">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it):
            $noteClean = preg_replace('/^(OCCASION: .*? \| )?(LADI: .*? \| )?(DELIVERY ADDR: .*)?$/', '', (string)$it['notes']);
            $noteClean = trim(str_replace(array('DELIVERY ADDR:'), '', preg_replace('/OCCASION: .*?(\||$)/', '', $noteClean)));
        ?>
            <tr>
                <td>
                    <div><span class="dk">Product&nbsp;&nbsp;:</span> <?php echo htmlspecialchars($it['category']); ?></div>
                    <?php if ($it['flavor']): ?><div><span class="dk">Flavours&nbsp;&nbsp;:</span> <?php echo htmlspecialchars($it['flavor']); ?></div><?php endif; ?>
                    <?php if ($ladi): ?><div><span class="dk">Material&nbsp;&nbsp;:</span> <?php echo htmlspecialchars($ladi); ?></div><?php endif; ?>
                    <?php if ($it['cake_message']): ?><div><span class="dk">Cake Message&nbsp;&nbsp;:</span> <?php echo htmlspecialchars($it['cake_message']); ?></div><?php endif; ?>
                    <?php if ($noteClean !== ''): ?><div><span class="dk">Cake Instructions&nbsp;&nbsp;:</span> <?php echo htmlspecialchars(strtoupper($noteClean)); ?></div><?php endif; ?>
                </td>
                <td><?php echo rtrim(rtrim(number_format($it['tiers'], 1), '0'), '.') . ' - ' . htmlspecialchars($it['uom']); ?></td>
                <td class="r"><?php echo intval($it['qty']); ?></td>
                <td class="r"><?php echo number_format($it['amount']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tot">
        <table>
            <tr><td class="k">Total</td><td class="v"><?php echo number_format($netTotal); ?> (Rs)</td></tr>
            <tr><td class="k">Advance</td><td class="v"><?php echo number_format($advance + $paid); ?> (Rs)</td></tr>
            <tr><td class="k">Balance</td><td class="v"><?php echo number_format(max(0, $balance)); ?> (Rs)</td></tr>
        </table>
    </div>

    <?php elseif ($kind === 'lunch'): ?>
    <!-- ============ LUNCH BOX LAYOUT ============ -->
    <?php
    $perBoxTotal = 0;
    $setBoxesFirst = $totalBoxes > 0 ? $totalBoxes : 1;
    ?>
    <ul class="plist">
        <?php foreach ($items as $it):
            if ($it['category'] === 'Extra Charges') continue;
            $perBoxQty = round($it['qty'] / $setBoxesFirst, 2);
            $perLine   = $it['price'] * $perBoxQty;
            $perBoxTotal += $perLine;
        ?>
        <li><span class="q"><?php echo rtrim(rtrim(number_format($perBoxQty, 2), '0'), '.'); ?></span><span class="n"><?php echo htmlspecialchars($it['category']); ?></span><span class="a"><?php echo number_format($perLine); ?></span></li>
        <?php endforeach; ?>
    </ul>
    <div class="tot">
        <table>
            <tr><td class="k">Each Box</td><td class="v">:<?php echo number_format($perBoxTotal); ?></td></tr>
            <tr><td class="k">Total Amount</td><td class="v">:<?php echo number_format($netTotal); ?></td></tr>
            <tr><td class="k">Advance</td><td class="v">:<?php echo number_format($advance + $paid); ?></td></tr>
            <tr><td class="k" style="font-weight:400;">Balance</td><td class="v" style="font-weight:400;">:<?php echo number_format(max(0, $balance)); ?></td></tr>
        </table>
    </div>

    <?php else: ?>
    <!-- ============ SWEETS BOX LAYOUT ============ -->
    <ul class="plist">
        <?php foreach ($items as $it):
            if ($it['category'] === 'Extra Charges') continue;
            $perBoxQty = $totalBoxes > 0 ? round($it['qty'] / $totalBoxes, 2) : $it['qty'];
        ?>
        <li><span class="q"><?php echo rtrim(rtrim(number_format($perBoxQty, 2), '0'), '.'); ?></span><span class="n"><?php echo htmlspecialchars($it['category']); ?></span></li>
        <?php endforeach; ?>
    </ul>
    <div class="tot">
        <table>
            <tr><td class="k">Advance</td><td class="v">:<?php echo number_format($advance + $paid); ?></td></tr>
            <tr><td class="k">Total Amount</td><td class="v" style="font-weight:400;">: Will be after weight</td></tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- stamp + signatures -->
    <div class="signrow">
        <?php if ($payStatus === 'Paid'): ?>
        <div class="stamp">
            <div><?php echo htmlspecialchars($branch['name']); ?></div>
            <div class="d"><?php echo date('j M Y'); ?></div>
            <div class="p">PAID</div>
        </div>
        <?php endif; ?>
        <div class="sig left"><div class="line"></div>Salesman<br><span style="font-weight:400;font-size:10.5px;color:#666;"><?php echo htmlspecialchars($first['order_taker'] ? $first['order_taker'] : $first['user']); ?></span></div>
        <div class="sig"><div class="line"></div>Cashier<br>Name</div>
        <div class="sig"><div class="line"></div>Cashier<br>Signature</div>
    </div>

    <!-- footer -->
    <div class="ft">
        <span>📞 <?php echo htmlspecialchars($INV['uan']); ?></span>
        <span class="sep">|</span>
        <span>🌐 <?php echo htmlspecialchars($INV['web']); ?></span>
        <span class="sep">|</span>
        <span>ⓕ <?php echo htmlspecialchars($INV['fb']); ?></span>
    </div>
</div>

<div class="no-print"><button onclick="window.print()">🖨 Print Invoice</button></div>
<script>window.onload = function () { setTimeout(function () { window.print(); }, 400); };</script>
</body>
</html>
