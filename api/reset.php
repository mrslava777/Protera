<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();

$token    = (string)(request_body()['token'] ?? '');
$password = (string)(request_body()['password'] ?? '');

if (($problem = password_problem($password)) !== null) {
    fail('password', $problem);
}
if (too_many('reset_try', client_ip(), 10, 3600)) {
    fail('rate', 'Слишком много попыток. Попробуйте через час.', 429);
}
add_event('reset_try', client_ip());

$row = find_token($token, 'reset');
if ($row === null) {
    fail('token', 'Ссылка для сброса недействительна или истекла. Запросите новую.', 410);
}

$user = db_one('SELECT * FROM users WHERE id = ?', [(int)$row['user_id']]);
if ($user === null) {
    fail('token', 'Ссылка для сброса недействительна. Запросите новую.', 410);
}

use_token((int)$row['id']);
$now = now();
db_exec(
    'UPDATE users SET pass_hash = ?, pass_changed_at = ?, email_verified = 1, verified_at = COALESCE(verified_at, ?), updated_at = ? WHERE id = ?',
    [password_hash($password, PASSWORD_DEFAULT), $now, $now, $now, (int)$user['id']]
);
db_exec('DELETE FROM tokens WHERE user_id = ? AND type = ?', [(int)$user['id'], 'reset']);

// Все активные сессии пользователя должны переавторизоваться.
unset($_SESSION['uid'], $_SESSION['at']);
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

add_event('password_reset', (string)$user['email']);

json_out([
    'ok'      => true,
    'email'   => (string)$user['email'],
    'message' => 'Пароль изменён. Войдите с новым паролем.',
]);
