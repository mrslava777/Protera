<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$settings = [];
foreach (db_all('SELECT skey, svalue FROM settings') as $row) {
    $settings[$row['skey']] = (string)$row['svalue'];
}

$categories = db_all('SELECT skey, name FROM categories WHERE is_active = 1 ORDER BY sort, id');

$products = db_all(
    'SELECT id, slug, name, category, volume, short_desc, description, price, old_price, icon, image, badge
     FROM products WHERE is_active = 1 ORDER BY sort, id'
);
foreach ($products as &$p) {
    $p['id'] = (int)$p['id'];
    $p['price'] = (float)$p['price'];
    $p['old_price'] = $p['old_price'] === null ? null : (float)$p['old_price'];
}
unset($p);

$zones = [];
foreach (db_all('SELECT id, name, cities, intervals, note FROM zones WHERE is_active = 1 ORDER BY sort, id') as $row) {
    $zones[] = [
        'id'        => (int)$row['id'],
        'name'      => (string)$row['name'],
        'cities'    => json_decode((string)$row['cities'], true) ?: [],
        'intervals' => json_decode((string)$row['intervals'], true) ?: [],
        'note'      => (string)$row['note'],
    ];
}

$payments = db_all('SELECT code, title, note FROM payments WHERE is_active = 1 ORDER BY sort, id');

json_out([
    'ok'         => true,
    'settings'   => $settings,
    'categories' => $categories,
    'products'   => $products,
    'zones'      => $zones,
    'payments'   => $payments,
]);
