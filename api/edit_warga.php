<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['nama_kk']) || !isset($data['no_rumah']) || !isset($data['username'])) {
    echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
    exit;
}

try {
    $id = intval($data['id']);
    $nama = $data['nama_kk'];
    $gang = $data['no_rumah'];
    $username = $data['username'];
    $password = isset($data['password']) ? trim($data['password']) : '';

    // Cek duplikat username pada user lain
    $stmtCek = $conn->prepare("SELECT ID_WARGA FROM WARGA WHERE USERNAME = ? AND ID_WARGA != ?");
    $stmtCek->execute([$username, $id]);
    if ($stmtCek->fetch()) {
        echo json_encode(["success" => false, "message" => "Username sudah digunakan warga lain. Pilih yang lain."]);
        exit;
    }

    if (!empty($password)) {
        $hashedPassword = hashPassword($password);
        $stmt = $conn->prepare("UPDATE WARGA SET NAMA = ?, GANG = ?, USERNAME = ?, PASSWORD = ? WHERE ID_WARGA = ?");
        $stmt->execute([$nama, $gang, $username, $hashedPassword, $id]);
    } else {
        $stmt = $conn->prepare("UPDATE WARGA SET NAMA = ?, GANG = ?, USERNAME = ? WHERE ID_WARGA = ?");
        $stmt->execute([$nama, $gang, $username, $id]);
    }

    echo json_encode(["success" => true]);

} catch(PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
