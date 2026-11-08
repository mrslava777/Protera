<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();
$user = require_user();

$password = (string)(request_body()['password'] ?? '');
if ($password === '' || !password_verify($password, (string)$user['pass_hash'])) {
    fail('password', 'Пароль неверный — аккаунт не удалён', 403);
}

db_exec('DELETE FROM users WHERE id = ?', [(int)$user['id']]);
add_event('account_deleted', (string)$user['email']);

$_SESSION = [];
session_destroy();

json_out(['ok' => true, 'message' => 'Аккаунт удалён']);
