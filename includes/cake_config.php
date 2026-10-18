<?php

/**
 * Predefined CAKE categories and their combobox data.
 * Screens read ONLY this file — edit freely, no DB change needed.
 *
 * Structure per category:
 * name : category label (saved in cake_order.category)
 * icon : emoji tile icon
 * price : default price per unit (Rs.)
 * flavors: flavor => price override (0 = keep category price)
 * sizes : size labels; the leading number + word become tiers + uom
 * ladi : ladi options
 */
function cakeConfig()
{
    $stdSizes = array(
        'Pound',
        'Kg',
        'Piece',
    );
    $stdLadi = array('Lotus', 'Red Velvent ', 'Vanilla', 'Chocolate', 'Brownie', 'N/A');

    return array(
        'categories' => array(
            array(
                'name' => 'Fresh Cream Cake',
                'icon' => '◉',
                'price' => 760,
                'flavors' => array(
                    'Pine Apple' => 760,
                    'Black Forest' => 760,
                    'Chocolate Chip' => 760,
                    'Fruit Cocktail' => 760,
                    'Caramel Fresh Cream' => 760,
                    'Coffee Chocolate' => 760,
                    'Mango' => 760,
                    'Strawberry' => 760
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Mousse Cake',
                'icon' => '◎',
                'price' => 760,
                'flavors' => array(
                    'Vanilla' => 760,
                    'Strawberry' => 760,
                    'Caramel' => 760,
                    'Chocolate Mousse' => 760,
                    'Chocolate Chip' => 760,
                    'Kitkat Chocolate' => 760,
                    'Oreo Chocolate' => 760,
                    'Vanilla Oreo' => 760,
                    'Vanilla Almond' => 760,
                    'Walnut Mousse' => 760
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Café Cake',
                'icon' => '◍',
                'price' => 1000,
                'flavors' => array(
                    'Belgium Cake' => 1000,
                    'Three Milky' => 1000,
                    'Red Velvet' => 1000,
                    'Ferrero Rocher' => 1000,
                    'Kitkat' => 1000,
                    'Cadbury' => 1000,
                    'Caramel' => 1000,
                    'Lotus' => 1000
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Butter Cream Cake',
                'icon' => '⬔',
                'price' => 760,
                'flavors' => array(
                    'Chocolate Icing' => 760,
                    'Vanilla Icing' => 760,
                    'Strawberry Icing' => 760,
                    'Coffee Fudge' => 760,
                    'Chocolate Fudge' => 760,
                    'Lemon Tart' => 760,
                    'Brownie Syrup' => 760,
                    'Rich Plum Cake' => 930
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Fresh Brownie Cake',
                'icon' => '⬓',
                'price' => 810,
                'flavors' => array(
                    'Oreo Brownie' => 810,
                    'Cadbury Brownie' => 810,
                    'Kitkat Brownie' => 810
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Ice Cream Cake',
                'icon' => '◐',
                'price' => 850,
                'flavors' => array(
                    'Kulfa' => 850,
                    'Vanilla' => 850,
                    'Chocolate Chip' => 850,
                    'Strawberry' => 850,
                    'Mango' => 850,
                    'Pistachio' => 850,
                    'Caramel' => 850,
                    'Coconut' => 850
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Chicken Cake',
                'icon' => '⬙',
                'price' => 600,
                'flavors' => array(
                    'Chicken Cake' => 600
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            // --- NEW CATEGORIES ADDED ---
            array(
                'name' => 'Cream + Fondant Cake',
                'icon' => '◈',
                'price' => 810,
                'flavors' => array(
                    'Pine Apple' => 810,
                    'Black Forest' => 810,
                    'Chocolate Chip' => 810,
                    'Fruit Cocktail' => 810,
                    'Caramel Fresh Cream' => 810,
                    'Coffee Chocolate' => 810,
                    'Mango' => 810,
                    'Strawberry' => 810,
                    'Chocolate Icing' => 810,
                    'Vanilla Icing' => 810,
                    'Strawberry Icing' => 810,
                    'Coffee Fudge' => 810,
                    'Chocolate Fudge' => 810,
                    'Lemon Tart' => 810
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Fresh Cream + Picture Cake',
                'icon' => '▣',
                'price' => 710,
                'flavors' => array(
                    'Pine Apple' => 710,
                    'Black Forest' => 710,
                    'Chocolate Chip' => 710,
                    'Fruit Cocktail' => 710,
                    'Caramel Fresh Cream' => 710,
                    'Coffee Chocolate' => 710,
                    'Mango' => 710,
                    'Strawberry' => 710
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),


            array(
                'name' => 'Fondant Cake',
                'icon' => '⬣',
                'price' => 900,
                'flavors' => array(
                    'Pine Apple' => 900,
                    'Black Forest' => 900,
                    'Chocolate Chip' => 900,
                    'Fruit Cocktail' => 900,
                    'Caramel Fresh Cream' => 900,
                    'Coffee Chocolate' => 900,
                    'Mango' => 900,
                    'Strawberry' => 900,
                    'Chocolate Icing' => 900,
                    'Vanilla Icing' => 900,
                    'Strawberry Icing' => 900,
                    'Coffee Fudge' => 900,
                    'Chocolate Fudge' => 900,
                    'Lemon Tart' => 900
                ),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),



        ),
        'default_flavors' => array(
            'Vanilla' => 0,
            'Chocolate' => 0,
            'Strawberry' => 0,
            'Red Velvet' => 0,
            'Mango' => 0,
            'Butterscotch' => 0,
            'Pineapple' => 0,
            'Coffee' => 0,
            'Black Forest' => 0,
            'Tiramisu' => 0
        ),
        'default_sizes' => $stdSizes,
        'default_ladi' => $stdLadi,
        'shapes' => array('Round', 'Square', 'Heart', 'Rectangle'),
    );
}




/** Parse "1.5 Kg (15-18 servings)" -> array(tiers, uom, label) */
function parseSizeLabel($label)
{
    $tiers = 1;
    $uom = 'kg';
    if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z]+)?/', $label, $m)) {
        $tiers = floatval($m[1]);
        if (!empty($m[2])) $uom = strtolower($m[2]);
    }
    return array($tiers, $uom);
}

/* ---------- order categories (DB managed, with defaults) ----------
   cake_order_category(id, name, order_type, keywords)
   order_type: cake | lunchbox | sweetsbox | pos | all               */
function ensureCategoryTable($mysqli)
{
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS cake_order_category (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        order_type VARCHAR(20) NOT NULL DEFAULT 'cake',
        keywords VARCHAR(500) NOT NULL DEFAULT '',
        dateent DATETIME NULL)");
    $r = mysqli_query($mysqli, "SELECT COUNT(*) AS c FROM cake_order_category");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    if (!$row || intval($row['c']) == 0) {
        $seed = array(
            array('Fresh Cream Cake', 'cake', 'cream,fresh'),
            array('Chocolate Cake',   'cake', 'chocolate,brownie,truffle'),
            array('Cheese Cake',      'cake', 'cheese'),
            array('Pastry',           'cake', 'pastry,cupcake,muffin,donut'),
            array('Lunch Items',      'lunchbox', 'chicken,rice,roti,kebab,pizza,juice,drink,shawarma'),
            array('Sweets',           'sweetsbox', 'sweet,laddu,barfi,gulab,jaman,kheer,halwa'),
            array('General',          'pos', ''),
        );
        foreach ($seed as $sd) {
            $n = mysqli_real_escape_string($mysqli, $sd[0]);
            $t = mysqli_real_escape_string($mysqli, $sd[1]);
            $k = mysqli_real_escape_string($mysqli, $sd[2]);
            mysqli_query($mysqli, "INSERT INTO cake_order_category (name, order_type, keywords, dateent) VALUES ('$n','$t','$k',NOW())");
        }
    }
}
function orderCategories($mysqli, $type)
{
    ensureCategoryTable($mysqli);
    $t = mysqli_real_escape_string($mysqli, $type);
    $out = array();
    $r = mysqli_query($mysqli, "SELECT name, order_type, keywords FROM cake_order_category
        WHERE order_type IN ('$t','all') ORDER BY id");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $out[] = $x;
    return $out;
}
function allCategories($mysqli)
{
    ensureCategoryTable($mysqli);
    $out = array();
    $r = mysqli_query($mysqli, "SELECT name, order_type, keywords FROM cake_order_category ORDER BY id");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $out[] = $x;
    return $out;
}
/* comma list of category names whose keywords appear in product name */
function productCatAttr($mysqli, $name)
{
    static $cats = null;
    if ($cats === null) $cats = allCategories($mysqli);
    $hit = array();
    foreach ($cats as $c) {
        $kws = array_filter(array_map('trim', explode(',', $c['keywords'])));
        if (!$kws) {
            $hit[] = strtolower($c['name']);
            continue;
        }   // no keywords = general, matches all
        foreach ($kws as $k) {
            if ($k !== '' && stripos($name, $k) !== false) {
                $hit[] = strtolower($c['name']);
                break;
            }
        }
    }
    return htmlspecialchars(implode(',', $hit), ENT_QUOTES);
}

/* delivery branch options (edit here) */
function deliveryBranches()
{
    return array(
        'Salaman Gulbahar bakery',
        'Salaman Dalazak bakery',
        'Salaman Asia Gate',
        'Salman warsak road',
        'Salman dabgari garden',
    );
}
