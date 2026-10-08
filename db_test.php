<?php

$mysqli = new mysqli("182.180.87.102", "waqar", "waqarmgr2019", "salman_gulbahar_bakery", 34599);

if ($mysqli->connect_error) {
    die("FAILED: " . $mysqli->connect_error);
}

echo "SUCCESS CONNECTED";