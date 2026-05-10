<?php
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : 0;
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : 0;

if ($bulan === 0 || $tahun === 0) {
    die("Parameter bulan dan tahun tidak valid.");
}

$bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$namaBulan = $bulanNames[$bulan-1];

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Kas_RT06_$namaBulan"."_$tahun.xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // 1. Dapatkan ID_PERIODE
    $stmtPer = $conn->prepare("SELECT ID_PERIODE FROM PERIODE_IURAN WHERE BULAN = ? AND TAHUN = ?");
    $stmtPer->execute([$bulan, $tahun]);
    $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
    if (!$rowPer) {
        die("Periode iuran tidak ditemukan.");
    }
    $id_periode = $rowPer['ID_PERIODE'];

    // 2. Ambil Pemasukan (Jimpitan)
    $stmtMasuk = $conn->prepare("
        SELECT w.NAMA, w.GANG, p.TANGGAL_BAYAR, pi.NOMINAL 
        FROM PEMBAYARAN p
        JOIN WARGA w ON p.ID_WARGA = w.ID_WARGA
        JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE
        WHERE p.ID_PERIODE = ? AND LOWER(p.STATUS) = 'lunas'
        ORDER BY p.TANGGAL_BAYAR ASC
    ");
    $stmtMasuk->execute([$id_periode]);
    $pemasukan = $stmtMasuk->fetchAll(PDO::FETCH_ASSOC);

    // 3. Ambil Pengeluaran
    $stmtKeluar = $conn->prepare("
        SELECT KETERANGAN_P, TANGGAL_P, NOMINAL_P 
        FROM PENGELUARAN 
        WHERE MONTH(TANGGAL_P) = ? AND YEAR(TANGGAL_P) = ?
        ORDER BY TANGGAL_P ASC
    ");
    $stmtKeluar->execute([$bulan, $tahun]);
    $pengeluaran = $stmtKeluar->fetchAll(PDO::FETCH_ASSOC);

    // 4. Hitung Saldo Awal (sebelum bulan ini)
    $stmtAllMasukPrev = $conn->prepare("
        SELECT SUM(pi.NOMINAL) as total
        FROM PEMBAYARAN p
        JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE
        WHERE pi.TAHUN < ? OR (pi.TAHUN = ? AND pi.BULAN < ?)
        AND LOWER(p.STATUS) = 'lunas'
    ");
    $stmtAllMasukPrev->execute([$tahun, $tahun, $bulan]);
    $totalMasukPrev = floatval($stmtAllMasukPrev->fetch(PDO::FETCH_ASSOC)['total']);

    $stmtAllKeluarPrev = $conn->prepare("
        SELECT SUM(NOMINAL_P) as total
        FROM PENGELUARAN
        WHERE YEAR(TANGGAL_P) < ? OR (YEAR(TANGGAL_P) = ? AND MONTH(TANGGAL_P) < ?)
    ");
    $stmtAllKeluarPrev->execute([$tahun, $tahun, $bulan]);
    $totalKeluarPrev = floatval($stmtAllKeluarPrev->fetch(PDO::FETCH_ASSOC)['total']);

    $saldoAwal = $totalMasukPrev - $totalKeluarPrev;

    $totalMasuk = 0;
    $totalKeluar = 0;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kas RT06</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: sans-serif; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .title { font-size: 16px; font-weight: bold; border: none; text-align: left; }
        .subtitle { font-size: 12px; border: none; text-align: left; }
        .no-border { border: none !important; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="5" class="title no-border">LAPORAN KAS MASUK & KELUAR RT 06</td>
    </tr>
    <tr>
        <td colspan="5" class="subtitle no-border">Periode: <?php echo $namaBulan . ' ' . $tahun; ?></td>
    </tr>
    <tr>
        <td colspan="5" class="subtitle no-border">Tanggal Cetak: <?php echo date('d M Y'); ?></td>
    </tr>
    <tr><td colspan="5" class="no-border"></td></tr>

    <!-- BAGIAN 1: KAS MASUK -->
    <tr>
        <td colspan="5" style="font-weight: bold; background-color: #d9ead3;">BAGIAN 1 - Kas Masuk (Jimpitan)</td>
    </tr>
    <tr>
        <th>No</th>
        <th>Nama Warga</th>
        <th>Gang</th>
        <th>Tanggal Bayar</th>
        <th>Nominal</th>
    </tr>
    <?php if (count($pemasukan) > 0): ?>
        <?php foreach ($pemasukan as $idx => $p): 
            $totalMasuk += floatval($p['NOMINAL']);
        ?>
        <tr>
            <td class="center"><?php echo $idx + 1; ?></td>
            <td><?php echo $p['NAMA']; ?></td>
            <td>Gang <?php echo $p['GANG']; ?></td>
            <td><?php echo date('d-m-Y', strtotime($p['TANGGAL_BAYAR'])); ?></td>
            <td class="right">Rp <?php echo number_format($p['NOMINAL'], 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="5" class="center">Belum ada pemasukan bulan ini</td></tr>
    <?php endif; ?>
    <tr>
        <td colspan="4" class="right" style="font-weight: bold;">Total Kas Masuk</td>
        <td class="right" style="font-weight: bold;">Rp <?php echo number_format($totalMasuk, 0, ',', '.'); ?></td>
    </tr>
    
    <tr><td colspan="5" class="no-border"></td></tr>

    <!-- BAGIAN 2: KAS KELUAR -->
    <tr>
        <td colspan="5" style="font-weight: bold; background-color: #f4cccc;">BAGIAN 2 - Kas Keluar</td>
    </tr>
    <tr>
        <th>No</th>
        <th colspan="2">Keterangan</th>
        <th>Tanggal</th>
        <th>Nominal</th>
    </tr>
    <?php if (count($pengeluaran) > 0): ?>
        <?php foreach ($pengeluaran as $idx => $p): 
            $totalKeluar += floatval($p['NOMINAL_P']);
        ?>
        <tr>
            <td class="center"><?php echo $idx + 1; ?></td>
            <td colspan="2"><?php echo $p['KETERANGAN_P']; ?></td>
            <td><?php echo date('d-m-Y', strtotime($p['TANGGAL_P'])); ?></td>
            <td class="right">Rp <?php echo number_format($p['NOMINAL_P'], 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="5" class="center">Tidak ada pengeluaran bulan ini</td></tr>
    <?php endif; ?>
    <tr>
        <td colspan="4" class="right" style="font-weight: bold;">Total Kas Keluar</td>
        <td class="right" style="font-weight: bold;">Rp <?php echo number_format($totalKeluar, 0, ',', '.'); ?></td>
    </tr>

    <tr><td colspan="5" class="no-border"></td></tr>

    <!-- BAGIAN 3: RINGKASAN -->
    <tr>
        <td colspan="5" style="font-weight: bold; background-color: #cfe2f3;">BAGIAN 3 - Ringkasan Saldo</td>
    </tr>
    <tr>
        <td colspan="4" class="right">Saldo Awal</td>
        <td class="right">Rp <?php echo number_format($saldoAwal, 0, ',', '.'); ?></td>
    </tr>
    <tr>
        <td colspan="4" class="right">Total Kas Masuk</td>
        <td class="right">Rp <?php echo number_format($totalMasuk, 0, ',', '.'); ?></td>
    </tr>
    <tr>
        <td colspan="4" class="right">Total Kas Keluar</td>
        <td class="right">Rp <?php echo number_format($totalKeluar, 0, ',', '.'); ?></td>
    </tr>
    <tr>
        <td colspan="4" class="right" style="font-weight: bold;">Saldo Akhir</td>
        <td class="right" style="font-weight: bold;">Rp <?php echo number_format($saldoAwal + $totalMasuk - $totalKeluar, 0, ',', '.'); ?></td>
    </tr>

</table>
</body>
</html>
<?php 
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
