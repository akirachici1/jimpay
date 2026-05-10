<?php
// test_db.php
require 'koneksi.php';

if ($conn) {
    echo "<h1>Koneksi Berhasil!</h1>";
    echo "<p>PHP berhasil terhubung ke SQL Server (SSMS) dengan nama database: jimpay.mdf</p>";
} else {
    echo "<h1>Koneksi Gagal</h1>";
}
?>
