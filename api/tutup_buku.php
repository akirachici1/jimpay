<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

// Menerima input JSON (karena pakai fetch body JSON)
$data = json_decode(file_get_contents('php://input'), true);

$bulan = isset($data['bulan']) ? intval($data['bulan']) : 0;
$tahun = isset($data['tahun']) ? intval($data['tahun']) : 0;

if ($bulan === 0 || $tahun === 0) {
    responseJson(["success" => false, "message" => "Bulan atau tahun tidak valid."], 400);
}

try {
    $conn->beginTransaction();

    // 1. Dapatkan ID_PERIODE untuk bulan dan tahun tersebut
    $stmtPer = $conn->prepare("SELECT ID_PERIODE FROM PERIODE_IURAN WHERE BULAN = ? AND TAHUN = ?");
    $stmtPer->execute([$bulan, $tahun]);
    $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);

    if (!$rowPer) {
        throw new Exception("Periode iuran tidak ditemukan.");
    }
    $id_periode = $rowPer['ID_PERIODE'];

    // 2. Cek apakah sudah ditutup
    $stmtCek = $conn->prepare("SELECT ID_LAPORAN FROM LAPORAN WHERE ID_PERIODE = ?");
    $stmtCek->execute([$id_periode]);
    if ($stmtCek->fetch()) {
        throw new Exception("Bulan ini sudah ditutup sebelumnya.");
    }

    // 3. Hitung Warga Lunas, Pending, Belum dan Total Pemasukan
    $stmtWarga = $conn->query("SELECT COUNT(*) as total FROM WARGA");
    $total_kk = $stmtWarga->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtBayar = $conn->prepare("SELECT STATUS, SUM(pi.NOMINAL) as NOMINAL 
                                 FROM PEMBAYARAN p 
                                 JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE 
                                 WHERE p.ID_PERIODE = ? 
                                 GROUP BY STATUS");
    $stmtBayar->execute([$id_periode]);
    $bayarStats = $stmtBayar->fetchAll(PDO::FETCH_ASSOC);

    $lunas = 0;
    $pending = 0;
    $ditolak = 0;
    $total_pemasukan = 0;

    foreach ($bayarStats as $b) {
        $status = strtolower($b['STATUS']);
        if ($status === 'lunas') {
            // Karena SUM mengembalikan total uang masuk untuk status ini, kita harus menghitung jumlah orangnya.
            // Lebih baik kita hitung dari COUNT. Mari query ulang secara terpisah saja agar aman.
        }
    }

    // Query Hitung Detail
    $stmtDetail = $conn->prepare("SELECT STATUS, COUNT(*) as cnt FROM PEMBAYARAN WHERE ID_PERIODE = ? GROUP BY STATUS");
    $stmtDetail->execute([$id_periode]);
    $detailStats = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
    foreach ($detailStats as $d) {
        $status = strtolower($d['STATUS']);
        if ($status === 'lunas') $lunas = intval($d['cnt']);
        if ($status === 'pending') $pending = intval($d['cnt']);
        if ($status === 'ditolak') $ditolak = intval($d['cnt']);
    }

    $belum = $total_kk - $lunas - $pending; // Warga yg ditolak juga masuk kategori belum/menunggak
    // (Sebenarnya yg blm masuk row pembayaran = belum bayar. Yg ditolak jg dihitung belum)

    // Hitung total pemasukan khusus bulan ini
    $stmtMasuk = $conn->prepare("SELECT SUM(pi.NOMINAL) as total_masuk 
                                 FROM PEMBAYARAN p 
                                 JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE 
                                 WHERE p.ID_PERIODE = ? AND p.STATUS = 'lunas'");
    $stmtMasuk->execute([$id_periode]);
    $rowMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC);
    $total_pemasukan = $rowMasuk['total_masuk'] ? floatval($rowMasuk['total_masuk']) : 0;

    // 4. Hitung Saldo Keseluruhan RT (Dari semua bulan lunas - semua pengeluaran)
    $stmtAllMasuk = $conn->query("SELECT SUM(pi.NOMINAL) as total_masuk FROM PEMBAYARAN p JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE WHERE p.STATUS = 'lunas'");
    $allMasuk = floatval($stmtAllMasuk->fetch(PDO::FETCH_ASSOC)['total_masuk']);

    $stmtAllKeluar = $conn->query("SELECT SUM(NOMINAL_P) as total_keluar FROM PENGELUARAN");
    $allKeluar = floatval($stmtAllKeluar->fetch(PDO::FETCH_ASSOC)['total_keluar']);

    $saldo = $allMasuk - $allKeluar;

    // 5. Insert ke LAPORAN
    $id_laporan = rand(1000000, 9999999);
    $tanggal_rekap = date('Y-m-d');

    $stmtInsert = $conn->prepare("INSERT INTO LAPORAN 
        (ID_LAPORAN, ID_PERIODE, TOTAL_PEMASUKAN, JUMLAH_WARGA_LUNAS, JUMLAH_WARGA_PENDING, JUMLAH_WARGA_BELUM, SALDO, TANGGAL_REKAP) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmtInsert->execute([
        $id_laporan,
        $id_periode,
        $total_pemasukan,
        $lunas,
        $pending,
        $belum,
        $saldo,
        $tanggal_rekap
    ]);

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Bulan berhasil ditutup dan diarsipkan."]);

} catch(Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    responseJson(["success" => false, "message" => "Database error: " . $e->getMessage()], 500);
}
?>
