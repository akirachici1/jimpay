<?php
header('Content-Type: application/json');
require '../auth.php';
require '../koneksi.php';

requireAuth(['admin']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    responseJson(["success" => false, "message" => "ID tidak valid"], 400);
}

try {
    $stmt = $conn->prepare("DELETE FROM PENGELUARAN WHERE ID_PENGELUARAN = ?");
    $stmt->execute([$id]);

    echo json_encode(["success" => true]);

} catch(PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
