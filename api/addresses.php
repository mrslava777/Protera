<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_user();

function address_list(int $userId): array
{
    $rows = db_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$userId]);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_default'] = (int)$r['is_default'];
    }
    unset($r);
    return $rows;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => true, 'addresses' => address_list((int)$user['id'])]);
}

csrf_check();
$action = field('action') ?: 'save';
$userId = (int)$user['id'];

if ($action === 'delete') {
    $id = (int)request_body()['id'];
    $row = db_one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    if (!$row) {
        fail('not_found', 'Адрес не найден', 404);
    }
    db_exec('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    if ((int)$row['is_default'] === 1) {
        $first = db_one('SELECT id FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        if ($first) {
            db_exec('UPDATE addresses SET is_default = 1 WHERE id = ?', [(int)$first['id']]);
        }
    }
    json_out(['ok' => true, 'addresses' => address_list($userId), 'message' => 'Адрес удалён']);
}

if ($action === 'default') {
    $id = (int)request_body()['id'];
    $row = db_one('SELECT id FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    if (!$row) {
        fail('not_found', 'Адрес не найден', 404);
    }
    db_exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
    db_exec('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    json_out(['ok' => true, 'addresses' => address_list($userId), 'message' => 'Основной адрес обновлён']);
}

/* сохранение (новый или существующий) */
$id      = (int)(request_body()['id'] ?? 0);
$label   = trim(field('label'));
$city    = trim(field('city'));
$street  = trim(field('street'));
$apartment = trim(field('apartment'));
$entrance  = trim(field('entrance'));
$floor     = trim(field('floor'));
$comment   = trim(field('comment'));
$makeDefault = !empty(request_body()['is_default']);

if (mb_strlen($label) > 60 || mb_strlen($street) < 3 || mb_strlen($street) > 200) {
    fail('street', 'Укажите улицу, дом и квартиру');
}
if (!in_array($city, delivery_cities(), true)) {
    fail('city', 'Выберите город из зоны доставки');
}
if (mb_strlen($comment) > 255) {
    fail('comment', 'Комментарий слишком длинный');
}
if ($id === 0 && (int)db_val('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]) >= 10) {
    fail('limit', 'Можно сохранить не больше 10 адресов');
}

$now = now();

if ($id > 0) {
    $exists = db_one('SELECT id FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    if (!$exists) {
        fail('not_found', 'Адрес не найден', 404);
    }
    db_exec(
        'UPDATE addresses SET label=?, city=?, street=?, apartment=?, entrance=?, floor=?, comment=?, updated_at=? WHERE id=? AND user_id=?',
        [$label, $city, $street, $apartment, $entrance, $floor, $comment, $now, $id, $userId]
    );
    $message = 'Адрес обновлён';
} else {
    $isFirst = (int)db_val('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]) === 0;
    db_exec(
        'INSERT INTO addresses (user_id, label, city, street, apartment, entrance, floor, comment, is_default, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$userId, $label, $city, $street, $apartment, $entrance, $floor, $comment, ($isFirst || $makeDefault) ? 1 : 0, $now, $now]
    );
    $id = db_id();
    $message = 'Адрес добавлен';
}

if ($makeDefault) {
    db_exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
    db_exec('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
}

json_out(['ok' => true, 'addresses' => address_list($userId), 'message' => $message]);
