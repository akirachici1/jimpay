<?php
function loadEnvFile(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

$envPath = __DIR__ . '/../.env';
loadEnvFile($envPath);

$AUTH_SECRET = getenv('APP_KEY') ?: 'please-change-this-secret';
$TOKEN_EXPIRY_SECONDS = intval(getenv('TOKEN_EXPIRY_SECONDS') ?: 86400);

function responseJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function getAuthHeader(): ?string
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            return trim($headers['Authorization']);
        }
        if (isset($headers['authorization'])) {
            return trim($headers['authorization']);
        }
    }

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    return null;
}

function getBearerToken(): ?string
{
    $header = getAuthHeader();
    if (!$header) {
        return null;
    }

    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }

    return null;
}

function createAuthToken(int $userId, string $role): string
{
    global $AUTH_SECRET;
    $payload = [
        'uid' => $userId,
        'role' => $role,
        'iat' => time()
    ];
    $payloadJson = json_encode($payload);
    $encoded = strtr(base64_encode($payloadJson), '+/', '-_');
    $signature = hash_hmac('sha256', $encoded, $AUTH_SECRET);
    return $encoded . '.' . $signature;
}

function verifyAuthToken(string $token): ?array
{
    global $AUTH_SECRET, $TOKEN_EXPIRY_SECONDS;
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }

    list($encoded, $signature) = $parts;
    $expected = hash_hmac('sha256', $encoded, $AUTH_SECRET);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $json = base64_decode(strtr($encoded, '-_', '+/'));
    if ($json === false) {
        return null;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload) || !isset($payload['uid']) || !isset($payload['role']) || !isset($payload['iat'])) {
        return null;
    }

    if (time() - intval($payload['iat']) > $TOKEN_EXPIRY_SECONDS) {
        return null;
    }

    return $payload;
}

function requireAuth(array $allowedRoles = []): array
{
    $token = getBearerToken();
    if (!$token) {
        responseJson(['success' => false, 'message' => 'Unauthorized. Token missing.'], 401);
    }

    $payload = verifyAuthToken($token);
    if (!$payload) {
        responseJson(['success' => false, 'message' => 'Unauthorized. Token invalid or expired.'], 401);
    }

    if (!empty($allowedRoles) && !in_array($payload['role'], $allowedRoles, true)) {
        responseJson(['success' => false, 'message' => 'Forbidden. Insufficient permissions.'], 403);
    }

    return $payload;
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword(string $password, string $storedValue): bool
{
    if (password_verify($password, $storedValue)) {
        return true;
    }

    return hash_equals($password, $storedValue);
}
