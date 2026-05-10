<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$payload = requireAuth(['warga']);

$bulanArr = isset($_POST['bulan']) ? json_decode($_POST['bulan'], true) : [];
$catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';
$tgl_bayar = isset($_POST['tgl_bayar']) && !empty($_POST['tgl_bayar']) ? trim($_POST['tgl_bayar']) : date('Y-m-d H:i:s');

if (empty($bulanArr) || !is_array($bulanArr)) {
    responseJson(['success' => false, 'message' => 'Data tidak lengkap'], 400);
}

try {
    $conn->beginTransaction();

    $fileName = '';
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['bukti']['tmp_name']);

        if (!in_array($mimeType, $allowedMimeTypes, true) || $_FILES['bukti']['size'] > $maxSize) {
            responseJson(['success' => false, 'message' => 'Format bukti harus JPG/PNG/PDF dan ukuran maksimal 5MB'], 400);
        }

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            responseJson(['success' => false, 'message' => 'Gagal membuat folder upload'], 500);
        }

        $extension = pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION);
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        $fileName = uniqid('bukti_', true) . '.' . ($extension ?: 'dat');
        move_uploaded_file($_FILES['bukti']['tmp_name'], $uploadDir . $fileName);
    }

    $tahun = date('Y');
    $warga_id = intval($payload['uid']);

    foreach ($bulanArr as $bulan) {
        $bulan = intval($bulan);
        $stmtPer = $conn->prepare('SELECT ID_PERIODE FROM PERIODE_IURAN WHERE BULAN = ? AND TAHUN = ?');
        $stmtPer->execute([$bulan, $tahun]);
        $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);

        if (!$rowPer) {
            continue;
        }

        $id_periode = $rowPer['ID_PERIODE'];
        $stmtCek = $conn->prepare('SELECT ID_PEMBAYARAN FROM PEMBAYARAN WHERE ID_WARGA = ? AND ID_PERIODE = ?');
        $stmtCek->execute([$warga_id, $id_periode]);
        $rowCek = $stmtCek->fetch(PDO::FETCH_ASSOC);

        $status = 'pending';
        if ($rowCek) {
            $id_pembayaran = $rowCek['ID_PEMBAYARAN'];
            $stmtUp = $conn->prepare('UPDATE PEMBAYARAN SET STATUS = ?, TANGGAL_BAYAR = ? WHERE ID_PEMBAYARAN = ?');
            $stmtUp->execute([$status, $tgl_bayar, $id_pembayaran]);
        } else {
            $id_pembayaran = rand(1000000, 9999999);
            $stmtIn = $conn->prepare('INSERT INTO PEMBAYARAN (ID_PEMBAYARAN, ID_PERIODE, ID_WARGA, TANGGAL_BAYAR, STATUS) VALUES (?, ?, ?, ?, ?)');
            $stmtIn->execute([$id_pembayaran, $id_periode, $warga_id, $tgl_bayar, $status]);
        }

        if ($fileName !== '') {
            $id_dokumen = rand(1000000, 9999999);
            $stmtLink = $conn->prepare('INSERT INTO BUKTI_PEMBAYARAN (ID_DOKUMEN, ID_PEMBAYARAN, BUKTI_PEMBAYARAN) VALUES (?, ?, ?)');
            $stmtLink->execute([$id_dokumen, $id_pembayaran, $fileName]);
        }
    }

    $conn->commit();
    responseJson(['success' => true]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    responseJson(['success' => false, 'message' => 'Database error'], 500);
}
?>
