<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$auth = requireAuth(['admin', 'warga']);

$data = json_decode(file_get_contents('php://input'), true);

$warga_id = isset($data['warga_id']) ? intval($data['warga_id']) : 0;
$bulanArr = isset($data['bulan']) ? $data['bulan'] : [];

if ($warga_id === 0 || empty($bulanArr)) {
    responseJson(["success" => false, "message" => "Data tidak lengkap"], 400);
}

if ($auth['role'] === 'warga' && intval($auth['uid']) !== $warga_id) {
    responseJson(["success" => false, "message" => "Forbidden"], 403);
}

try {
    $conn->beginTransaction();
    
    $tahun = date('Y');
    $tgl_bayar = isset($data['tgl_bayar']) && !empty($data['tgl_bayar']) ? $data['tgl_bayar'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s');

    foreach ($bulanArr as $bulan) {
        $bulan = intval($bulan);
        
        // Cari ID_PERIODE
        $stmtPer = $conn->prepare("SELECT ID_PERIODE FROM PERIODE_IURAN WHERE BULAN = ? AND TAHUN = ?");
        $stmtPer->execute([$bulan, $tahun]);
        $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
        
        if ($rowPer) {
            $id_periode = $rowPer['ID_PERIODE'];
            
            // Cek apakah sudah ada pembayaran
            $stmtCek = $conn->prepare("SELECT ID_PEMBAYARAN FROM PEMBAYARAN WHERE ID_WARGA = ? AND ID_PERIODE = ?");
            $stmtCek->execute([$warga_id, $id_periode]);
            $rowCek = $stmtCek->fetch(PDO::FETCH_ASSOC);
            
            $status = 'lunas';
            
            if ($rowCek) {
                // Update
                $id_pembayaran = $rowCek['ID_PEMBAYARAN'];
                $stmtUp = $conn->prepare("UPDATE PEMBAYARAN SET STATUS = ?, TANGGAL_BAYAR = ? WHERE ID_PEMBAYARAN = ?");
                $stmtUp->execute([$status, $tgl_bayar, $id_pembayaran]);
            } else {
                // Insert
                $id_pembayaran = rand(1000000, 9999999);
                $stmtIn = $conn->prepare("INSERT INTO PEMBAYARAN (ID_PEMBAYARAN, ID_PERIODE, ID_WARGA, TANGGAL_BAYAR, STATUS) VALUES (?, ?, ?, ?, ?)");
                $stmtIn->execute([$id_pembayaran, $id_periode, $warga_id, $tgl_bayar, $status]);
            }
        }
    }
    
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Pembayaran tunai berhasil dicatat!"]);

} catch(Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    responseJson(["success" => false, "message" => "Database error: " . $e->getMessage()], 500);
}
?>
