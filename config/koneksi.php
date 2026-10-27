<?php
$koneksi = mysqli_connect("localhost", "root", "", "chickguard");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
