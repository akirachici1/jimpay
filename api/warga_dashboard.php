<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$auth = requireAuth(['admin', 'warga']);
$warga_id = isset($_GET['warga_id']) ? intval($_GET['warga_id']) : 0;
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : intval(date('Y'));

if ($warga_id === 0) {
    responseJson(["error" => "warga_id required"], 400);
}

if ($auth['role'] === 'warga' && intval($auth['uid']) !== $warga_id) {
    responseJson(["error" => "Forbidden"], 403);
}

try {
    // 1. Cek dan generate PERIODE_IURAN untuk tahun ini jika masih kosong
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM PERIODE_IURAN WHERE TAHUN = ?");
    $stmt->execute([$tahun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['count'] == 0) {
        for ($i = 1; $i <= 12; $i++) {
            $id_periode = ($tahun * 100) + $i; // Contoh: 202501
            $batas = sprintf('%04d-%02d-25 23:59:59', $tahun, $i);
            $conn->exec("INSERT INTO PERIODE_IURAN (ID_PERIODE, BULAN, TAHUN, NOMINAL, TANGGAL_BATAS_BAYAR) 
                         VALUES ($id_periode, $i, $tahun, 6000, '$batas')");
        }
    }

    // 2. Ambil semua PERIODE_IURAN tahun ini
    $stmt = $conn->prepare("SELECT * FROM PERIODE_IURAN WHERE TAHUN = ? ORDER BY BULAN ASC");
    $stmt->execute([$tahun]);
    $periodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Ambil pembayaran warga ini
    $stmt = $conn->prepare("SELECT p.*, pi.BULAN, pi.NOMINAL, pi.TANGGAL_BATAS_BAYAR 
                            FROM PEMBAYARAN p 
                            JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE 
                            WHERE p.ID_WARGA = ? AND pi.TAHUN = ?");
    $stmt->execute([$warga_id, $tahun]);
    $pembayarans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping pembayaran by bulan
    $pembayaran_dict = [];
    foreach ($pembayarans as $p) {
        $pembayaran_dict[$p['BULAN']] = $p;
    }

    $bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    $currentMonth = intval(date('m'));

    $stats = [
        "lunas" => 0, "belum" => 0, "pending" => 0, "totalNominal" => 0,
        "totalMasukRT" => 0, "totalPengeluaran" => 0, "saldoBersih" => 0
    ];
    $grid = [];
    $riwayat = [];

    // Hitung grid & stats untuk warga ini
    foreach ($periodes as $pi) {
        $b = intval($pi['BULAN']);
        $nominal = floatval($pi['NOMINAL']);
        $status = 'upcoming';

        if (isset($pembayaran_dict[$b])) {
            $status = strtolower($pembayaran_dict[$b]['STATUS']);
            if ($status === 'lunas') {
                $stats['lunas']++;
                $stats['totalNominal'] += $nominal;
                
                $tgl_bayar = strtotime($pembayaran_dict[$b]['TANGGAL_BAYAR']);
                $batas = strtotime($pembayaran_dict[$b]['TANGGAL_BATAS_BAYAR']);
                if ($tgl_bayar > $batas) {
                    $status = 'terlambat';
                }
            } else if ($status === 'pending') {
                $stats['pending']++;
            } else if ($status === 'ditolak') {
                $stats['belum']++;
            }
            
            if ($status !== 'belum') {
                $riwayat[] = [
                    "bulan" => $b,
                    "nama_bulan" => $bulanNames[$b-1],
                    "tahun" => $tahun,
                    "nominal" => $nominal,
                    "tgl_bayar" => date('d M Y', strtotime($pembayaran_dict[$b]['TANGGAL_BAYAR'])),
                    "status" => $status
                ];
            }
        } else {
            if ($b <= $currentMonth) {
                $status = 'belum';
                $stats['belum']++;
            }
        }

        $grid[] = [
            "bulan" => $b,
            "nama_bulan" => $bulanNames[$b-1],
            "status" => $status,
            "nominal" => $nominal
        ];
    }

    // Sort riwayat descending
    usort($riwayat, function($a, $b) { return $b['bulan'] <=> $a['bulan']; });
    $riwayat = array_slice($riwayat, 0, 5);

    // 4. Hitung Kas Keseluruhan RT (Hanya Tahun Ini)
    $stmt = $conn->prepare("SELECT SUM(pi.NOMINAL) as total_masuk 
                          FROM PEMBAYARAN p 
                          JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE 
                          WHERE LOWER(p.STATUS) = 'lunas' AND pi.TAHUN = ?");
    $stmt->execute([$tahun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['totalMasukRT'] = $row['total_masuk'] ? floatval($row['total_masuk']) : 0;

    $stmt = $conn->prepare("SELECT SUM(NOMINAL_P) as total_keluar FROM PENGELUARAN WHERE YEAR(TANGGAL_P) = ?");
    $stmt->execute([$tahun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['totalPengeluaran'] = $row['total_keluar'] ? floatval($row['total_keluar']) : 0;

    // Hitung Saldo Awal (semua masuk sebelum tahun ini - semua keluar sebelum tahun ini)
    $stmtAwalMasuk = $conn->prepare("SELECT SUM(pi.NOMINAL) as total FROM PEMBAYARAN p JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE WHERE pi.TAHUN < ? AND LOWER(p.STATUS) = 'lunas'");
    $stmtAwalMasuk->execute([$tahun]);
    $masukAwal = floatval($stmtAwalMasuk->fetch(PDO::FETCH_ASSOC)['total']);

    $stmtAwalKeluar = $conn->prepare("SELECT SUM(NOMINAL_P) as total FROM PENGELUARAN WHERE YEAR(TANGGAL_P) < ?");
    $stmtAwalKeluar->execute([$tahun]);
    $keluarAwal = floatval($stmtAwalKeluar->fetch(PDO::FETCH_ASSOC)['total']);

    $saldoAwal = $masukAwal - $keluarAwal;

    $stats['saldoBersih'] = $saldoAwal + $stats['totalMasukRT'] - $stats['totalPengeluaran'];

    echo json_encode([
        "stats" => $stats,
        "grid" => $grid,
        "riwayat" => $riwayat
    ]);

} catch(PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
