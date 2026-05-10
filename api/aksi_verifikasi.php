<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$auth = requireAuth(['admin']);

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['dokId']) || !isset($data['aksi'])) {
    responseJson(['success' => false, 'message' => 'Data tidak lengkap'], 400);
    exit;
}

try {
    $id_pembayaran = intval($data['dokId']);
    $aksi = $data['aksi'];
    $alasan = isset($data['alasan']) ? trim($data['alasan']) : '';

    if ($aksi !== 'approve' && $aksi !== 'reject') {
        responseJson(['success' => false, 'message' => 'Aksi tidak valid'], 400);
        exit;
    }

    $status = $aksi === 'approve' ? 'lunas' : 'ditolak';
    $hasil = $aksi === 'approve' ? 1 : 0;
    $tgl = date('Y-m-d');

    $conn->beginTransaction();

    $stmt = $conn->prepare('UPDATE PEMBAYARAN SET STATUS = ? WHERE ID_PEMBAYARAN = ?');
    $stmt->execute([$status, $id_pembayaran]);

    $stmtDok = $conn->prepare('SELECT ID_DOKUMEN FROM BUKTI_PEMBAYARAN WHERE ID_PEMBAYARAN = ?');
    $stmtDok->execute([$id_pembayaran]);
    $rowDok = $stmtDok->fetch(PDO::FETCH_ASSOC);
    $id_dokumen = $rowDok ? $rowDok['ID_DOKUMEN'] : null;

    $id_verifikasi = time();
    $id_pengurus = intval($auth['uid']);

    $stmtVer = $conn->prepare('INSERT INTO VERIFIKASI (ID_VERIFIKASI, ID_PENGURUS, ID_DOKUMEN, HASIL, TANGGAL_KONFIRMASI, KETERANGAN) VALUES (?, ?, ?, ?, ?, ?)');
    $stmtVer->execute([$id_verifikasi, $id_pengurus, $id_dokumen, $hasil, $tgl, $alasan]);

    $conn->commit();
    responseJson(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    responseJson(['success' => false, 'message' => 'Database error'], 500);
}
?>
