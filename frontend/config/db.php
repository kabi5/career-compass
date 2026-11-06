<?php
// Shared application bootstrap. Secrets are read from the server environment only.
date_default_timezone_set('Africa/Maseru');
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    set_exception_handler(static function (Throwable $error): void {
        $incident = bin2hex(random_bytes(6));
        error_log('Career Compass request failed ['.$incident.'] '.get_class($error));
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'This request could not be completed. Please try again or contact the operator. Reference: '.$incident;
    });
}
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
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
function csp_nonce(): string {
    static $nonce = null;
    if ($nonce === null) $nonce = base64_encode(random_bytes(18));
    return $nonce;
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
if (getenv('APP_FORCE_HTTPS') === '1') header('Strict-Transport-Security: max-age=31536000');

function env_value(string $name, string $fallback = ''): string {
    $value = getenv($name);
    return $value === false ? $fallback : (string)$value;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if(env_value('APP_ENV')==='production' && (strlen(env_value('INTERNAL_API_TOKEN'))<32 || str_contains(env_value('INTERNAL_API_TOKEN'),'REPLACE_WITH') || env_value('DB_PASSWORD')==='' || str_contains(env_value('DB_PASSWORD'),'REPLACE_WITH')))throw new RuntimeException('Configure production secrets before starting the application.');
        $host = env_value('DB_HOST', '127.0.0.1');
        $name = env_value('DB_NAME', 'career_guidance');
        $dsn = 'mysql:host=' . $host . ';port=' . env_value('DB_PORT', '3306') .
               ';dbname=' . $name . ';charset=utf8mb4';
        $pdo = new PDO($dsn, env_value('DB_USER', 'root'), env_value('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
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
function verify_csrf($token): void {
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session has expired. Please go back, refresh the page, and try again.');
    }
}
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    if (time() - (int)($_SESSION['last_active'] ?? 0) > 1800 || time() - (int)($_SESSION['signed_in_at'] ?? 0) > 28800) {
        unset($_SESSION['user_id'], $_SESSION['auth_version']);
        return null;
    }
    $stmt = db()->prepare('SELECT u.id,u.full_name,u.email,u.role,u.grade,u.school,s.school_id,s.email_verified_at,COALESCE(s.auth_version,1) AS auth_version FROM users u LEFT JOIN user_security s ON s.user_id=u.id WHERE u.id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || (int)($_SESSION['auth_version'] ?? 0) !== (int)$user['auth_version']) {
        unset($_SESSION['user_id'], $_SESSION['auth_version']);
        return null;
    }
    $_SESSION['last_active'] = time();
    return $user;
}
function require_login(): void {
    if (!current_user()) { header('Location: login.php'); exit; }
}
function require_admin(): void {
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') { http_response_code(403); exit('Access denied'); }
}
function require_teacher(): void {
    $user = current_user();
    if (!$user || !in_array($user['role'], ['teacher','admin'], true)) { http_response_code(403); exit('Access denied'); }
}
function api_request(string $endpoint, array $payload, int $timeout = 25): ?array {
    if (!function_exists('curl_init')) return null;
    if(in_array($endpoint,['/recommend','/compare'],true)&&function_exists('catalogue'))$payload['programmes']=catalogue('programmes');
    // PHP encodes an empty array as []; these API fields are named JSON objects.
    foreach (['riasec_scores','marks','subject_grades','baseline_grades','scenario_grades'] as $key) {
        if (array_key_exists($key,$payload) && is_array($payload[$key])) $payload[$key]=(object)$payload[$key];
    }
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
    // Remote address is server supplied; forwarded client headers are never trusted.
    // An IP-wide key prevents cookie/session rotation from bypassing the limit.
    return security_rate_limit($bucket . '|ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), $limit, $window);
}
require_once __DIR__ . '/security.php';
