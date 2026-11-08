<?php
// Shared application bootstrap. Secrets are read from the server environment only.
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
             (getenv('APP_FORCE_HTTPS') === '1');
    session_name('career_compass_sid');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https,
                               'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function env_value(string $name, string $fallback = ''): string {
    $value = getenv($name);
    return $value === false ? $fallback : (string)$value;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = env_value('DB_HOST', '127.0.0.1');
        $name = env_value('DB_NAME', 'career_guidance');
        $dsn = 'mysql:host=' . $host . ';port=' . env_value('DB_PORT', '3306') .
               ';dbname=' . $name . ';charset=utf8mb4';
        $pdo = new PDO($dsn, env_value('DB_USER', 'root'), env_value('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(?string $token): void {
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session has expired. Please go back, refresh the page, and try again.');
    }
}
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id,full_name,email,role,grade,school FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
function require_login(): void {
    if (!current_user()) { header('Location: login.php'); exit; }
}
function require_admin(): void {
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') { http_response_code(403); exit('Access denied'); }
}
function api_request(string $endpoint, array $payload, int $timeout = 25): ?array {
    if (!function_exists('curl_init')) return null;
    $base = rtrim(env_value('AI_API', 'http://127.0.0.1:5001'), '/');
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Internal-Token: ' . env_value('INTERNAL_API_TOKEN'),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false || $code !== 200) {
        error_log('Career Compass recommendation service unavailable, HTTP ' . (int)$code);
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}
function rate_limited(string $bucket, int $limit, int $window): bool {
    $now = time();
    $history = $_SESSION['rate_' . $bucket] ?? [];
    $history = array_values(array_filter($history, static fn($ts) => $ts > $now - $window));
    $blocked = count($history) >= $limit;
    if (!$blocked) $history[] = $now;
    $_SESSION['rate_' . $bucket] = $history;
    return $blocked;
}
