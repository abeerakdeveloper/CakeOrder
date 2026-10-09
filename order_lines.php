<?php
// ============================================================
// Order lines and display helpers.
// No database calls in this file, so it can be tested on its own.
// Used by: save_order.php, invoice.php, kitchen_display.php,
// order_list.php, payment.php and receipt.php.
//
// Every saved row keeps the existing money rule:
//   amount = round(unit price x qty)
// Box groups, extra charges and weighed sweet boxes are plain rows
// with their own sale_type, so bill totals, balance and the ledger
// work the same way as before.
// Written for PHP 5.6 and later.
// ============================================================

function ot_type_labels() {
    return array(
        'cake'    => 'Cake',
        'lunch'   => 'Lunch box',
        'sweet'   => 'Sweet box',
        'eatable' => 'Eatable picture',
        'other'   => 'Other',
    );
}

function ot_is_box_type($type) {
    return $type === 'lunch' || $type === 'sweet';
}

// Rows shown as normal kitchen items. NULL = rows saved before order types existed.
function ot_is_item_row($saleType) {
    return $saleType === null || $saleType === '' || $saleType === 'cake'
        || $saleType === 'eatable' || $saleType === 'other';
}

function ot_num($value) {
    return is_numeric($value) ? (float) $value : 0.0;
}

function ot_money($value) {
    return number_format((int) round((float) $value));
}

// 4 -> "4", 1.5 -> "1.5", 2.00 -> "2"
function ot_clean_number($value) {
    $s = number_format((float) $value, 2, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
}

function ot_date_text($ymd) {
    $t = strtotime((string) $ymd);
    return $t ? date('d-m-Y', $t) : '';
}

function ot_time_text($hhmm) {
    $t = strtotime('2000-01-01 ' . (string) $hhmm);
    return $t ? date('g:i A', $t) : '';
}

function ot_uom_text($uom, $measure) {
    $uom = trim((string) $uom);
    if ($uom === 'pound' || $uom === 'pounds') {
        return ot_num($measure) == 1.0 ? 'pound' : 'pounds';
    }
    return $uom;
}

function ot_blank_line($saleType) {
    return array(
        'sale_type' => $saleType, 'inv_id' => 0, 'category' => '', 'qty' => 1, 'price' => 0.0,
        'amount' => 0, 'tiers' => 1, 'uom' => 'pcs', 'flavor' => '', 'shape' => '',
        'cake_message' => '', 'material' => '', 'kitchen_note' => '', 'image_data' => '',
        'audio_data' => '', 'box_group' => null, 'box_qty' => null,
        'id' => 0, 'keep_image' => false, 'keep_audio' => false,
    );
}

/**
 * Turns the posted order into rows (one row per cake_order line).
 * Returns array('lines' => array(...), 'errors' => array(...)).
 *
 * Input keys:
 *   type      cake | lunch | sweet | eatable | other
 *   items     cake, eatable picture or other lines (each may carry kind)
 *   boxes     lunch / sweet box groups: boxes, name, items[name, qty per box, price per piece]
 *   charges   extra charges: label, amount
 */
function ot_build_lines(array $input) {
    $type = (isset($input['type']) && $input['type'] !== '') ? (string) $input['type'] : 'cake';
    $labels = ot_type_labels();
    if (!isset($labels[$type])) {
        return array('lines' => array(), 'errors' => array('Choose an order type first.'));
    }

    $items   = (isset($input['items'])   && is_array($input['items']))   ? $input['items']   : array();
    $boxes   = (isset($input['boxes'])   && is_array($input['boxes']))   ? $input['boxes']   : array();
    $charges = (isset($input['charges']) && is_array($input['charges'])) ? $input['charges'] : array();

    // Which line kinds each order type may contain
    $allowed = array(
        'cake'    => array('cake', 'eatable'),
        'eatable' => array('eatable'),
        'other'   => array('other'),
        'lunch'   => array(),
        'sweet'   => array(),
    );

    $lines  = array();
    $errors = array();

    foreach ($items as $it) {
        $kind = (isset($it['kind']) && $it['kind'] !== '') ? (string) $it['kind'] : $type;
        if (!in_array($kind, $allowed[$type], true)) {
            $errors[] = 'An item does not match this order type.';
            continue;
        }
        $qty   = ot_num(isset($it['qty']) ? $it['qty'] : 1);
        $price = ot_num(isset($it['price']) ? $it['price'] : 0);
        if ($qty <= 0) {
            $errors[] = 'Quantity must be at least 1.';
            continue;
        }
        if ($price < 0) {
            $errors[] = 'Price cannot be negative.';
            continue;
        }

        $line = ot_blank_line($kind);
        $line['qty'] = $qty;
        $line['price'] = $price;
        $line['amount'] = (int) round($price * $qty);
        $line['kitchen_note'] = trim((string) (isset($it['note']) ? $it['note'] : ''));
        $line['image_data'] = (string) (isset($it['image_data']) ? $it['image_data'] : '');
        $line['id'] = isset($it['id']) ? (int) $it['id'] : 0;
        $line['keep_image'] = !empty($it['keep_image']);
        $line['keep_audio'] = !empty($it['keep_audio']);

        if ($kind === 'cake') {
            $name = trim((string) (isset($it['name']) ? $it['name'] : ''));
            if ($name === '') {
                $errors[] = 'Choose a cake for each cake line.';
                continue;
            }
            $tiers = ot_num(isset($it['tiers']) ? $it['tiers'] : 1);
            if ($tiers <= 0) {
                $errors[] = $name . ': enter the weight.';
                continue;
            }
            $uom = trim((string) (isset($it['uom']) ? $it['uom'] : ''));
            $line['category']     = $name;
            $line['inv_id']       = isset($it['inv_id']) ? (int) $it['inv_id'] : 0;
            $line['flavor']       = trim((string) (isset($it['flavor']) ? $it['flavor'] : ''));
            $line['shape']        = trim((string) (isset($it['shape']) ? $it['shape'] : ''));
            $line['uom']          = $uom !== '' ? $uom : 'pcs';
            $line['tiers']        = $tiers;
            $line['cake_message'] = trim((string) (isset($it['cake_message']) ? $it['cake_message'] : ''));
            $line['material']     = trim((string) (isset($it['material']) ? $it['material'] : ''));
            $line['audio_data']   = (string) (isset($it['audio_data']) ? $it['audio_data'] : '');
        } elseif ($kind === 'eatable') {
            // An eatable picture needs its picture: a new upload, or the one already saved (edit)
            if ($line['image_data'] === '' && !($line['id'] > 0 && $line['keep_image'])) {
                $errors[] = 'Add the picture for each eatable picture.';
                continue;
            }
            $size = trim((string) (isset($it['size']) ? $it['size'] : ''));
            $line['category'] = 'Eatable picture' . ($size !== '' ? ' (' . $size . ')' : '');
        } else { // other
            $desc = trim((string) (isset($it['name']) ? $it['name'] : ''));
            if ($desc === '') {
                $errors[] = 'Describe each other item.';
                continue;
            }
            $line['category'] = $desc;
        }
        $lines[] = $line;
    }

    // Box groups (lunch and sweet): every box group is written as one row per item.
    if (ot_is_box_type($type)) {
        if (empty($boxes)) {
            $errors[] = 'Add at least one box group.';
        }
        $groupNo = 0;
        foreach ($boxes as $grp) {
            $groupNo++;
            $count = (int) floor(ot_num(isset($grp['boxes']) ? $grp['boxes'] : 0));
            if ($count < 1) {
                $errors[] = 'Box group ' . $groupNo . ': enter how many boxes.';
                continue;
            }
            $grpItems = (isset($grp['items']) && is_array($grp['items'])) ? $grp['items'] : array();
            $added = 0;
            foreach ($grpItems as $bi) {
                $name = trim((string) (isset($bi['name']) ? $bi['name'] : ''));
                if ($name === '') {
                    continue;
                }
                $perBox = (int) floor(ot_num(isset($bi['qty']) ? $bi['qty'] : 1));
                if ($perBox < 1) {
                    $perBox = 1;
                }
                // Sweet boxes are priced after weighing, so their items carry no price.
                $price = ($type === 'sweet') ? 0.0 : ot_num(isset($bi['price']) ? $bi['price'] : 0);
                if ($price < 0) {
                    $errors[] = 'Price cannot be negative.';
                    continue;
                }
                $qty = $count * $perBox;
                $line = ot_blank_line($type);
                $line['id'] = isset($bi['id']) ? (int) $bi['id'] : 0;
                $line['category'] = $name;
                $line['qty'] = $qty;
                $line['price'] = $price;
                $line['amount'] = (int) round($price * $qty);
                $line['box_group'] = $groupNo;
                $line['box_qty'] = $count;
                $lines[] = $line;
                $added++;
            }
            if ($added === 0) {
                $errors[] = 'Box group ' . $groupNo . ': add at least one item.';
            }
        }
    }

    // Extra charges: one row each, for every order type.
    foreach ($charges as $c) {
        $amount = ot_num(isset($c['amount']) ? $c['amount'] : 0);
        if ($amount == 0) {
            continue;
        }
        if ($amount < 0) {
            $errors[] = 'Extra charges cannot be negative.';
            continue;
        }
        $label = trim((string) (isset($c['label']) ? $c['label'] : ''));
        $line = ot_blank_line('charge');
        $line['id'] = isset($c['id']) ? (int) $c['id'] : 0;
        $line['category'] = ($label !== '') ? $label : 'Extra charge';
        $line['qty'] = 1;
        $line['price'] = $amount;
        $line['amount'] = (int) round($amount);
        $lines[] = $line;
    }

    $itemCount = 0;
    foreach ($lines as $l) {
        if ($l['sale_type'] !== 'charge') {
            $itemCount++;
        }
    }
    if ($itemCount === 0 && empty($errors)) {
        $errors[] = 'Add at least one item.';
    }
    if (!empty($errors)) {
        return array('lines' => array(), 'errors' => $errors);
    }
    return array('lines' => $lines, 'errors' => array());
}

/**
 * Groups box rows (lunch / sweet) for display.
 * Accepts DB rows or built lines. Keys used: sale_type, box_group, box_qty,
 * category, qty, amount, and retail_price (or price).
 */
function ot_box_groups(array $rows) {
    $groups = array();
    foreach ($rows as $r) {
        $sale = isset($r['sale_type']) ? $r['sale_type'] : '';
        if (!ot_is_box_type($sale) || empty($r['box_group'])) {
            continue;
        }
        $g = (int) $r['box_group'];
        if (!isset($groups[$g])) {
            $groups[$g] = array(
                'no' => $g,
                'type' => $sale,
                'boxes' => max(1, (int) $r['box_qty']),
                'items' => array(),
                'each_box' => 0.0,
                'total' => 0.0,
            );
        }
        $boxes = $groups[$g]['boxes'];
        $qty = ot_num($r['qty']);
        $perBox = $qty / $boxes;
        $price = isset($r['retail_price']) ? ot_num($r['retail_price']) : ot_num(isset($r['price']) ? $r['price'] : 0);
        $amount = ot_num(isset($r['amount']) ? $r['amount'] : 0);
        $groups[$g]['items'][] = array(
            'name' => (string) $r['category'],
            'per_box' => $perBox,
            'price' => $price,
            'amount' => $amount,
        );
        $groups[$g]['each_box'] += $price * $perBox;
        $groups[$g]['total'] += $amount;
    }
    ksort($groups);
    return array_values($groups);
}

/**
 * Payment figures for one bill.
 * $total = sum of all row amounts (items, box groups, charges, weighed sweets).
 * $waitingForWeight = sweet boxes exist but no weighed amount is entered yet.
 */
function ot_payment_figures($total, $discount, $advance, $paid, $waitingForWeight) {
    $due = ot_num($total) - ot_num($discount);
    $received = ot_num($advance) + ot_num($paid);
    $balance = $due - $received;
    if ($waitingForWeight) {
        $status = $received > 0 ? 'advance' : 'unpaid';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } elseif ($received > 0) {
        $status = 'advance';
    } else {
        $status = 'unpaid';
    }
    $names = array('paid' => 'Paid', 'advance' => 'Advance', 'unpaid' => 'Unpaid');
    return array(
        'status' => $status,
        'label' => $names[$status],
        'due' => $due,
        'received' => $received,
        'balance' => $balance,
        'waiting' => (bool) $waitingForWeight,
    );
}

// Delivery address is kept inside the notes text by save_order.php
function ot_address_from_notes($notes) {
    $marker = 'DELIVERY ADDR: ';
    $pos = strpos((string) $notes, $marker);
    if ($pos === false) {
        return '';
    }
    $s = substr((string) $notes, $pos + strlen($marker));
    $bar = strpos($s, ' | ');
    return trim($bar === false ? $s : substr($s, 0, $bar));
}

/**
 * Everything the invoice prints, built from the rows of one bill.
 * $rows must be the bill's rows (ordercancel = 0) ordered by id.
 * Each row needs: id, bill_no, sale_type, inv_id, category, qty, amount,
 * retail_price, tiers, uom, flavor, cake_message, material, kitchen_note,
 * notes, box_group, box_qty, has_image, advance, paid, flat_disc, status,
 * party_detail, cell_no, order_taker, user, inv_date, deliver_date,
 * delivery_time, delivery_type, delivery_branch.
 */
function ot_invoice_model(array $rows, array $branch, array $company) {
    if (empty($rows)) {
        return null;
    }
    $first = $rows[0];

    $cakes = array();
    $lines = array();
    $charges = array();
    $weighed = null;
    $hasSweet = false;
    $total = 0.0;
    $discount = 0.0;
    $advance = 0.0;
    $paid = 0.0;

    foreach ($rows as $r) {
        $sale = isset($r['sale_type']) ? $r['sale_type'] : null;
        $amount = ot_num($r['amount']);
        $total += $amount;
        $discount = max($discount, ot_num($r['flat_disc']));
        $advance = max($advance, ot_num($r['advance']));
        $paid = max($paid, ot_num($r['paid']));

        if ($sale === 'sweet') {
            $hasSweet = true;
            continue;
        }
        if ($sale === 'lunch') {
            continue;
        }
        if ($sale === 'charge') {
            $charges[] = array('label' => (string) $r['category'], 'amount' => $amount);
            continue;
        }
        if ($sale === 'weighed') {
            $weighed = $amount;
            continue;
        }

        $isCake = ($sale === 'cake') || (($sale === null || $sale === '') && ot_num($r['inv_id']) > 0);
        if ($isCake) {
            $cakes[] = array(
                'id' => (int) $r['id'],
                'name' => (string) $r['category'],
                'weight' => ot_clean_number($r['tiers']) . ' - ' . ot_uom_text($r['uom'], $r['tiers']),
                'qty' => ot_clean_number($r['qty']),
                'amount' => $amount,
                'flavour' => (string) $r['flavor'],
                'material' => (string) $r['material'],
                'message' => (string) $r['cake_message'],
                'instructions' => (string) $r['kitchen_note'],
                'has_image' => !empty($r['has_image']),
            );
        } else {
            $lines[] = array(
                'id' => (int) $r['id'],
                'name' => (string) $r['category'],
                'qty' => ot_clean_number($r['qty']),
                'amount' => $amount,
                'instructions' => (string) $r['kitchen_note'],
                'has_image' => ($sale === 'eatable') && !empty($r['has_image']),
            );
        }
    }

    $waiting = $hasSweet && $weighed === null;
    $figures = ot_payment_figures($total, $discount, $advance, $paid, $waiting);

    $boxGroups = ot_box_groups($rows);
    $boxTotal = 0;
    foreach ($boxGroups as $g) {
        $boxTotal += $g['boxes'];
    }

    // The address is kept in the notes text only for home delivery (save_order.php)
    $address = ot_address_from_notes($first['notes']);

    $salesman = (isset($first['order_taker']) && $first['order_taker'] !== '') ? $first['order_taker'] : $first['user'];
    $deliveryBranch = (isset($first['delivery_branch']) && $first['delivery_branch'] !== '')
        ? $first['delivery_branch'] : $branch['name'];

    return array(
        'bill_no' => (int) $first['bill_no'],
        'customer' => (string) $first['party_detail'],
        'phone' => (string) $first['cell_no'],
        'salesman' => (string) $salesman,
        'order_branch' => (string) $branch['name'],
        'branch_phone' => isset($company['branch_phone']) ? (string) $company['branch_phone'] : '',
        'invoice_date' => ot_date_text($first['inv_date']),
        'delivery_branch' => (string) $deliveryBranch,
        'delivery_date' => ot_date_text($first['deliver_date']),
        'delivery_time' => ot_time_text($first['delivery_time']),
        'delivery_address' => $address,
        'cakes' => $cakes,
        'lines' => $lines,
        'box_groups' => $boxGroups,
        'box_total' => $boxTotal,
        'has_sweet' => $hasSweet,
        'charges' => $charges,
        'weighed' => $weighed,
        'total' => $total,
        'discount' => $discount,
        'advance' => $advance,
        'paid' => $paid,
        'waiting' => $waiting,
        'payment' => $figures,
        'stamp_paid' => ($figures['status'] === 'paid' && !$waiting && $total > 0),
    );
}

// ============================================================
// Edit rules. An order can be changed until the kitchen starts
// preparing it. This part only plans the change (no database);
// order_store.php writes it and save_order_edit.php runs it.
// ============================================================

// Statuses in which an order can still be changed. Start preparing locks it.
function ot_editable_status($status) {
    return in_array((string) $status, array('pending', 'hold', 'confirmed'), true);
}

// Kind of a saved row. Rows saved before order types existed have sale_type NULL.
function ot_row_kind(array $row) {
    if (isset($row['sale_type']) && $row['sale_type'] !== null && $row['sale_type'] !== '') {
        return (string) $row['sale_type'];
    }
    return (isset($row['inv_id']) && (int) $row['inv_id'] > 0) ? 'cake' : 'other';
}

// Order type of a bill, read from its rows (charges and weighed sweet boxes do not count).
function ot_bill_type(array $rows) {
    $kinds = array();
    foreach ($rows as $r) {
        $kinds[ot_row_kind($r)] = true;
    }
    foreach (array('lunch', 'sweet', 'cake', 'eatable') as $t) {
        if (isset($kinds[$t])) {
            return $t;
        }
    }
    return 'other';
}

// Text added to the end of the notes after the order form: payment and edit stamps.
function ot_notes_suffix($notes) {
    $s = (string) $notes;
    $found = false;
    foreach (array(' | PAY: ', ' | EDITED by ') as $marker) {
        $p = strpos($s, $marker);
        if ($p !== false && ($found === false || $p < $found)) {
            $found = $p;
        }
    }
    return $found === false ? '' : (string) substr($s, $found);
}

// Splits saved notes into occasion, kitchen note and delivery address.
// Reverse of ot_compose_notes(), as save_order.php writes them.
function ot_parse_notes($notes) {
    $s = (string) $notes;
    $suffix = ot_notes_suffix($s);
    if ($suffix !== '') {
        $s = (string) substr($s, 0, strlen($s) - strlen($suffix));
    }
    $occasion = '';
    if (strpos($s, 'OCCASION: ') === 0) {
        $rest = (string) substr($s, strlen('OCCASION: '));
        $bar = strpos($rest, ' | ');
        if ($bar === false) {
            $occasion = trim($rest);
            $s = '';
        } else {
            $occasion = trim((string) substr($rest, 0, $bar));
            $s = (string) substr($rest, $bar + 3);
        }
    }
    $address = '';
    $marker = 'DELIVERY ADDR: ';
    $pos = strpos($s, $marker);
    if ($pos !== false) {
        $address = trim((string) substr($s, $pos + strlen($marker)));
        $s = (string) substr($s, 0, $pos);
    }
    return array(
        'occasion' => $occasion,
        'kitchen' => trim($s, " |"),
        'address' => $address,
        'suffix' => $suffix,
    );
}

// Kitchen note of a saved row: the kitchen_note column, or the text inside notes for older rows.
function ot_kitchen_of_row(array $row) {
    if (isset($row['kitchen_note']) && $row['kitchen_note'] !== null && $row['kitchen_note'] !== '') {
        return (string) $row['kitchen_note'];
    }
    $parsed = ot_parse_notes(isset($row['notes']) ? $row['notes'] : '');
    return $parsed['kitchen'];
}

// Notes text as save_order.php writes it: occasion, kitchen note, delivery address, then any stamps kept.
function ot_compose_notes($kitchenNote, $occasion, $address, $suffix) {
    $s = '';
    if ((string) $occasion !== '') {
        $s = 'OCCASION: ' . $occasion . ' | ';
    }
    $s .= (string) $kitchenNote;
    if ((string) $address !== '') {
        $s .= ' | DELIVERY ADDR: ' . $address;
    }
    return $s . (string) $suffix;
}

// Trimmed text of one form field ('' when missing).
function ot_text(array $input, $key) {
    return (isset($input[$key]) && !is_array($input[$key])) ? trim((string) $input[$key]) : '';
}

// Order header from the form. The same cleaning is used for a new order and for an edit.
function ot_header_from_input(array $input, $branchName) {
    $delivery = (ot_text($input, 'delivery_type') === 'delivery') ? 'delivery' : 'pickup';
    $address = ($delivery === 'delivery') ? ot_text($input, 'delivery_address') : '';
    $customer = ot_text($input, 'party_detail');
    $deliverDate = ot_text($input, 'deliver_date');
    $deliveryTime = ot_text($input, 'delivery_time');
    $priority = ot_text($input, 'priority');
    $source = ot_text($input, 'source');
    $branch = ot_text($input, 'delivery_branch');
    return array(
        'party_detail' => ($customer !== '') ? $customer : 'Walk-in',
        'cell_no' => ot_text($input, 'cell_no'),
        'deliver_date' => ($deliverDate !== '') ? $deliverDate : date('Y-m-d'),
        'delivery_time' => ($deliveryTime !== '') ? $deliveryTime : '12:00',
        'priority' => in_array($priority, array('normal', 'urgent', 'vip'), true) ? $priority : 'normal',
        'flat_disc' => max(0, (int) round(ot_num(isset($input['flat_disc']) ? $input['flat_disc'] : 0))),
        'source' => ($source !== '') ? $source : 'walk-in',
        'delivery_type' => $delivery,
        'delivery_address' => $address,
        'delivery_branch' => ($branch !== '') ? $branch : (string) $branchName,
        'occasion' => ot_text($input, 'occasion'),
    );
}

/**
 * What the edit screen shows: the saved order in the shape the New Order screen uses.
 * $rows = the bill's rows (ordercancel = 0), ordered by id.
 * Unit price for a cake is retail_price / tiers, so the screen's price x weight gives the saved amount back.
 */
function ot_edit_prefill(array $rows, $branchName) {
    $first = $rows[0];
    $type = ot_bill_type($rows);
    $parsed = ot_parse_notes($first['notes']);
    $header = array(
        'party_detail' => (string) $first['party_detail'],
        'cell_no' => (string) $first['cell_no'],
        'deliver_date' => substr((string) $first['deliver_date'], 0, 10),
        'delivery_time' => substr((string) $first['delivery_time'], 0, 5),
        'priority' => ((string) $first['priority'] !== '') ? (string) $first['priority'] : 'normal',
        'source' => ((string) $first['order_type'] !== '') ? (string) $first['order_type'] : 'walk-in',
        'delivery_type' => ($parsed['address'] !== '') ? 'delivery' : 'pickup',
        'delivery_address' => $parsed['address'],
        'delivery_branch' => ((string) $first['delivery_branch'] !== '') ? (string) $first['delivery_branch'] : (string) $branchName,
        'occasion' => $parsed['occasion'],
        'flat_disc' => 0,
        'advance' => 0,
        'paid' => 0,
    );
    foreach ($rows as $r) {
        // advance, paid and discount are stored on every row of the bill; the largest value is the bill's
        $header['flat_disc'] = max($header['flat_disc'], (int) round(ot_num($r['flat_disc'])));
        $header['advance'] = max($header['advance'], (int) round(ot_num($r['advance'])));
        $header['paid'] = max($header['paid'], (int) round(ot_num($r['paid'])));
    }

    $items = array();
    $groups = array();
    $charges = array();
    $weighed = null;
    foreach ($rows as $r) {
        $kind = ot_row_kind($r);
        if ($kind === 'weighed') {
            $weighed = (int) round(ot_num($r['amount']));
            continue;
        }
        if ($kind === 'charge') {
            $charges[] = array(
                'id' => (int) $r['id'],
                'label' => (string) $r['category'],
                'amount' => (int) round(ot_num($r['amount'])),
            );
            continue;
        }
        if (ot_is_box_type($kind)) {
            $g = (int) $r['box_group'];
            $count = max(1, (int) $r['box_qty']);
            if (!isset($groups[$g])) {
                $groups[$g] = array('boxes' => $count, 'items' => array());
            }
            $groups[$g]['items'][] = array(
                'id' => (int) $r['id'],
                'name' => (string) $r['category'],
                'qty' => max(1, (int) round(ot_num($r['qty']) / $count)),
                'price' => ot_num($r['retail_price']),
            );
            continue;
        }
        $tiers = (ot_num($r['tiers']) > 0) ? ot_num($r['tiers']) : 1.0;
        $price = ($kind === 'cake') ? ot_num($r['retail_price']) / $tiers : ot_num($r['retail_price']);
        $name = (string) $r['category'];
        $size = '';
        if ($kind === 'eatable' && preg_match('/^Eatable picture \((.*)\)$/', $name, $m)) {
            $size = $m[1];
            $name = 'Eatable picture';
        }
        $items[] = array(
            'id' => (int) $r['id'],
            'kind' => $kind,
            'inv_id' => (int) $r['inv_id'],
            'name' => $name,
            'size' => $size,
            'price' => $price,
            'qty' => ot_num($r['qty']),
            'tiers' => $tiers,
            'uom' => ((string) $r['uom'] !== '') ? (string) $r['uom'] : 'pcs',
            'flavor' => (string) $r['flavor'],
            'shape' => (string) $r['shape'],
            'cake_message' => (string) $r['cake_message'],
            'material' => (string) $r['material'],
            'note' => ot_kitchen_of_row($r),
            'has_image' => !empty($r['has_image']),
            'has_audio' => !empty($r['has_audio']),
        );
    }
    ksort($groups);
    return array(
        'bill_no' => (int) $first['bill_no'],
        'type' => $type,
        'status' => (string) $first['status'],
        'editable' => ot_editable_status($first['status']),
        'header' => $header,
        'items' => $items,
        'boxes' => array_values($groups),
        'charges' => $charges,
        'weighed' => $weighed,
    );
}

// True when a posted line differs from the saved row it would update.
function ot_line_changed(array $old, array $line) {
    if (ot_row_kind($old) !== $line['sale_type']) {
        return true;
    }
    if ((string) $old['category'] !== (string) $line['category']) {
        return true;
    }
    if (abs(ot_num($old['qty']) - (float) $line['qty']) >= 0.005) {
        return true;
    }
    if (abs(ot_num($old['retail_price']) - (float) $line['price']) >= 0.005) {
        return true;
    }
    if ($line['sale_type'] === 'cake') {
        if (abs(ot_num($old['tiers']) - (float) $line['tiers']) >= 0.005) {
            return true;
        }
        if ((string) $old['uom'] !== (string) $line['uom'] || (string) $old['flavor'] !== (string) $line['flavor']
            || (string) $old['shape'] !== (string) $line['shape'] || (string) $old['cake_message'] !== (string) $line['cake_message']
            || (string) $old['material'] !== (string) $line['material']) {
            return true;
        }
    }
    if (ot_kitchen_of_row($old) !== (string) $line['kitchen_note']) {
        return true;
    }
    if (ot_is_box_type($line['sale_type'])) {
        if ((int) $old['box_group'] !== (int) $line['box_group'] || (int) $old['box_qty'] !== (int) $line['box_qty']) {
            return true;
        }
    }
    if (!empty($line['image_data']) || !empty($line['audio_data'])) {
        return true;
    }
    return false;
}

/**
 * Matches the posted lines with the saved rows.
 *  - a line with id > 0 updates that row (only if something changed);
 *  - a line with id 0 is a new row;
 *  - a saved row that is not posted any more is deleted.
 * Weighed sweet-box amounts are not part of the plan.
 * Returns array('update' => [id => line], 'changed' => [id => bool], 'insert' => [...],
 *               'delete' => [id, ...], 'errors' => [...]).
 */
function ot_plan_edit(array $rows, array $lines) {
    $saved = array();
    foreach ($rows as $r) {
        if (ot_row_kind($r) !== 'weighed') {
            $saved[(int) $r['id']] = $r;
        }
    }
    $plan = array('update' => array(), 'changed' => array(), 'insert' => array(), 'delete' => array(), 'errors' => array());
    foreach ($lines as $line) {
        $id = isset($line['id']) ? (int) $line['id'] : 0;
        if ($id === 0) {
            $plan['insert'][] = $line;
            continue;
        }
        if (!isset($saved[$id])) {
            $plan['errors'][] = 'The order was changed on another screen. Open it again and check the items.';
            continue;
        }
        if (isset($plan['update'][$id])) {
            $plan['errors'][] = 'The same item appears twice. Remove the copy.';
            continue;
        }
        if (ot_row_kind($saved[$id]) !== $line['sale_type']) {
            $plan['errors'][] = 'An item cannot change its kind. Remove it and add a new one.';
            continue;
        }
        $plan['update'][$id] = $line;
        $plan['changed'][$id] = ot_line_changed($saved[$id], $line);
    }
    foreach ($saved as $id => $r) {
        if (!isset($plan['update'][$id])) {
            $plan['delete'][] = $id;
        }
    }
    return $plan;
}

// Bill total after the edit (before the discount). Unchanged rows keep their saved amount.
function ot_plan_total(array $rows, array $plan) {
    $total = 0;
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if (ot_row_kind($r) === 'weighed') {
            $total += (int) round(ot_num($r['amount']));
        } elseif (isset($plan['update'][$id])) {
            $total += !empty($plan['changed'][$id])
                ? (int) $plan['update'][$id]['amount']
                : (int) round(ot_num($r['amount']));
        }
    }
    foreach ($plan['insert'] as $line) {
        $total += (int) $line['amount'];
    }
    return $total;
}

// Plain-language list of what changed, for the change log.
function ot_diff_summary(array $rows, array $plan, array $oldHeader, array $newHeader) {
    $out = array();
    $labels = array(
        'party_detail' => 'Customer name',
        'cell_no' => 'Phone',
        'deliver_date' => 'Delivery date',
        'delivery_time' => 'Delivery time',
        'priority' => 'Priority',
        'flat_disc' => 'Discount',
        'source' => 'Order source',
        'delivery_branch' => 'Delivery branch',
        'occasion' => 'Occasion',
        'delivery_address' => 'Delivery address',
    );
    foreach ($labels as $key => $label) {
        $a = (string) $oldHeader[$key];
        $b = (string) $newHeader[$key];
        if ($a !== $b) {
            $out[] = $label . ': ' . ($a !== '' ? $a : '(none)') . ' changed to ' . ($b !== '' ? $b : '(none)');
        }
    }

    $saved = array();
    foreach ($rows as $r) {
        $saved[(int) $r['id']] = $r;
    }
    foreach ($plan['update'] as $id => $line) {
        if (empty($plan['changed'][$id])) {
            continue;
        }
        $old = $saved[$id];
        $parts = array();
        if ((string) $old['category'] !== (string) $line['category']) {
            $parts[] = 'name ' . $old['category'] . ' changed to ' . $line['category'];
        }
        if (abs(ot_num($old['qty']) - (float) $line['qty']) >= 0.005) {
            $parts[] = 'quantity ' . ot_clean_number($old['qty']) . ' changed to ' . ot_clean_number($line['qty']);
        }
        if (abs(ot_num($old['retail_price']) - (float) $line['price']) >= 0.005) {
            $parts[] = 'price Rs ' . ot_money($old['retail_price']) . ' changed to Rs ' . ot_money($line['price']);
        }
        if ($line['sale_type'] === 'cake') {
            if (abs(ot_num($old['tiers']) - (float) $line['tiers']) >= 0.005) {
                $parts[] = 'weight ' . ot_clean_number($old['tiers']) . ' changed to ' . ot_clean_number($line['tiers']);
            }
            if ((string) $old['flavor'] !== (string) $line['flavor']) {
                $parts[] = 'flavour changed to ' . $line['flavor'];
            }
            if ((string) $old['shape'] !== (string) $line['shape']) {
                $parts[] = 'shape changed to ' . $line['shape'];
            }
            if ((string) $old['cake_message'] !== (string) $line['cake_message']) {
                $parts[] = 'message changed';
            }
            if ((string) $old['material'] !== (string) $line['material']) {
                $parts[] = 'material changed';
            }
        }
        if (ot_is_box_type($line['sale_type'])) {
            if ((int) $old['box_qty'] !== (int) $line['box_qty']) {
                $parts[] = 'boxes ' . (int) $old['box_qty'] . ' changed to ' . (int) $line['box_qty'];
            }
            if ((int) $old['box_group'] !== (int) $line['box_group']) {
                $parts[] = 'box group ' . (int) $old['box_group'] . ' changed to ' . (int) $line['box_group'];
            }
        }
        if (ot_kitchen_of_row($old) !== (string) $line['kitchen_note']) {
            $parts[] = 'kitchen note changed';
        }
        if (!empty($line['image_data'])) {
            $parts[] = 'new photo';
        }
        if (!empty($line['audio_data'])) {
            $parts[] = 'new voice message';
        }
        if (empty($parts)) {
            $parts[] = 'changed';
        }
        $prefix = ($line['sale_type'] === 'charge') ? 'Charge ' : '';
        $out[] = $prefix . $line['category'] . ': ' . implode(', ', $parts);
    }
    foreach ($plan['insert'] as $line) {
        if ($line['sale_type'] === 'charge') {
            $out[] = 'Added charge ' . $line['category'] . ' (Rs ' . ot_money($line['amount']) . ')';
        } elseif (ot_is_box_type($line['sale_type'])) {
            $out[] = 'Added ' . $line['category'] . ' to box group ' . (int) $line['box_group'];
        } else {
            $out[] = 'Added ' . $line['category'] . ' (Rs ' . ot_money($line['amount']) . ')';
        }
    }
    foreach ($plan['delete'] as $id) {
        $old = $saved[$id];
        if ($old['sale_type'] === 'charge') {
            $out[] = 'Removed charge ' . $old['category'] . ' (Rs ' . ot_money($old['amount']) . ')';
        } elseif (ot_is_box_type(ot_row_kind($old))) {
            $out[] = 'Removed ' . $old['category'] . ' from box group ' . (int) $old['box_group'];
        } else {
            $out[] = 'Removed ' . $old['category'] . ' (Rs ' . ot_money($old['amount']) . ')';
        }
    }
    return $out;
}
