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
            if ($line['image_data'] === '') {
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

    $address = '';
    if (isset($first['delivery_type']) && $first['delivery_type'] === 'delivery') {
        $address = ot_address_from_notes($first['notes']);
    }

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
