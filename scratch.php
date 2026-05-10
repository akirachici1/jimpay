<?php
require 'koneksi.php';
$stmt = $conn->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'WARGA'");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
