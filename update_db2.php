<?php
require 'koneksi.php';
$conn->exec('UPDATE PERIODE_IURAN SET NOMINAL = 6000');
echo 'Updated DB';
?>
