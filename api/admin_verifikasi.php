<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

try {
    $query = "SELECT p.ID_PEMBAYARAN, p.STATUS, p.TANGGAL_BAYAR, 
                     w.NAMA, w.GANG, 
                     pi.BULAN, pi.TAHUN, pi.NOMINAL,
                     b.BUKTI_PEMBAYARAN as file_name
              FROM PEMBAYARAN p
              JOIN WARGA w ON p.ID_WARGA = w.ID_WARGA
              JOIN PERIODE_IURAN pi ON p.ID_PERIODE = pi.ID_PERIODE
              LEFT JOIN BUKTI_PEMBAYARAN b ON p.ID_PEMBAYARAN = b.ID_PEMBAYARAN
              ORDER BY CASE WHEN p.STATUS = 'pending' THEN 1 ELSE 2 END, p.TANGGAL_BAYAR DESC";
    
    $stmt = $conn->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $results = [];

    foreach ($data as $d) {
        $words = explode(' ', $d['NAMA']);
        $inisial = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($words[0], 0, 2));

        $status = strtolower($d['STATUS']);
        $status_map = $status === 'lunas' ? 'terverifikasi' : $status;

        $file_name = $d['file_name'] ? $d['file_name'] : '';
        $emoji = '📄';
        if (preg_match('/\.(jpeg|jpg|png)$/i', $file_name)) {
            $emoji = '🖼️';
        }

        $results[] = [
            'id' => intval($d['ID_PEMBAYARAN']),
            'warga' => $d['NAMA'],
            'rumah' => 'Gang ' . $d['GANG'],
            'inisial' => $inisial,
            'bulan' => $bulanNames[intval($d['BULAN'])-1] . ' ' . $d['TAHUN'],
            'nominal' => 'Rp ' . number_format(floatval($d['NOMINAL']), 0, ',', '.'),
            'tanggal' => date('d M Y', strtotime($d['TANGGAL_BAYAR'])),
            'catatan' => '',
            'status' => $status_map,
            'alasan' => '',
            'file_name' => $file_name,
            'emoji' => $emoji
        ];
    }

    echo json_encode($results);

} catch(PDOException $e) {
    responseJson(['error' => 'Database error'], 500);
}
?>
