<?php

/**
 * Predefined CAKE categories and their combobox data.
 * Screens read ONLY this file — edit freely, no DB change needed.
 *
 * Structure per category:
 *   name   : category label (saved in cake_order.category)
 *   icon   : emoji tile icon
 *   price  : default price per unit (Rs.)
 *   flavors: flavor => price override (0 = keep category price)
 *   sizes  : size labels; the leading number + word become tiers + uom
 *   ladi   : ladi options
 */
function cakeConfig()
{
    $stdSizes = array(
        '0.5 Kg (5-6 servings)',
        '1 Kg (10-12 servings)',
        '1.5 Kg (15-18 servings)',
        '2 Kg (20-24 servings)',
        '3 Kg (30-35 servings)',
        '5 Kg (50-60 servings)'
    );
    $stdLadi = array('White Laddi', 'Chocolate Laddi', 'Mix Laddi', 'N/A');

    return array(
        'categories' => array(
            array(
                'name' => 'Fresh Cream Cake',
                'icon' => '🎂',
                'price' => 760,
                'flavors' => array('Chocolate Icing' => 760, 'Vanilla Icing' => 760, 'Strawberry Icing' => 780, 'Pineapple Icing' => 780, 'Black Forest' => 850),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Mousse Cake',
                'icon' => '🍰',
                'price' => 950,
                'flavors' => array('Chocolate Mousse' => 950, 'Vanilla Mousse' => 950, 'Coffee Mousse' => 980, 'Mango Mousse' => 980),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Café Cake',
                'icon' => '☕',
                'price' => 880,
                'flavors' => array('Coffee Icing' => 880, 'Cappuccino Icing' => 900, 'Caramel Icing' => 900),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Butter Cream Cake',
                'icon' => '🧈',
                'price' => 720,
                'flavors' => array('Vanilla Butter Cream' => 720, 'Chocolate Butter Cream' => 720, 'Strawberry Butter Cream' => 740, 'Butterscotch' => 760),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Fresh Brownie Cake',
                'icon' => '🍫',
                'price' => 990,
                'flavors' => array('Chocolate Brownie' => 990, 'Walnut Brownie' => 1050, 'Double Chocolate' => 1050),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Ice Cream Cake',
                'icon' => '🍨',
                'price' => 1100,
                'flavors' => array('Vanilla Ice Cream' => 1100, 'Chocolate Ice Cream' => 1100, 'Strawberry Ice Cream' => 1120, 'Kulfa Ice Cream' => 1120),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
            array(
                'name' => 'Chicken Cake',
                'icon' => '🍗',
                'price' => 1200,
                'flavors' => array('Chicken Savoury' => 1200, 'Cheesy Chicken' => 1250),
                'sizes' => $stdSizes,
                'ladi' => $stdLadi
            ),
        ),
        // Fallbacks when a searched inventory item (not a predefined
        // category) is selected:
        'default_flavors' => array('Vanilla' => 0, 'Chocolate' => 0, 'Strawberry' => 0, 'Red Velvet' => 0, 'Mango' => 0, 'Butterscotch' => 0, 'Pineapple' => 0, 'Coffee' => 0, 'Black Forest' => 0, 'Tiramisu' => 0),
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
