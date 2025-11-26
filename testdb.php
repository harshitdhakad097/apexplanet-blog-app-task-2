<?php
require 'config.php';
if ($conn) {
    echo "Connected. Tables in DB:<br>";
    $res = mysqli_query($conn, "SHOW TABLES");
    while ($r = mysqli_fetch_array($res)) {
        echo htmlspecialchars($r[0]) . "<br>";
    }
} else {
    echo "No connection variable found.";
}
?>
