<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();
$user = require_user();

$name  = field('name');
$phone = normalize_phone(field('phone'));

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    fail('name', 'Укажите имя (2–100 символов)');
}
if ($phone === null) {
    fail('phone', 'Введите телефон в формате +375 (29) 123-45-67');
}

$taken = db_one('SELECT id FROM users WHERE phone = ? AND id <> ?', [$phone, (int)$user['id']]);
if ($taken !== null) {
    fail('phone_taken', 'Этот телефон уже привязан к другому аккаунту.', 409);
}

$now = now();
db_exec(
    'UPDATE users SET name = ?, phone = ?, updated_at = ? WHERE id = ?',
    [$name, $phone, $now, (int)$user['id']]
);

$user = db_one('SELECT * FROM users WHERE id = ?', [(int)$user['id']]);

json_out([
    'ok'      => true,
    'user'    => public_user($user),
    'message' => 'Данные сохранены',
]);
