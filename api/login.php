<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();

$login    = field('login');
$password = (string)(request_body()['password'] ?? '');
$remember = !empty(request_body()['remember']);

if ($login === '' || $password === '') {
    fail('fields', 'Введите e-mail/телефон и пароль');
}

$key = mb_strtolower($login);
if (too_many('login_fail', $key, 8, 900) || too_many('login_fail_ip', client_ip(), 30, 900)) {
    fail('rate', 'Слишком много неудачных попыток. Повторите через 15 минут.', 429);
}

$user = find_user_by_login($login);

if ($user === null || !password_verify($password, (string)$user['pass_hash'])) {
    add_event('login_fail', $key);
    add_event('login_fail_ip', client_ip());
    fail('invalid_credentials', 'Неверный e-mail/телефон или пароль', 401);
}

if ((int)$user['email_verified'] !== 1) {
    fail('email_not_verified', 'E-mail не подтверждён. Откройте письмо или запросите его повторно.', 403);
}

if (password_needs_rehash((string)$user['pass_hash'], PASSWORD_DEFAULT)) {
    db_exec('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
}

session_regenerate_id(true);
$_SESSION['uid']  = (int)$user['id'];
$_SESSION['at']   = time();
$_SESSION['csrf'] = bin2hex(random_bytes(16));

if ($remember) {
    remember_cookie();
}

$now = now();
db_exec('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?', [$now, $now, (int)$user['id']]);
$user['last_login_at'] = $now;

json_out([
    'ok'    => true,
    'user'  => public_user($user),
    'csrf'  => csrf_token(),
]);
