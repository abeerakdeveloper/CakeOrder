<?php
/**
 * Product pictures — NO database change needed.
 *
 * Drop image files into /product_images/ named by inventory id or product name:
 *     product_images/123.jpg            (inv_id  = 123)
 *     product_images/chicken-sandwich.png
 * Reference photos for the cake screen go into:
 *     product_images/reference/*.jpg
 *
 * If no file is found the screens fall back to a clean emoji tile.
 */

function productImageDir() {
    return dirname(__DIR__) . '/product_images';
}

/** Returns web path to a product picture, or '' when none exists. */
function productImageUrl($invId, $name) {
    $dir  = productImageDir();
    $exts = array('jpg', 'jpeg', 'png', 'webp', 'gif');
    $bases = array();
    if ($invId) $bases[] = strval(intval($invId));
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$name), '-'));
    if ($slug !== '') $bases[] = $slug;

    foreach ($bases as $b) {
        foreach ($exts as $e) {
            $f = $dir . '/' . $b . '.' . $e;
            if (is_file($f)) return 'product_images/' . $b . '.' . $e;
        }
    }
    return '';
}

/** Reference cake photos shown as "choose a design" thumbnails. */
function productReferenceImages($limit = 8) {
    $dir = productImageDir() . '/reference';
    $out = array();
    if (!is_dir($dir)) return $out;
    foreach (scandir($dir) as $f) {
        if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $f)) {
            $out[] = 'product_images/reference/' . $f;
            if (count($out) >= $limit) break;
        }
    }
    return $out;
}

/** Clean emoji fallback per product name keyword. */
function productEmoji($name) {
    $n = strtolower((string)$name);
    $map = array(
        'cake' => '🎂', 'brownie' => '🍫', 'cupcake' => '🧁', 'muffin' => '🧁',
        'pastry' => '🥐', 'croissant' => '🥐', 'donut' => '🍩', 'doughnut' => '🍩',
        'sandwich' => '🥪', 'burger' => '🍔', 'pizza' => '🍕', 'fries' => '🍟',
        'chicken' => '🍗', 'nugget' => '🍗', 'wrap' => '🌯', 'roll' => '🌯',
        'biryani' => '🍛', 'rice' => '🍚', 'salad' => '🥗', 'fruit' => '🍓',
        'juice' => '🧃', 'water' => '💧', 'drink' => '🥤', 'shake' => '🥤',
        'tea' => '🍵', 'coffee' => '☕', 'ice cream' => '', 'cream' => '',
        'sweet' => '🍬', 'mithai' => '🍬', 'barfi' => '🍬', 'gulab' => '🍯',
        'laddu' => '🍡', 'halwa' => '🍮', 'kheer' => '🍮', 'custard' => '🍮',
        'bread' => '🍞', 'bun' => '🍞', 'cookie' => '🍪', 'biscuit' => '🍪',
        'pie' => '🥧', 'tart' => '🥧', 'chocolate' => '🍫', 'egg' => '🥚',
        'samosa' => '🥟', 'pakora' => '🥟', 'box' => '🍱', 'lunch' => '🍱',
    );
    foreach ($map as $k => $v) if (strpos($n, $k) !== false) return $v;
    return '🍽️';
}
