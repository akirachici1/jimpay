<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

try {
    $tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : intval(date('Y'));
    
    $stmt = $conn->prepare("SELECT * FROM PENGELUARAN WHERE YEAR(TANGGAL_P) = ? ORDER BY ID_PENGELUARAN DESC");
    $stmt->execute([$tahun]);
    $pengeluaran = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($pengeluaran as $p) {
        $results[] = [
            "id" => $p['ID_PENGELUARAN'],
            "keterangan" => $p['KETERANGAN_P'],
            "nominal" => floatval($p['NOMINAL_P']),
            "tanggal" => date('d M Y', strtotime($p['TANGGAL_P']))
        ];
    }
    echo json_encode($results);

} catch(PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
