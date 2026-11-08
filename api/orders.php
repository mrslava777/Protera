<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_user();
$userId = (int)$user['id'];

function order_public(array $row): array
{
    return [
        'id'            => (int)$row['id'],
        'number'        => (string)$row['number'],
        'city'          => (string)$row['city'],
        'address_text'  => (string)$row['address_text'],
        'zone'          => (string)$row['zone'],
        'interval'      => (string)$row['interval'],
        'payment_code'  => (string)$row['payment_code'],
        'payment_title' => (string)$row['payment_title'],
        'total'         => (float)$row['total'],
        'status'        => (string)$row['status'],
        'comment'       => (string)$row['comment'],
        'items'         => json_decode((string)$row['items'], true) ?: [],
        'created_at'    => (string)$row['created_at'],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $rows = db_all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]);
    json_out(['ok' => true, 'orders' => array_map('order_public', $rows)]);
}

require_post();
csrf_check();

$in = request_body();
$rawItems = $in['items'] ?? [];
$addressId = (int)($in['address_id'] ?? 0);
$interval = trim((string)($in['interval'] ?? ''));
$paymentCode = trim((string)($in['payment_code'] ?? ''));
$comment = trim((string)($in['comment'] ?? ''));

if (!is_array($rawItems) || !$rawItems) {
    fail('items', 'Корзина пуста');
}
if (count($rawItems) > 50) {
    fail('items', 'Слишком много позиций в заказе');
}
if (mb_strlen($comment) > 500) {
    fail('comment', 'Комментарий слишком длинный');
}

$address = db_one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$addressId, $userId]);
if (!$address) {
    fail('address', 'Выберите адрес доставки');
}
$zone = zone_for_city((string)$address['city']);
if ($zone === null) {
    fail('address', 'По этому городу доставка недоступна');
}
if (!in_array($interval, $zone['interval_list'], true)) {
    fail('interval', 'Выберите интервал доставки');
}
$payment = db_one('SELECT * FROM payments WHERE code = ? AND is_active = 1', [$paymentCode]);
if (!$payment) {
    fail('payment', 'Выберите способ оплаты');
}

$items = [];
$total = 0.0;
foreach ($rawItems as $raw) {
    $pid = (int)($raw['id'] ?? 0);
    $qty = (int)($raw['qty'] ?? 0);
    if ($pid <= 0 || $qty < 1 || $qty > 99) {
        continue;
    }
    $product = db_one('SELECT * FROM products WHERE id = ? AND is_active = 1', [$pid]);
    if (!$product) {
        fail('items', 'Один из товаров больше недоступен — обновите корзину');
    }
    $price = (float)$product['price'];
    $items[] = [
        'id'    => (int)$product['id'],
        'name'  => (string)$product['name'],
        'price' => $price,
        'qty'   => $qty,
    ];
    $total += $price * $qty;
}
if (!$items) {
    fail('items', 'Корзина пуста');
}
$total = round($total, 2);

$addressText = trim($address['street'] . ($address['apartment'] !== '' ? ', кв. ' . $address['apartment'] : ''));
$addressText = mb_substr($addressText, 0, 255);

$nextId = (int)db_val('SELECT COALESCE(MAX(id), 0) + 1 FROM orders');
$number = '№ ' . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
$now = now();

db_exec(
    'INSERT INTO orders (number, user_id, address_id, city, address_text, zone, interval, payment_code, payment_title, total, status, comment, items, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $number, $userId, $addressId, (string)$address['city'], $addressText, (string)$zone['name'], $interval,
        (string)$payment['code'], (string)$payment['title'], $total, 'Новый', $comment,
        json_encode($items, JSON_UNESCAPED_UNICODE), $now, $now,
    ]
);

$order = db_one('SELECT * FROM orders WHERE id = ?', [db_id()]);
add_event('order_created', $number);

json_out(['ok' => true, 'order' => order_public($order), 'message' => 'Заказ ' . $number . ' оформлен']);
