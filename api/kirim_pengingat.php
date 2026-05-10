<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM WARGA");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_warga = $row['count'];
    
    $stmtSet = $conn->query("SELECT COUNT(DISTINCT ID_WARGA) as count FROM PEMBAYARAN");
    $rowSet = $stmtSet->fetch(PDO::FETCH_ASSOC);
    $sudah_bayar = $rowSet['count'];
    
    $nunggak = $total_warga - $sudah_bayar;
    if ($nunggak < 0) $nunggak = 0;

    echo json_encode(["success" => true, "count" => $nunggak]);

} catch(PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
