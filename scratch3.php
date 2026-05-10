<?php
require 'koneksi.php';
$stmt = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
