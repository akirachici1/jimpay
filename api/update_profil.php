<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['old_password']) || !isset($data['new_password'])) {
    responseJson(['success' => false, 'message' => 'Data tidak lengkap'], 400);
}

$payload = requireAuth(['warga', 'admin']);
$oldPass = $data['old_password'];
$newPass = trim($data['new_password']);
$newUsername = isset($data['username']) ? trim($data['username']) : '';

try {
    if ($payload['role'] === 'warga') {
        $stmtCek = $conn->prepare('SELECT PASSWORD FROM WARGA WHERE ID_WARGA = ?');
        $stmtCek->execute([$payload['uid']]);
        $row = $stmtCek->fetch(PDO::FETCH_ASSOC);

        if (!$row || !verifyPassword($oldPass, $row['PASSWORD'])) {
            responseJson(['success' => false, 'message' => 'Password lama salah'], 401);
        }

        if (!empty($newPass)) {
            $stmtUp = $conn->prepare('UPDATE WARGA SET PASSWORD = ? WHERE ID_WARGA = ?');
            $stmtUp->execute([hashPassword($newPass), $payload['uid']]);
        }
    } elseif ($payload['role'] === 'admin') {
        $stmtCek = $conn->prepare('SELECT PASSWORD_ADMIN FROM PENGURUS WHERE ID_PENGURUS = ?');
        $stmtCek->execute([$payload['uid']]);
        $row = $stmtCek->fetch(PDO::FETCH_ASSOC);

        if (!$row || !verifyPassword($oldPass, $row['PASSWORD_ADMIN'])) {
            responseJson(['success' => false, 'message' => 'Password lama salah'], 401);
        }

        if (!empty($newUsername)) {
            $stmtDup = $conn->prepare('SELECT ID_PENGURUS FROM PENGURUS WHERE USER_ADMIN = ? AND ID_PENGURUS != ?');
            $stmtDup->execute([$newUsername, $payload['uid']]);
            if ($stmtDup->fetch()) {
                responseJson(['success' => false, 'message' => 'Username sudah dipakai. Pilih yang lain.'], 409);
            }
        }

        $params = [];
        $clauses = [];
        if (!empty($newUsername)) {
            $clauses[] = 'USER_ADMIN = ?';
            $params[] = $newUsername;
        }
        if (!empty($newPass)) {
            $clauses[] = 'PASSWORD_ADMIN = ?';
            $params[] = hashPassword($newPass);
        }

        if (!empty($clauses)) {
            $query = 'UPDATE PENGURUS SET ' . implode(', ', $clauses) . ' WHERE ID_PENGURUS = ?';
            $params[] = $payload['uid'];
            $stmtUp = $conn->prepare($query);
            $stmtUp->execute($params);
        }
    }

    responseJson(['success' => true]);
} catch (PDOException $e) {
    responseJson(['success' => false, 'message' => 'Database error'], 500);
}
?>
