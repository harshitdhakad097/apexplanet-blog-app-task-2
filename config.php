<?php
// config.php - database connection used by all pages
$servername = "localhost";
$username = "root";
$password = "";     // default for XAMPP
$database = "blog";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
