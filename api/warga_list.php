<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

try {
    $stmt = $conn->query('SELECT ID_WARGA, NAMA, GANG, USERNAME FROM WARGA ORDER BY ID_WARGA DESC');
    $wargas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($wargas as $w) {
        $words = explode(' ', $w['NAMA']);
        $inisial = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($words[0], 0, 2));

        $results[] = [
            'id' => intval($w['ID_WARGA']),
            'nama_kk' => $w['NAMA'],
            'no_rumah' => $w['GANG'],
            'inisial' => $inisial,
            'username' => $w['USERNAME']
        ];
    }
    echo json_encode($results);
} catch(PDOException $e) {
    responseJson(['error' => 'Database error'], 500);
}
?>
