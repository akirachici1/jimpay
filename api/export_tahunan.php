<?php
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : 0;

if ($tahun === 0) {
    die("Parameter tahun tidak valid.");
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Kas_Tahunan_RT06_$tahun.xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // 1. Ambil Pemasukan (Jimpitan) Tahun Ini
    $stmtMasuk = $conn->prepare("
        SELECT w.NAMA, w.GANG, pi.BULAN, p.TANGGAL_BAYAR, pi.NOMINAL 
        FROM PEMBAYARAN p
        JOIN WARGA w ON p.ID_WARGA = w.ID_WARGA
        JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE
        WHERE pi.TAHUN = ? AND LOWER(p.STATUS) = 'lunas'
        ORDER BY pi.BULAN ASC, p.TANGGAL_BAYAR ASC
    ");
    $stmtMasuk->execute([$tahun]);
    $pemasukan = $stmtMasuk->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil Pengeluaran Tahun Ini
    $stmtKeluar = $conn->prepare("
        SELECT KETERANGAN_P, TANGGAL_P, NOMINAL_P 
        FROM PENGELUARAN 
        WHERE YEAR(TANGGAL_P) = ?
        ORDER BY TANGGAL_P ASC
    ");
    $stmtKeluar->execute([$tahun]);
    $pengeluaran = $stmtKeluar->fetchAll(PDO::FETCH_ASSOC);

    // 3. Hitung Saldo Awal (semua tahun sebelum tahun yang dipilih)
    $stmtAllMasukPrev = $conn->prepare("
        SELECT SUM(pi.NOMINAL) as total
        FROM PEMBAYARAN p
        JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE
        WHERE pi.TAHUN < ? AND LOWER(p.STATUS) = 'lunas'
    ");
    $stmtAllMasukPrev->execute([$tahun]);
    $totalMasukPrev = floatval($stmtAllMasukPrev->fetch(PDO::FETCH_ASSOC)['total']);

    $stmtAllKeluarPrev = $conn->prepare("
        SELECT SUM(NOMINAL_P) as total
        FROM PENGELUARAN
        WHERE YEAR(TANGGAL_P) < ?
    ");
    $stmtAllKeluarPrev->execute([$tahun]);
    $totalKeluarPrev = floatval($stmtAllKeluarPrev->fetch(PDO::FETCH_ASSOC)['total']);

    $saldoAwal = $totalMasukPrev - $totalKeluarPrev;

    $totalMasuk = 0;
    $totalKeluar = 0;
    
    $bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kas Tahunan RT 06</title>
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
        <td colspan="6" class="title no-border">LAPORAN KAS TAHUNAN RT 06</td>
    </tr>
    <tr>
        <td colspan="6" class="subtitle no-border">Periode: Tahun <?php echo $tahun; ?></td>
    </tr>
    <tr>
        <td colspan="6" class="subtitle no-border">Tanggal Cetak: <?php echo date('d M Y'); ?></td>
    </tr>
    <tr><td colspan="6" class="no-border"></td></tr>

    <!-- BAGIAN 1: KAS MASUK -->
    <tr>
        <td colspan="6" style="font-weight: bold; background-color: #d9ead3;">BAGIAN 1 - Rincian Kas Masuk (Selama <?php echo $tahun; ?>)</td>
    </tr>
    <tr>
        <th>No</th>
        <th>Bulan Tagihan</th>
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
            <td><?php echo $bulanNames[$p['BULAN'] - 1]; ?></td>
            <td><?php echo $p['NAMA']; ?></td>
            <td>Gang <?php echo $p['GANG']; ?></td>
            <td><?php echo date('d-m-Y', strtotime($p['TANGGAL_BAYAR'])); ?></td>
            <td class="right">Rp <?php echo number_format($p['NOMINAL'], 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="6" class="center">Belum ada pemasukan di tahun ini</td></tr>
    <?php endif; ?>
    <tr>
        <td colspan="5" class="right" style="font-weight: bold;">Total Kas Masuk</td>
        <td class="right" style="font-weight: bold;">Rp <?php echo number_format($totalMasuk, 0, ',', '.'); ?></td>
    </tr>
    
    <tr><td colspan="6" class="no-border"></td></tr>

    <!-- BAGIAN 2: KAS KELUAR -->
    <tr>
        <td colspan="6" style="font-weight: bold; background-color: #f4cccc;">BAGIAN 2 - Rincian Kas Keluar (Selama <?php echo $tahun; ?>)</td>
    </tr>
    <tr>
        <th>No</th>
        <th colspan="3">Keterangan</th>
        <th>Tanggal</th>
        <th>Nominal</th>
    </tr>
    <?php if (count($pengeluaran) > 0): ?>
        <?php foreach ($pengeluaran as $idx => $p): 
            $totalKeluar += floatval($p['NOMINAL_P']);
        ?>
        <tr>
            <td class="center"><?php echo $idx + 1; ?></td>
            <td colspan="3"><?php echo $p['KETERANGAN_P']; ?></td>
            <td><?php echo date('d-m-Y', strtotime($p['TANGGAL_P'])); ?></td>
            <td class="right">Rp <?php echo number_format($p['NOMINAL_P'], 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="6" class="center">Tidak ada pengeluaran di tahun ini</td></tr>
    <?php endif; ?>
    <tr>
        <td colspan="5" class="right" style="font-weight: bold;">Total Kas Keluar</td>
        <td class="right" style="font-weight: bold;">Rp <?php echo number_format($totalKeluar, 0, ',', '.'); ?></td>
    </tr>

    <tr><td colspan="6" class="no-border"></td></tr>

    <!-- BAGIAN 3: RINGKASAN -->
    <tr>
        <td colspan="6" style="font-weight: bold; background-color: #cfe2f3;">BAGIAN 3 - Ringkasan Saldo Tahunan</td>
    </tr>
    <tr>
        <td colspan="5" class="right">Saldo Awal (Dari tahun sebelumnya)</td>
        <td class="right">Rp <?php echo number_format($saldoAwal, 0, ',', '.'); ?></td>
    </tr>
    <tr>
        <td colspan="5" class="right">Total Kas Masuk (<?php echo $tahun; ?>)</td>
        <td class="right">Rp <?php echo number_format($totalMasuk, 0, ',', '.'); ?></td>
    </tr>
    <tr>
        <td colspan="5" class="right">Total Kas Keluar (<?php echo $tahun; ?>)</td>
        <td class="right">Rp <?php echo number_format($totalKeluar, 0, ',', '.'); ?></td>
    </tr>
    <tr>
        <td colspan="5" class="right" style="font-weight: bold;">Saldo Akhir</td>
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
