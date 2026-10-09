<?php
require_once 'db.php';

// Item search for the order screen: finished products that are active, found by name or barcode.
// Every word typed must match (so "choc cake" finds "Chocolate Cake"). With no search text, the first 200 by name.
$raw = (isset($_GET['search']) && is_string($_GET['search'])) ? trim($_GET['search']) : '';
$words = ($raw === '') ? array() : preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

$sql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom 
        FROM inventory 
        WHERE manufacture = 'Finish Product' AND active = 1";

foreach ($words as $word) {
    // esc() makes the text safe for SQL. % and _ are escaped too, so they are searched as plain characters.
    $w = str_replace(array('%', '_'), array('\\%', '\\_'), esc($word));
    $sql .= " AND (prod_name LIKE '%$w%' OR manualbc LIKE '%$w%')";
}

$sql .= " ORDER BY prod_name ASC LIMIT 200";

$result = mysqli_query($mysqli, $sql);

$products = array();
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = array(
            'inv_id' => $row['inv_id'],
            'prod_name' => $row['prod_name'],
            'retail_price' => floatval($row['retail_price']),
            'barcode' => $row['barcode'],
            'uom' => $row['uom']
        );
    }
}

jsonResponse(array('products' => $products));
?>