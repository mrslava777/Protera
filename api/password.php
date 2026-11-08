<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();
$user = require_user();

$current = (string)(request_body()['current'] ?? '');
$new     = (string)(request_body()['password'] ?? '');

if ($current === '') {
    fail('current', 'Введите текущий пароль');
}
if (!password_verify($current, (string)$user['pass_hash'])) {
    fail('current', 'Текущий пароль неверный', 403);
}
if (($problem = password_problem($new)) !== null) {
    fail('password', $problem);
}
if (hash_equals($current, $new)) {
    fail('password', 'Новый пароль должен отличаться от текущего');
}

$now = now();
db_exec(
    'UPDATE users SET pass_hash = ?, pass_changed_at = ?, updated_at = ? WHERE id = ?',
    [password_hash($new, PASSWORD_DEFAULT), $now, $now, (int)$user['id']]
);

// Переавторизуем текущую сессию, остальные сессии станут недействительными.
session_regenerate_id(true);
$_SESSION['uid']  = (int)$user['id'];
$_SESSION['at']   = time();
$_SESSION['csrf'] = bin2hex(random_bytes(16));

add_event('password_changed', (string)$user['email']);

json_out(['ok' => true, 'csrf' => csrf_token(), 'message' => 'Пароль обновлён']);
