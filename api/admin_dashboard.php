<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

try {
    $currentMonth = intval(date('m'));
    $currentYear = intval(date('Y'));
    
    // 1. Dapatkan daftar WARGA
    $stmt = $conn->query("SELECT * FROM WARGA");
    $wargas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_kk = count($wargas);

    // 2. Dapatkan PERIODE_IURAN tahun berjalan
    $stmt = $conn->prepare("SELECT * FROM PERIODE_IURAN WHERE TAHUN = ? ORDER BY BULAN ASC");
    $stmt->execute([$currentYear]);
    $periodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT p.*, pi.BULAN, pi.NOMINAL 
                            FROM PEMBAYARAN p 
                            JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE 
                            WHERE pi.TAHUN = ?");
    $stmt->execute([$currentYear]);
    $pembayarans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Dapatkan LAPORAN tahun berjalan
    $stmt = $conn->prepare("SELECT l.*, pi.BULAN 
                            FROM LAPORAN l 
                            JOIN PERIODE_IURAN pi ON l.ID_PERIODE = pi.ID_PERIODE 
                            WHERE pi.TAHUN = ?");
    $stmt->execute([$currentYear]);
    $laporans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $laporan_dict = [];
    foreach ($laporans as $l) {
        $laporan_dict[$l['BULAN']] = $l;
    }

    // Hitung rekap bulanan & tunggakan
    $rekap = [];
    $tunggakan = [];
    $totalMasuk = 0;
    $targetMasuk = $total_kk * 6000 * 12; // Asumsi 6000 per bulan
    $pendingCount = 0;
    $lunasBulanIni = 0;
    $bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $shortBulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];

    // Menghitung rekap per bulan
    for ($i = 1; $i <= $currentMonth; $i++) {
        $lunasCount = 0;
        $terkumpul = 0;
        foreach ($pembayarans as $p) {
            if ($p['BULAN'] == $i && strtolower($p['STATUS']) === 'lunas') {
                $lunasCount++;
                $terkumpul += floatval($p['NOMINAL']);
            }
        }
        $totalMasuk += $terkumpul;
        
        $selesai = ($lunasCount === $total_kk && $total_kk > 0);
        $status_teks = "Berlangsung";
        $arsip = false;
        $tanggal_rekap = null;

        if (isset($laporan_dict[$i])) {
            $arsip = true;
            $tanggal_rekap = date('d M Y', strtotime($laporan_dict[$i]['TANGGAL_REKAP']));
            $status_teks = "Diarsipkan";
        } else if ($selesai) {
            $status_teks = "Selesai";
        } else if ($i < $currentMonth) {
            $status_teks = "Ditutup";
        }

        $rekap[] = [
            "bulan" => $bulanNames[$i-1],
            "bulan_angka" => $i,
            "terkumpul" => "Rp " . number_format($terkumpul, 0, ',', '.'),
            "lunas" => $lunasCount,
            "total_kk" => $total_kk,
            "selesai" => $selesai,
            "status_teks" => $status_teks,
            "arsip" => $arsip,
            "tanggal_rekap" => $tanggal_rekap
        ];

        if ($i === $currentMonth) {
            $lunasBulanIni = $lunasCount;
        }
    }

    // Menghitung tunggakan per warga
    foreach ($wargas as $w) {
        $bulanNunggak = [];
        $bulanNunggakAngka = [];
        for ($i = 1; $i <= $currentMonth; $i++) {
            $bayar = null;
            foreach ($pembayarans as $p) {
                if ($p['ID_WARGA'] == $w['ID_WARGA'] && $p['BULAN'] == $i) {
                    $bayar = $p;
                    break;
                }
            }
            if (!$bayar || strtolower($bayar['STATUS']) === 'ditolak' || strtolower($bayar['STATUS']) === 'belum') {
                $bulanNunggak[] = $shortBulanNames[$i-1];
                $bulanNunggakAngka[] = $i;
            }
            if ($bayar && strtolower($bayar['STATUS']) === 'pending') {
                $pendingCount++;
            }
        }

        if (count($bulanNunggak) > 0) {
            $words = explode(" ", $w['NAMA']);
            $inisial = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($words[0], 0, 2));
            
            $tunggakan[] = [
                "id_warga" => $w['ID_WARGA'],
                "nama" => $w['NAMA'],
                "rumah" => "Gang " . $w['GANG'],
                "inisial" => $inisial,
                "bulan" => $bulanNunggak,
                "bulan_angka" => $bulanNunggakAngka
            ];
        }
    }

    // Hitung pengeluaran tahun ini
    $stmt = $conn->prepare("SELECT SUM(NOMINAL_P) as total_keluar FROM PENGELUARAN WHERE YEAR(TANGGAL_P) = ?");
    $stmt->execute([$currentYear]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPengeluaran = $row['total_keluar'] ? floatval($row['total_keluar']) : 0;

    // Hitung Saldo Awal (semua masuk sebelum tahun ini - semua keluar sebelum tahun ini)
    $stmtAwalMasuk = $conn->prepare("SELECT SUM(pi.NOMINAL) as total FROM PEMBAYARAN p JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE WHERE pi.TAHUN < ? AND LOWER(p.STATUS) = 'lunas'");
    $stmtAwalMasuk->execute([$currentYear]);
    $masukAwal = floatval($stmtAwalMasuk->fetch(PDO::FETCH_ASSOC)['total']);

    $stmtAwalKeluar = $conn->prepare("SELECT SUM(NOMINAL_P) as total FROM PENGELUARAN WHERE YEAR(TANGGAL_P) < ?");
    $stmtAwalKeluar->execute([$currentYear]);
    $keluarAwal = floatval($stmtAwalKeluar->fetch(PDO::FETCH_ASSOC)['total']);

    $saldoAwal = $masukAwal - $keluarAwal;

    // Saldo bersih akhir
    $saldoBersih = $saldoAwal + $totalMasuk - $totalPengeluaran;

    // Persen lunas bulan ini
    $persenLunas = $total_kk > 0 ? round(($lunasBulanIni / $total_kk) * 100) : 0;

    echo json_encode([
        "stats" => [
            "total_kk" => $total_kk,
            "lunas_bulan_ini" => $lunasBulanIni,
            "persen_lunas" => $persenLunas,
            "belum_bayar" => count($tunggakan),
            "pending_count" => $pendingCount,
            "total_masuk" => $totalMasuk,
            "target_masuk" => $targetMasuk,
            "total_pengeluaran" => $totalPengeluaran,
            "saldo_awal" => $saldoAwal,
            "saldo_bersih" => $saldoBersih
        ],
        "rekap" => $rekap,
        "tunggakan" => $tunggakan
    ]);

} catch(PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
