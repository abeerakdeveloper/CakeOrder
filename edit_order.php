<?php
// The old add-only edit screen is replaced by the New Order screen in edit mode.
// Old links to edit_order.php?bill=N go to index.php?bill=N.
require_once 'db.php';
$billNo = isset($_GET['bill']) ? intval($_GET['bill']) : 0;
header('Location: index.php?bill=' . $billNo);
exit;
