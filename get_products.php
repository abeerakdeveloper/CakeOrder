<?php
require_once 'db.php';

$search = isset($_GET['search']) ? esc($_GET['search']) : '';

$sql = "SELECT inv_id, prod_name, retail_price, manualbc AS barcode, packing AS uom 
        FROM inventory 
        WHERE manufacture = 'Finish Product' AND active = 1";

if (!empty($search)) {
    $sql .= " AND (prod_name LIKE '%$search%' OR manualbc LIKE '%$search%')";
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