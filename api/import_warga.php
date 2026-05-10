<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data) || count($data) === 0) {
    responseJson(['success' => false, 'message' => 'Data kosong atau format salah.'], 400);
}

try {
    $conn->beginTransaction();
    $berhasil = 0;

    $stmtCek = $conn->prepare('SELECT ID_WARGA FROM WARGA WHERE USERNAME = ?');
    $stmtInsert = $conn->prepare('INSERT INTO WARGA (ID_WARGA, NAMA, GANG, USERNAME, PASSWORD) VALUES (?, ?, ?, ?, ?)');

    foreach ($data as $w) {
        $nama = trim($w['nama']);
        $gang = trim($w['gang']);
        $username = trim($w['username']);
        $password = trim($w['password'] ?? '');

        if (empty($nama) || empty($username)) {
            continue;
        }

        $stmtCek->execute([$username]);
        if ($stmtCek->fetch()) {
            $username = $username . rand(10, 99);
        }

        $id_warga = rand(1000000, 9999999);
        $stmtCekId = $conn->prepare('SELECT ID_WARGA FROM WARGA WHERE ID_WARGA = ?');
        $stmtCekId->execute([$id_warga]);
        while ($stmtCekId->fetch()) {
            $id_warga = rand(1000000, 9999999);
            $stmtCekId->execute([$id_warga]);
        }

        $password = $password !== '' ? $password : '123';
        $stmtInsert->execute([$id_warga, $nama, $gang, $username, hashPassword($password)]);
        $berhasil++;
    }

    $conn->commit();
    responseJson(['success' => true, 'message' => "$berhasil warga berhasil diimpor!"]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    responseJson(['success' => false, 'message' => 'Database error'], 500);
}
?>
