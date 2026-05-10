<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);
if (!is_array($data)) {
    responseJson(['success' => false, 'message' => 'Data tidak valid'], 400);
}

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = trim(strtolower($data['role'] ?? ''));

if ($username === '' || $password === '' || $role === '') {
    responseJson(['success' => false, 'message' => 'Username, password, dan role wajib diisi'], 400);
}

try {
    if ($role === 'admin') {
        $stmt = $conn->prepare('SELECT * FROM PENGURUS WHERE USER_ADMIN = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !verifyPassword($password, $user['PASSWORD_ADMIN'])) {
            responseJson(['success' => false, 'message' => 'Username atau password salah'], 401);
        }

        $token = createAuthToken(intval($user['ID_PENGURUS']), 'admin');
        responseJson([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => intval($user['ID_PENGURUS']),
                'role' => 'admin',
                'nama' => $user['USER_ADMIN'],
                'jabatan' => 'Pengurus RT'
            ]
        ]);
    } elseif ($role === 'warga') {
        $stmt = $conn->prepare('SELECT * FROM WARGA WHERE USERNAME = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !verifyPassword($password, $user['PASSWORD'])) {
            responseJson(['success' => false, 'message' => 'Username atau password salah'], 401);
        }

        $words = explode(' ', $user['NAMA']);
        if (count($words) >= 2) {
            $inisial = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            $inisial = strtoupper(substr($words[0], 0, 2));
        }

        $token = createAuthToken(intval($user['ID_WARGA']), 'warga');
        responseJson([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => intval($user['ID_WARGA']),
                'role' => 'warga',
                'nama' => $user['NAMA'],
                'rumah' => 'Gang ' . $user['GANG'],
                'inisial' => $inisial
            ]
        ]);
    } else {
        responseJson(['success' => false, 'message' => 'Role tidak dikenal'], 400);
    }
} catch (PDOException $e) {
    responseJson(['success' => false, 'message' => 'Database error'], 500);
}
?>
