<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$auth = requireAuth(['admin']);

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['keterangan']) || !isset($data['nominal'])) {
    responseJson(["success" => false, "message" => "Data tidak lengkap"], 400);
}

try {
    // Generate simple ID
    $id = time();
    $keterangan = $data['keterangan'];
    $nominal = floatval($data['nominal']);
    // Tanggal diambil dari kalender frontend (format YYYY-MM-DD), default hari ini jika kosong
    $tanggal = !empty($data['tanggal']) ? $data['tanggal'] : date('Y-m-d');
    
    $id_pengurus = intval($auth['uid']);
    $stmt = $conn->prepare("INSERT INTO PENGELUARAN (ID_PENGELUARAN, ID_PENGURUS, NOMINAL_P, KETERANGAN_P, TANGGAL_P) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id, $id_pengurus, $nominal, $keterangan, $tanggal]);

    echo json_encode(["success" => true, "id" => $id]);

} catch(PDOException $e) {
    responseJson(["success" => false, "message" => "Database error: " . $e->getMessage()], 500);
}
?>
