<?php
// koneksi.php
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$driver = getenv('DB_DRIVER') ?: 'mysql';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: ($driver === 'sqlsrv' ? '1433' : '3306');
$dbName = getenv('DB_DATABASE') ?: 'jimpay_v2';
$dbUser = getenv('DB_USERNAME') ?: '';
$dbPassword = getenv('DB_PASSWORD') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    if ($driver === 'sqlsrv') {
        $dsn = "sqlsrv:server=$host,$port;Database=$dbName;TrustServerCertificate=true";
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=$charset";
    }
    $conn = new PDO($dsn, $dbUser, $dbPassword, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
?>
