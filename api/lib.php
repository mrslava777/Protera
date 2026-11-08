<?php
declare(strict_types=1);

/** Общие помощники: JSON, сессия, валидация, токены, лимиты. */

function cfg(?string $key = null)
{
    global $CONFIG;
    if ($key === null) {
        return $CONFIG;
    }
    $value = $CONFIG;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }
    return $value;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $days = (int)(cfg('security.remember_days') ?: 30);
    ini_set('session.gc_maxlifetime', (string)($days * 86400));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('protera_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function remember_cookie(): void
{
    $days = (int)(cfg('security.remember_days') ?: 30);
    setcookie(session_name(), session_id(), [
        'expires'  => time() + $days * 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $code, string $message, int $status = 400): void
{
    json_out(['ok' => false, 'code' => $code, 'message' => $message], $status);
}

function request_body(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    $data = is_array($json) ? $json : $_POST;
    return $data;
}

function field(string $name): string
{
    $value = request_body()[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        fail('method_not_allowed', 'Метод не поддерживается', 405);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        fail('csrf', 'Сессия устарела. Обновите страницу и попробуйте снова.', 419);
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function normalize_phone(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === null || $digits === '') {
        return null;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '80')) {
        $digits = '375' . substr($digits, 2);
    }
    if (strlen($digits) === 9) {
        $digits = '375' . $digits;
    }
    if (strlen($digits) !== 12 || !str_starts_with($digits, '375')) {
        return null;
    }
    return '+' . $digits;
}

function valid_email(string $email): bool
{
    return $email !== ''
        && mb_strlen($email) <= 190
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function password_problem(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Пароль должен содержать минимум 8 символов';
    }
    if (strlen($password) > 100) {
        return 'Пароль слишком длинный (максимум 100 символов)';
    }
    if (!preg_match('/[A-Za-zА-Яа-яЁё]/u', $password) || !preg_match('/\d/', $password)) {
        return 'Пароль должен содержать хотя бы одну букву и одну цифру';
    }
    return null;
}

/** Поиск пользователя по e-mail или телефону. */
function find_user_by_login(string $login): ?array
{
    $login = trim($login);
    if (valid_email($login)) {
        return db_one('SELECT * FROM users WHERE email = ?', [mb_strtolower($login)]);
    }
    $phone = normalize_phone($login);
    if ($phone !== null) {
        return db_one('SELECT * FROM users WHERE phone = ?', [$phone]);
    }
    return null;
}

function current_user(): ?array
{
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return null;
    }
    $user = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
    if (!$user) {
        unset($_SESSION['uid']);
        return null;
    }
    // После смены/сброса пароля старые сессии недействительны.
    $signedAt = (int)($_SESSION['at'] ?? 0);
    if ($signedAt < strtotime((string)$user['pass_changed_at'])) {
        unset($_SESSION['uid']);
        return null;
    }
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        fail('auth', 'Нужно войти в аккаунт', 401);
    }
    return $user;
}

function public_user(array $user): array
{
    return [
        'id'             => (int)$user['id'],
        'name'           => (string)$user['name'],
        'email'          => (string)$user['email'],
        'phone'          => (string)$user['phone'],
        'city'           => (string)($user['city'] ?? ''),
        'address'        => (string)($user['address'] ?? ''),
        'email_verified' => (int)$user['email_verified'],
        'created_at'     => (string)$user['created_at'],
    ];
}

/* ---------- Одноразовые токены ---------- */

function issue_token(int $userId, string $type, int $ttl): string
{
    $token = bin2hex(random_bytes(32));
    db_exec('DELETE FROM tokens WHERE expires_at < ?', [now()]);
    db_exec('DELETE FROM tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
    db_exec(
        'INSERT INTO tokens (user_id, type, token_hash, expires_at, created_at, ip) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $type, hash('sha256', $token), date('Y-m-d H:i:s', time() + $ttl), now(), client_ip()]
    );
    return $token;
}

function find_token(string $token, string $type): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return db_one(
        'SELECT * FROM tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > ?',
        [hash('sha256', $token), $type, now()]
    );
}

function use_token(int $tokenId): void
{
    db_exec('UPDATE tokens SET used_at = ? WHERE id = ?', [now(), $tokenId]);
}

/* ---------- Ограничение частоты ---------- */

function count_events(string $kind, string $ident, int $windowSeconds): int
{
    $since = date('Y-m-d H:i:s', time() - $windowSeconds);
    return (int)db_val(
        'SELECT COUNT(*) FROM events WHERE kind = ? AND ident = ? AND created_at > ?',
        [$kind, $ident, $since]
    );
}

function add_event(string $kind, string $ident): void
{
    if (random_int(1, 100) === 1) {
        db_exec('DELETE FROM events WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
    }
    db_exec(
        'INSERT INTO events (kind, ident, ip, created_at) VALUES (?, ?, ?, ?)',
        [$kind, mb_substr($ident, 0, 190), client_ip(), now()]
    );
}

function too_many(string $kind, string $ident, int $max, int $windowSeconds): bool
{
    return count_events($kind, $ident, $windowSeconds) >= $max;
}

function site_url(string $path = ''): string
{
    $base = rtrim((string)cfg('site_url'), '/');
    return $base . '/' . ltrim($path, '/');
}

/* ---------- Доставка ---------- */

/** Все города активных зон. */
function delivery_cities(): array
{
    $cities = [];
    foreach (db_all('SELECT cities FROM zones WHERE is_active = 1 ORDER BY sort, id') as $row) {
        $list = json_decode((string)$row['cities'], true);
        if (is_array($list)) {
            foreach ($list as $city) {
                $cities[(string)$city] = true;
            }
        }
    }
    return array_keys($cities);
}

/** Зона доставки для города (или null). */
function zone_for_city(string $city): ?array
{
    $city = trim($city);
    if ($city === '') {
        return null;
    }
    foreach (db_all('SELECT * FROM zones WHERE is_active = 1 ORDER BY sort, id') as $zone) {
        $cities = json_decode((string)$zone['cities'], true) ?: [];
        if (in_array($city, $cities, true)) {
            $zone['city_list'] = $cities;
            $zone['interval_list'] = json_decode((string)$zone['intervals'], true) ?: [];
            return $zone;
        }
    }
    return null;
}
