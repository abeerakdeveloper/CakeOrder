<?php
require_once 'db.php';

$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) {
    header('Location: order_list.php');
    exit;
}

$res = mysqli_query($mysqli, "SELECT * FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 ORDER BY id");
$items = array();
while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
if (empty($items)) {
    die("Not found");
}

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

$isSweets = false;
$isLunch = false;
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
foreach ($items as $i) {
    if (!empty($i['image_data'])) {
        $photoId = $i['id'];
        break;
    }
}

// extra photos
$extras = array();
$er = @mysqli_query($mysqli, "SELECT id FROM order_extra_images WHERE bill_no = $billNo ORDER BY id");
if ($er) while ($e = mysqli_fetch_assoc($er)) $extras[] = $e['id'];

function invDate($d)
{
    return date('d-m-Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $billNo; ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #eef0f3;
            color: #222;
            padding: 24px 12px;
        }

        .print-container {
            display: flex;
            justify-content: center;
            align-items: stretch;
            max-width: 1400px;
            margin: 0 auto;
        }

        .sheet {
            flex: 1;
            max-width: 660px;
            background: #fbfbfc;
            box-shadow: 0 4px 22px rgba(0, 0, 0, .14);
            padding: 20px 24px 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cut-line {
            width: 0;
            border-left: 1.5px dashed #aaa;
            margin: 0 14px;
            align-self: stretch;
        }

        .maroon {
            color: #9b1c2e;
        }

        /* header */
        .hd {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 14px;
        }

        .hd .logo {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #9b1c2e;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .hd .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hd h1 {
            color: #9b1c2e;
            font-size: 26px;
            line-height: 1.1;
            font-weight: 800;
        }

        /* info columns */
        .info {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .info table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info .k {
            font-weight: 700;
            padding-right: 6px;
            white-space: nowrap;
        }

        .info .c {
            padding-right: 6px;
        }

        .info .v {}

        .mid {
            text-align: center;
            margin: 4px 0 10px;
        }

        .mid .copy {
            font-weight: 800;
            font-size: 13px;
            color: #9b1c2e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .mid .inv {
            font-size: 12.5px;
            font-weight: 700;
        }

        .mid .inv span {
            font-weight: 600;
            margin-left: 18px;
        }

        .mid .boxes {
            font-size: 12.5px;
            font-weight: 800;
            margin-top: 4px;
        }

        .mid .boxes span {
            margin-left: 8px;
        }

        .invoice-content-body {
            flex: 1 1 auto;
        }

        .invoice-bottom {
            flex: 0 0 auto;
        }

        .photo {
            margin: 4px 0 10px;
        }

        .photo img {
            width: 120px;
            height: 85px;
            object-fit: cover;
            border: 1px solid #ddd;
        }

        /* tables */
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 10px;
        }

        table.lines th {
            background: #f6e7ea;
            color: #9b1c2e;
            text-align: left;
            padding: 6px 8px;
            font-size: 11.5px;
        }

        table.lines th.r,
        table.lines td.r {
            text-align: right;
        }

        table.lines td {
            padding: 5px 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        table.lines .dk {
            font-weight: 700;
            width: 110px;
        }

        table.lines .plain td {
            border-bottom: none;
            padding: 4px 8px;
        }

        /* simple list (lunch / sweets) */
        ul.plist {
            list-style: none;
            max-width: 400px;
            margin: 0 auto 12px;
            font-size: 12px;
        }

        ul.plist li {
            display: flex;
            gap: 12px;
            padding: 4px 0;
        }

        ul.plist li .q {
            width: 20px;
            text-align: right;
        }

        ul.plist li .n {
            flex: 1;
        }

        ul.plist li .a {
            width: 70px;
            text-align: right;
        }

        /* totals */
        .tot {
            display: flex;
            justify-content: flex-end;
            margin: 4px 0 12px;
        }

        .tot table {
            font-size: 12px;
            border-collapse: collapse;
        }

        .tot td {
            padding: 3px 0;
        }

        .tot .k {
            font-weight: 700;
            padding-right: 20px;
            text-align: right;
        }

        .tot .v {
            font-weight: 700;
            min-width: 110px;
        }

        /* stamp + signatures */
        .signrow {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 6px 0 16px;
            position: relative;
        }

        .stamp {
            position: absolute;
            left: 50%;
            top: -24px;
            transform: translateX(-50%) rotate(-4deg);
            border: 2.5px double #1a56c9;
            color: #1a56c9;
            padding: 6px 16px;
            text-align: center;
            font-weight: 800;
            border-radius: 4px;
            background: rgba(255, 255, 255, .7);
        }

        .stamp .d {
            font-size: 9.5px;
            font-weight: 700;
        }

        .stamp .p {
            font-size: 17px;
            letter-spacing: 2px;
        }

        .sig {
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1.4;
        }

        .sig .line {
            width: 95px;
            border-top: 1px solid #444;
            margin: 18px auto 3px;
        }

        .sig.left {
            text-align: left;
        }

        .sig.left .line {
            margin: 18px 0 3px;
            width: 90px;
        }

        /* footer */
        .ft {
            margin: 0 -8px;
            background: #9b1c2e;
            color: #fff;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .ft .sep {
            opacity: .6;
        }

        .no-print {
            max-width: 760px;
            margin: 14px auto 0;
            text-align: center;
        }

        .no-print button {
            padding: 9px 22px;
            border: none;
            border-radius: 8px;
            background: #9b1c2e;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        /* print: horizontal (landscape) A4 with two side-by-side copies on ONE page */
        @page {
            size: A4 landscape;
            margin: 0;
        }

        @media print {
            html, body {
                background: #fff;
                padding: 0;
                margin: 0;
                width: 297mm;
                height: 210mm;
                max-height: 210mm;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .print-container {
                display: flex;
                flex-direction: row;
                width: 297mm;
                height: 210mm;
                max-height: 210mm;
                padding: 4mm 6mm;
                justify-content: space-between;
                align-items: stretch;
                box-sizing: border-box;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                overflow: hidden;
            }

            .sheet {
                box-shadow: none;
                max-width: none;
                width: 48.8%;
                flex: 0 0 48.8%;
                padding: 0 4mm 0 4mm;
                margin: 0;
                box-sizing: border-box;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                height: 100%;
                max-height: 202mm;
                overflow: hidden;
            }

            .cut-line {
                width: 0;
                border-left: 1.5px dashed #888;
                margin: 0 2px;
                align-self: stretch;
                height: 100%;
            }

            .invoice-content-body {
                flex: 1 1 auto;
                min-height: 0;
            }

            .invoice-bottom {
                flex: 0 0 auto;
            }

            .hd {
                margin-bottom: 2mm;
                gap: 2.5mm;
            }

            .hd .logo {
                width: 42px;
                height: 42px;
                font-size: 20px;
            }

            .hd h1 {
                font-size: 18px;
            }

            .info {
                font-size: 9.5px;
                margin-bottom: 2mm;
                gap: 3mm;
            }

            .info table td {
                padding: 1px 0;
            }

            .mid {
                margin: 1mm 0 2mm;
            }

            .mid .copy {
                font-size: 11px;
                margin-bottom: 1px;
            }

            .mid .inv {
                font-size: 10px;
            }

            .mid .inv span {
                margin-left: 8px;
            }

            .mid .boxes {
                font-size: 10px;
                margin-top: 1px;
            }

            .photo {
                margin: 1mm 0 2mm;
            }

            .photo img {
                width: 80px;
                height: 55px;
            }

            table.lines {
                font-size: 9px;
                margin-bottom: 2mm;
            }

            table.lines th {
                padding: 2.5px 4px;
                font-size: 8.5px;
            }

            table.lines td {
                padding: 2px 4px;
                line-height: 1.25;
            }

            table.lines .plain td {
                padding: 2px 4px;
            }

            ul.plist {
                font-size: 9.5px;
                margin: 0 auto 2mm;
                max-width: 320px;
            }

            ul.plist li {
                padding: 1.5px 0;
            }

            .tot {
                margin: 1mm 0 2mm;
            }

            .tot table {
                font-size: 9.5px;
            }

            .tot td {
                padding: 1px 0;
            }

            .tot .k {
                padding-right: 10px;
            }

            .tot .v {
                min-width: 85px;
            }

            .signrow {
                padding: 1.5mm 0 2.5mm;
            }

            .stamp {
                top: -12px;
                padding: 2px 8px;
            }

            .stamp .d {
                font-size: 7px;
            }

            .stamp .p {
                font-size: 12px;
                letter-spacing: 1.5px;
            }

            .sig {
                font-size: 8px;
                line-height: 1.25;
            }

            .sig .line {
                width: 75px;
                margin: 10px auto 2px;
            }

            .sig.left .line {
                width: 70px;
                margin: 10px 0 2px;
            }

            .ft {
                margin: 0 -4mm;
                padding: 3.5px 4px;
                font-size: 8.5px;
                gap: 5px;
            }
        }
    </style>
</head>

<body>

    <?php
    function renderInvoiceCopy($copyType, $branch, $first, $billNo, $payStatus, $INV, $kind, $totalBoxes, $photoId, $extras, $items, $netTotal, $advance, $paid, $balance, $ladi) {
        ob_start();
        ?>
        <!-- header -->
        <div class="hd">
            <div class="logo"><img src="assets/clogo.png" alt=""></div>
            <h1><?php echo htmlspecialchars($branch['name']); ?></h1>
        </div>

        <!-- info -->
        <div class="info">
            <table>
                <tr>
                    <td class="k">Salesman Name</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo htmlspecialchars($first['order_taker'] ? $first['order_taker'] : $first['user']); ?></td>
                </tr>
                <tr>
                    <td class="k">Customer Name</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo htmlspecialchars($first['party_detail']); ?></td>
                </tr>
                <tr>
                    <td class="k">Phone Number</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo htmlspecialchars($first['cell_no']); ?></td>
                </tr>
                <tr>
                    <td class="k">Order Number</td>
                    <td class="c">:</td>
                    <td class="v">#<?php echo $billNo; ?></td>
                </tr>
                <tr>
                    <td class="k">Payment Status</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo $payStatus; ?></td>
                </tr>
            </table>
            <table>
                <tr>
                    <td class="k">Order Branch</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo htmlspecialchars($branch['name']); ?></td>
                </tr>
                <tr>
                    <td class="k">Date</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo invDate($first['inv_date']); ?></td>
                </tr>
                <tr>
                    <td class="k">Branch Phone</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo htmlspecialchars($INV['branch_phone']); ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="height:6px;"></td>
                </tr>
                <tr>
                    <td class="k">Delivery Branch</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo htmlspecialchars($branch['name']); ?></td>
                </tr>
                <tr>
                    <td class="k">Delivery Date</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo invDate($first['deliver_date']); ?></td>
                </tr>
                <tr>
                    <td class="k">Delivery Time</td>
                    <td class="c">:</td>
                    <td class="v"><?php echo date('g:i A', strtotime($first['delivery_time'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- middle title -->
        <div class="mid">
            <div class="copy"><?php echo htmlspecialchars($copyType); ?></div>
            <div class="inv">Invoice Number #<span><?php echo $billNo; ?></span></div>
            <?php if ($kind === 'lunch'): ?><div class="boxes">Lunch Boxes :<span><?php echo $totalBoxes; ?></span></div><?php endif; ?>
            <?php if ($kind === 'sweets'): ?><div class="boxes">Sweet's Boxes :<span><?php echo $totalBoxes; ?></span></div><?php endif; ?>
        </div>

        <div class="invoice-content-body">
            <?php if ($kind === 'cake' && $photoId): ?>
                <div class="photo">
                    <img src="show_image.php?type=thumb&id=<?php echo $photoId; ?>" alt="">
                </div>
            <?php endif; ?>

            <?php if ($kind === 'cake'): ?>
                <!-- ============ CAKE / CUSTOM LAYOUT ============ -->
                <table class="lines">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th style="width:75px;">Uom</th>
                            <th style="width:70px;" class="r">Quantity</th>
                            <th style="width:80px;" class="r">Total</th>
                        </tr>
                    </thead>
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
                                <td><?php echo htmlspecialchars($it['uom']); ?></td>
                                <td class="r"><?php echo intval($it['qty']); ?></td>
                                <td class="r"><?php echo number_format($it['amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="tot">
                    <table>
                        <tr>
                            <td class="k">Total</td>
                            <td class="v"><?php echo number_format($netTotal); ?> (Rs)</td>
                        </tr>
                        <tr>
                            <td class="k">Advance</td>
                            <td class="v"><?php echo number_format($advance + $paid); ?> (Rs)</td>
                        </tr>
                        <tr>
                            <td class="k">Balance</td>
                            <td class="v"><?php echo number_format(max(0, $balance)); ?> (Rs)</td>
                        </tr>
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
                        <tr>
                            <td class="k">Each Box</td>
                            <td class="v">:<?php echo number_format($perBoxTotal); ?></td>
                        </tr>
                        <tr>
                            <td class="k">Total Amount</td>
                            <td class="v">:<?php echo number_format($netTotal); ?></td>
                        </tr>
                        <tr>
                            <td class="k">Advance</td>
                            <td class="v">:<?php echo number_format($advance + $paid); ?></td>
                        </tr>
                        <tr>
                            <td class="k" style="font-weight:400;">Balance</td>
                            <td class="v" style="font-weight:400;">:<?php echo number_format(max(0, $balance)); ?></td>
                        </tr>
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
                        <tr>
                            <td class="k">Advance</td>
                            <td class="v">:<?php echo number_format($advance + $paid); ?></td>
                        </tr>
                        <tr>
                            <td class="k">Total Amount</td>
                            <td class="v" style="font-weight:400;">: Will be after weight</td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="invoice-bottom">
            <!-- stamp + signatures -->
            <div class="signrow">
                <?php if ($payStatus === 'Paid'): ?>
                    <div class="stamp">
                        <div><?php echo htmlspecialchars($branch['name']); ?></div>
                        <div class="d"><?php echo date('j M Y'); ?></div>
                        <div class="p">PAID</div>
                    </div>
                <?php endif; ?>
                <div class="sig left">
                    <div class="line"></div>Salesman<br><span style="font-weight:400;font-size:10px;color:#666;"><?php echo htmlspecialchars($first['order_taker'] ? $first['order_taker'] : $first['user']); ?></span>
                </div>
                <div class="sig">
                    <div class="line"></div>Cashier<br>Name
                </div>
                <div class="sig">
                    <div class="line"></div>Cashier<br>Signature
                </div>
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
        <?php
        return ob_get_clean();
    }
    ?>

    <div class="print-container">
        <div class="sheet">
            <?php echo renderInvoiceCopy('Branch Copy', $branch, $first, $billNo, $payStatus, $INV, $kind, $totalBoxes, $photoId, $extras, $items, $netTotal, $advance, $paid, $balance, $ladi); ?>
        </div>
        <div class="cut-line"></div>
        <div class="sheet">
            <?php echo renderInvoiceCopy('Customer Copy', $branch, $first, $billNo, $payStatus, $INV, $kind, $totalBoxes, $photoId, $extras, $items, $netTotal, $advance, $paid, $balance, $ladi); ?>
        </div>
    </div>

    <div class="no-print"><button onclick="window.print()">🖨 Print Invoice</button></div>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 400);
        };
    </script>
</body>

</html>