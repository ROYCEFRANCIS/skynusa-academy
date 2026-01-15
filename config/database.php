<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'skynusa_academy');

// Koneksi ke database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

// Fungsi helper
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
