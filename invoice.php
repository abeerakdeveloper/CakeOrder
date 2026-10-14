<?php
require_once 'db.php';
require_once 'order_lines.php';
require_once 'company.php';
requireRole(array(1, 3)); // POS and admin print invoices

date_default_timezone_set('Asia/Karachi');

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
if (!$billNo) {
    header('Location: order_list.php');
    exit;
}

$res = mysqli_query($mysqli, "SELECT id, bill_no, inv_date, deliver_date, delivery_time, party_detail, cell_no,
    order_taker, user, sale_type, inv_id, category, qty, amount, retail_price, tiers, uom, flavor, cake_message,
    material, kitchen_note, notes, box_group, box_qty, flat_disc, advance, paid, status, payment_method,
    delivery_branch, pay_date,
    (image_data IS NOT NULL AND image_data != '') AS has_image
    FROM cake_order WHERE bill_no = $billNo AND ordercancel = 0 ORDER BY id");
$rows = array();
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
}
if (empty($rows)) {
    die('Invoice not found');
}

$branch = getBranchInfo();
$m = ot_invoice_model($rows, $branch, $COMPANY);
$lastRow = $rows[count($rows) - 1];
$stampDate = ($lastRow['pay_date'] && $lastRow['pay_date'] !== '0000-00-00') ? ot_date_text($lastRow['pay_date']) : date('d-m-Y');

$hasBoxes = !empty($m['box_groups']);
$boxKind = $hasBoxes ? $m['box_groups'][0]['type'] : '';
$boxTitle = ($boxKind === 'sweet') ? 'Sweet Boxes' : 'Lunch Boxes';
$multiGroup = count($m['box_groups']) > 1;
$discount = $m['discount'];
$due = $m['payment']['due'];
$balanceText = $m['waiting'] ? 'After weighing' : 'Rs. ' . ot_money(max(0, $m['payment']['balance']));
$totalLabel = $hasBoxes ? 'Total Amount' : 'Total';
if ($m['waiting']) {
    $totalText = ($due != 0) ? 'Rs. ' . ot_money($due) . ' + after weight' : 'Will be after weight';
} else {
    $totalText = 'Rs. ' . ot_money($due);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice #<?php echo $billNo; ?> - <?php echo h($COMPANY['name']); ?></title>
<style>
    @page { size: A4; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #222; font-size: 13px; margin: 0; background: #eee; }
    .toolbar { text-align: center; padding: 12px; background: #fff; border-bottom: 1px solid #ddd; }
    .toolbar button, .toolbar a { display: inline-block; padding: 9px 18px; margin: 0 4px; border-radius: 6px; font-size: 14px; text-decoration: none; cursor: pointer; }
    .toolbar button { background: #8e1b1b; color: #fff; border: none; }
    .toolbar a { background: #fff; color: #555; border: 1px solid #ccc; }
    .sheet { width: 186mm; height: 273mm; margin: 14px auto; background: #fff; position: relative; padding: 0; overflow: hidden; }
    .logo-img { max-height: 70px; max-width: 230px; }
    .logo-img-text { font-size: 26px; font-weight: bold; color: #8e1b1b; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; }
    .info { display: flex; justify-content: space-between; margin-top: 16px; }
    .info table { border-collapse: collapse; }
    .info td { padding: 3px 4px; vertical-align: top; font-size: 13px; }
    .info td.k { font-weight: bold; white-space: nowrap; }
    .info td.sep { width: 14px; text-align: center; }
    .centre { text-align: center; margin: 18px 0 10px; }
    .centre .copy { font-size: 15px; font-weight: bold; }
    .centre .no { font-size: 13px; font-weight: bold; margin-top: 5px; }
    .cake-block { margin-top: 8px; }
    .cake-pic { max-height: 60mm; max-width: 60mm; display: block; margin-bottom: 8px; border: 1px solid #ddd; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th { background: #f5e8e8; text-align: left; padding: 7px 8px; font-size: 13px; border-bottom: 1px solid #d9b4b4; }
    table.items th.num, table.items td.num { text-align: right; }
    table.items td { padding: 6px 8px; vertical-align: top; font-size: 13px; }
    table.items td.desc div { margin: 3px 0; }
    table.items td.desc b { display: inline-block; min-width: 118px; }
    .small { font-size: 11.5px; color: #555; }
    .boxes-title { font-size: 15px; font-weight: bold; margin: 16px 0 6px; }
    .group-title { font-weight: bold; margin: 12px 0 4px; font-size: 13px; color: #8e1b1b; }
    .box-line { display: flex; padding: 4px 8px; font-size: 13.5px; border-bottom: 1px dotted #e4d4d4; }
    .box-line .q { width: 40px; }
    .box-line .n { flex: 1; }
    .box-line .p { width: 110px; text-align: right; }
    .each-box { padding: 6px 8px; font-weight: bold; font-size: 13.5px; }
    .bottom-row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 16px; }
    .stamp { border: 3px solid #1f4fb4; color: #1f4fb4; padding: 8px 16px; text-align: center; transform: rotate(-5deg); width: 210px; margin-left: 10px; }
    .stamp .s1 { font-weight: bold; font-size: 14px; }
    .stamp .s2 { font-size: 12px; margin-top: 2px; }
    .stamp .s3 { font-size: 22px; font-weight: bold; letter-spacing: 3px; margin-top: 2px; }
    .totals { width: 280px; }
    .totals .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
    .totals .row.bold { font-weight: bold; border-top: 1px solid #ccc; margin-top: 4px; padding-top: 6px; }
    .sign { display: flex; justify-content: flex-end; gap: 42px; margin-top: 44px; }
    .sign div { text-align: center; min-width: 170px; font-size: 13px; }
    .sign .who { min-height: 18px; font-size: 13px; }
    .sign .line { border-top: 1px solid #222; margin-top: 30px; padding-top: 3px; font-weight: bold; text-decoration: underline; }
    .footer { position: absolute; left: 0; right: 0; bottom: 0; background: #8e1b1b; color: #fff; display: flex; justify-content: space-around; padding: 10px 8px; font-size: 12.5px; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .sheet { margin: 0; width: auto; height: 273mm; }
    }
</style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print invoice</button>
    <a href="order_list.php">Back to orders</a>
    <a href="index.php">New order</a>
</div>

<div class="sheet">
    <div class="head">
        <div><?php echo company_logo_html('logo-img'); ?></div>
    </div>

    <div class="info">
        <table>
            <tr><td class="k">Salesman Name</td><td class="sep">:</td><td><?php echo h($m['salesman']); ?></td></tr>
            <tr><td class="k">Customer Name</td><td class="sep">:</td><td><?php echo h($m['customer'] !== '' ? $m['customer'] : 'Walk-in'); ?></td></tr>
            <tr><td class="k">Phone Number</td><td class="sep">:</td><td><?php echo h($m['phone']); ?></td></tr>
            <tr><td class="k">Order Number</td><td class="sep">:</td><td>#<?php echo $billNo; ?></td></tr>
            <tr><td class="k">Payment Status</td><td class="sep">:</td><td><?php echo h($m['payment']['label']); ?></td></tr>
        </table>
        <table>
            <tr><td class="k">Order Branch</td><td class="sep">:</td><td><?php echo h($m['order_branch']); ?></td></tr>
            <tr><td class="k">Date</td><td class="sep">:</td><td><?php echo h($m['invoice_date']); ?></td></tr>
            <?php if ($m['branch_phone'] !== ''): ?>
            <tr><td class="k">Branch Phone</td><td class="sep">:</td><td><?php echo h($m['branch_phone']); ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="3" style="height:10px;"></td></tr>
            <tr><td class="k">Delivery Branch</td><td class="sep">:</td><td><?php echo h($m['delivery_branch']); ?></td></tr>
            <tr><td class="k">Delivery Date</td><td class="sep">:</td><td><?php echo h($m['delivery_date']); ?></td></tr>
            <tr><td class="k">Delivery Time</td><td class="sep">:</td><td><?php echo h($m['delivery_time']); ?></td></tr>
            <?php if ($m['delivery_address'] !== ''): ?>
            <tr><td class="k">Address</td><td class="sep">:</td><td><?php echo h($m['delivery_address']); ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="centre">
        <div class="copy">Branch Copy</div>
        <div class="no">Invoice Number : #<?php echo $billNo; ?></div>
    </div>

    <?php foreach ($m['cakes'] as $cake): ?>
    <div class="cake-block">
        <?php if ($cake['has_image']): ?>
        <img class="cake-pic" src="show_image.php?type=image&amp;id=<?php echo $cake['id']; ?>" alt="Cake reference">
        <?php endif; ?>
        <table class="items">
            <thead>
                <tr><th>Description</th><th>Weight</th><th>Quantity</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="desc">
                        <div><b>Product</b> : <?php echo h($cake['name']); ?></div>
                        <?php if ($cake['flavour'] !== ''): ?><div><b>Flavours</b> : <?php echo h($cake['flavour']); ?></div><?php endif; ?>
                        <?php if ($cake['material'] !== ''): ?><div><b>Material</b> : <?php echo h($cake['material']); ?></div><?php endif; ?>
                        <?php if ($cake['message'] !== ''): ?><div><b>Cake Message</b> : <?php echo h($cake['message']); ?></div><?php endif; ?>
                        <?php if ($cake['instructions'] !== ''): ?><div><b>Cake Instructions</b> : <?php echo h($cake['instructions']); ?></div><?php endif; ?>
                    </td>
                    <td><?php echo h($cake['weight']); ?></td>
                    <td><?php echo h($cake['qty']); ?></td>
                    <td class="num"><?php echo ot_money($cake['amount']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <?php if ($hasBoxes): ?>
    <div class="boxes-title"><?php echo $boxTitle; ?> : <?php echo (int) $m['box_total']; ?></div>
    <?php foreach ($m['box_groups'] as $g): ?>
        <?php if ($multiGroup): ?>
        <div class="group-title">Box group <?php echo $g['no']; ?> &middot; <?php echo (int) $g['boxes']; ?> boxes</div>
        <?php endif; ?>
        <?php foreach ($g['items'] as $it): ?>
        <div class="box-line">
            <span class="q"><?php echo ot_clean_number($it['per_box']); ?></span>
            <span class="n"><?php echo h($it['name']); ?></span>
            <?php if ($g['type'] !== 'sweet'): ?><span class="p"><?php echo ot_money($it['price']); ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($g['type'] !== 'sweet'): ?>
        <div class="each-box">Each Box : <?php echo ot_money($g['each_box']); ?><?php if ($multiGroup): ?> &nbsp;&middot;&nbsp; Group total : <?php echo ot_money($g['total']); ?><?php endif; ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($m['lines']) || !empty($m['charges']) || $m['weighed'] !== null): ?>
    <table class="items">
        <thead>
            <tr><th>Description</th><th>Weight</th><th>Quantity</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($m['lines'] as $ln): ?>
            <tr>
                <td class="desc">
                    <div><b><?php echo h($ln['name']); ?></b></div>
                    <?php if ($ln['instructions'] !== ''): ?><div class="small"><?php echo h($ln['instructions']); ?></div><?php endif; ?>
                    <?php if ($ln['has_image']): ?><img class="cake-pic" style="max-height:45mm;" src="show_image.php?type=image&amp;id=<?php echo $ln['id']; ?>" alt="Picture"><?php endif; ?>
                </td>
                <td>&nbsp;</td>
                <td><?php echo h($ln['qty']); ?></td>
                <td class="num"><?php echo ot_money($ln['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($m['weighed'] !== null): ?>
            <tr>
                <td class="desc"><div><b>Sweet boxes (after weight)</b></div></td>
                <td>&nbsp;</td>
                <td>1</td>
                <td class="num"><?php echo ot_money($m['weighed']); ?></td>
            </tr>
            <?php endif; ?>
            <?php foreach ($m['charges'] as $ch): ?>
            <tr>
                <td class="desc"><div><?php echo h($ch['label']); ?></div></td>
                <td>&nbsp;</td>
                <td>1</td>
                <td class="num"><?php echo ot_money($ch['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="bottom-row">
        <div>
            <?php if ($m['stamp_paid']): ?>
            <div class="stamp">
                <div class="s1"><?php echo h($COMPANY['name']); ?></div>
                <div class="s2"><?php echo h(strtoupper($stampDate)); ?></div>
                <div class="s3">PAID</div>
            </div>
            <?php endif; ?>
        </div>
        <div class="totals">
            <div class="row"><span><?php echo $totalLabel; ?></span><span><?php echo h($totalText); ?></span></div>
            <?php if ($discount > 0): ?>
            <div class="row"><span>Discount</span><span>- Rs. <?php echo ot_money($discount); ?></span></div>
            <?php endif; ?>
            <div class="row"><span>Advance</span><span>Rs. <?php echo ot_money($m['advance']); ?></span></div>
            <?php if ($m['paid'] > 0): ?>
            <div class="row"><span>Paid</span><span>Rs. <?php echo ot_money($m['paid']); ?></span></div>
            <?php endif; ?>
            <div class="row bold"><span>Balance</span><span><?php echo h($balanceText); ?></span></div>
        </div>
    </div>

    <div class="sign">
        <div>
            <div class="who"><?php echo h($m['salesman']); ?></div>
            <div class="line">Cashier Name</div>
        </div>
        <div>
            <div class="who">&nbsp;</div>
            <div class="line">Cashier Signature</div>
        </div>
    </div>

    <div class="footer">
        <span>UAN: <?php echo h($COMPANY['uan']); ?></span>
        <span><?php echo h($COMPANY['website']); ?></span>
        <span><?php echo h($COMPANY['facebook']); ?></span>
    </div>
</div>

</body>
</html>
