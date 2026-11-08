<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$token = (string)($_GET['token'] ?? '');
$row = find_token($token, 'reset');

if ($row === null) {
    fail('token', 'Ссылка для сброса недействительна или истекла.', 410);
}

$user = db_one('SELECT email FROM users WHERE id = ?', [(int)$row['user_id']]);
if ($user === null) {
    fail('token', 'Ссылка для сброса недействительна.', 410);
}

/** iv***@test.by */
function mask_email(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = mb_substr($name, 0, 2);
    return $visible . str_repeat('*', max(2, min(6, mb_strlen($name) - 2))) . '@' . $domain;
}

json_out([
    'ok'    => true,
    'email' => mask_email((string)$user['email']),
]);
