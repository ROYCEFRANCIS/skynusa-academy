<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
define('DB_NAME', 'skynusa_academy');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

function query($sql) {
    global $conn;
    return mysqli_query($conn, $sql);
}

function escape($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

function fetch_one($result) {
    return mysqli_fetch_assoc($result);
}

function fetch_all($result) {
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>