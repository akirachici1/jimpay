<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['nama_kk']) || !isset($data['no_rumah'])) {
    responseJson(['success' => false, 'message' => 'Data tidak lengkap'], 400);
}

try {
    $id_warga = time();
    $nama = trim($data['nama_kk']);
    $gang = trim($data['no_rumah']);
    $username = isset($data['username']) && trim($data['username']) !== '' ? trim($data['username']) : strtolower(explode(' ', trim($nama))[0]);
    $password = isset($data['password']) && trim($data['password']) !== '' ? trim($data['password']) : '123';

    $stmtCek = $conn->prepare('SELECT ID_WARGA FROM WARGA WHERE USERNAME = ?');
    $stmtCek->execute([$username]);
    if ($stmtCek->fetch()) {
        $username = $username . rand(10, 99);
    }

    $stmt = $conn->prepare('INSERT INTO WARGA (ID_WARGA, NAMA, GANG, USERNAME, PASSWORD) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$id_warga, $nama, $gang, $username, hashPassword($password)]);

    responseJson(['success' => true, 'id' => $id_warga]);
} catch (PDOException $e) {
    responseJson(['success' => false, 'message' => 'Database error'], 500);
}
?>
