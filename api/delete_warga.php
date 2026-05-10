<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    echo json_encode(["success" => false, "message" => "ID tidak valid"]);
    exit;
}

try {
    $conn->beginTransaction();
    
    $stmtP = $conn->prepare("SELECT ID_PEMBAYARAN FROM PEMBAYARAN WHERE ID_WARGA = ?");
    $stmtP->execute([$id]);
    $pembayarans = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pembayarans as $p) {
        $id_pem = $p['ID_PEMBAYARAN'];

        $stmtD = $conn->prepare("SELECT ID_DOKUMEN FROM BUKTI_PEMBAYARAN WHERE ID_PEMBAYARAN = ?");
        $stmtD->execute([$id_pem]);
        $dokumens = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dokumens as $d) {
            $id_dok = $d['ID_DOKUMEN'];
            
            $stmtV = $conn->prepare("DELETE FROM VERIFIKASI WHERE ID_DOKUMEN = ?");
            $stmtV->execute([$id_dok]);

            $stmtB = $conn->prepare("DELETE FROM BUKTI_PEMBAYARAN WHERE ID_DOKUMEN = ?");
            $stmtB->execute([$id_dok]);
        }

        $stmtDelPem = $conn->prepare("DELETE FROM PEMBAYARAN WHERE ID_PEMBAYARAN = ?");
        $stmtDelPem->execute([$id_pem]);
    }

    $stmt = $conn->prepare("DELETE FROM WARGA WHERE ID_WARGA = ?");
    $stmt->execute([$id]);

    $conn->commit();
    echo json_encode(["success" => true]);

} catch(Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
