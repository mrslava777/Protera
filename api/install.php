<?php
/**
 * Однократная установка таблиц и стартовых данных. Запуск:
 *   https://ваш-сайт/api/install.php?key=КЛЮЧ_ИЗ_CONFIG
 * После успешной установки удалите этот файл.
 */
declare(strict_types=1);

define('PROTERA', true);
$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set((string)($CONFIG['timezone'] ?? 'Europe/Minsk'));
require __DIR__ . '/lib.php';
require __DIR__ . '/db.php';

$key = (string)($_GET['key'] ?? ($argv[1] ?? ''));
if ($key === '' || !hash_equals((string)($CONFIG['install_key'] ?? ''), $key)) {
    http_response_code(403);
    echo '403: укажите ?key= из api/config.php';
    exit;
}

$driver = (string)$CONFIG['db']['driver'];
$pdo = db();
$mysql = $driver !== 'sqlite';

$tables = [];

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    city VARCHAR(60) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    pass_hash VARCHAR(255) NOT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    pass_changed_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uniq_email (email), UNIQUE KEY uniq_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, phone TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL, city TEXT NOT NULL DEFAULT '', address TEXT NOT NULL DEFAULT '',
    pass_hash TEXT NOT NULL, email_verified INTEGER NOT NULL DEFAULT 0, verified_at TEXT NULL,
    pass_changed_at TEXT NOT NULL, last_login_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
    type VARCHAR(16) NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL,
    used_at DATETIME NULL, created_at DATETIME NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT '',
    PRIMARY KEY (id), KEY idx_tokens_hash (token_hash), KEY idx_tokens_user (user_id, type),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL,
    token_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT NULL, created_at TEXT NOT NULL,
    ip TEXT NOT NULL DEFAULT '', FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, kind VARCHAR(24) NOT NULL,
    ident VARCHAR(190) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '', created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_events_kind (kind, ident, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL, ident TEXT NOT NULL DEFAULT '',
    ip TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, skey VARCHAR(40) NOT NULL, name VARCHAR(100) NOT NULL,
    sort INT NOT NULL DEFAULT 100, is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id), UNIQUE KEY uniq_cat_key (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT, skey TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 100, is_active INTEGER NOT NULL DEFAULT 1
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL,
    category VARCHAR(40) NOT NULL, volume VARCHAR(40) NOT NULL DEFAULT '', short_desc VARCHAR(200) NOT NULL DEFAULT '',
    description TEXT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0, old_price DECIMAL(10,2) NULL,
    icon VARCHAR(40) NOT NULL DEFAULT '', image VARCHAR(255) NOT NULL DEFAULT '', badge VARCHAR(30) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uniq_product_slug (slug), KEY idx_product_cat (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
    category TEXT NOT NULL, volume TEXT NOT NULL DEFAULT '', short_desc TEXT NOT NULL DEFAULT '',
    description TEXT NULL, price REAL NOT NULL DEFAULT 0, old_price REAL NULL,
    icon TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '', badge TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 100,
    created_at TEXT NOT NULL, updated_at TEXT NOT NULL
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS addresses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
    label VARCHAR(60) NOT NULL DEFAULT '', city VARCHAR(60) NOT NULL, street VARCHAR(200) NOT NULL,
    apartment VARCHAR(20) NOT NULL DEFAULT '', entrance VARCHAR(20) NOT NULL DEFAULT '',
    floor VARCHAR(20) NOT NULL DEFAULT '', comment VARCHAR(255) NOT NULL DEFAULT '',
    is_default TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_addr_user (user_id),
    CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS addresses (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, label TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL, street TEXT NOT NULL, apartment TEXT NOT NULL DEFAULT '',
    entrance TEXT NOT NULL DEFAULT '', floor TEXT NOT NULL DEFAULT '', comment TEXT NOT NULL DEFAULT '',
    is_default INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS zones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, cities TEXT NOT NULL,
    intervals TEXT NOT NULL, note VARCHAR(255) NOT NULL DEFAULT '', sort INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS zones (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, cities TEXT NOT NULL, intervals TEXT NOT NULL,
    note TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 100, is_active INTEGER NOT NULL DEFAULT 1
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(40) NOT NULL, title VARCHAR(120) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '', sort INT NOT NULL DEFAULT 100, is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id), UNIQUE KEY uniq_payment_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, title TEXT NOT NULL,
    note TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 100, is_active INTEGER NOT NULL DEFAULT 1
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, number VARCHAR(20) NOT NULL, user_id INT UNSIGNED NOT NULL,
    address_id INT UNSIGNED NULL, city VARCHAR(60) NOT NULL, address_text VARCHAR(255) NOT NULL,
    zone VARCHAR(120) NOT NULL DEFAULT '', interval VARCHAR(80) NOT NULL DEFAULT '',
    payment_code VARCHAR(40) NOT NULL DEFAULT '', payment_title VARCHAR(120) NOT NULL DEFAULT '',
    total DECIMAL(10,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'Новый',
    comment VARCHAR(500) NOT NULL DEFAULT '', items TEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_orders_user (user_id, created_at), KEY idx_orders_status (status),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL, user_id INTEGER NOT NULL,
    address_id INTEGER NULL, city TEXT NOT NULL, address_text TEXT NOT NULL, zone TEXT NOT NULL DEFAULT '',
    interval TEXT NOT NULL DEFAULT '', payment_code TEXT NOT NULL DEFAULT '', payment_title TEXT NOT NULL DEFAULT '',
    total REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'Новый', comment TEXT NOT NULL DEFAULT '',
    items TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, login VARCHAR(60) NOT NULL, name VARCHAR(100) NOT NULL DEFAULT '',
    pass_hash VARCHAR(255) NOT NULL, last_login_at DATETIME NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uniq_admin_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, name TEXT NOT NULL DEFAULT '',
    pass_hash TEXT NOT NULL, last_login_at TEXT NULL, created_at TEXT NOT NULL
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS admin_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, admin_id INT UNSIGNED NULL, admin_login VARCHAR(60) NOT NULL DEFAULT '',
    action VARCHAR(40) NOT NULL, entity VARCHAR(40) NOT NULL DEFAULT '', entity_id VARCHAR(40) NOT NULL DEFAULT '',
    details VARCHAR(500) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '', created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS admin_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER NULL, admin_login TEXT NOT NULL DEFAULT '',
    action TEXT NOT NULL, entity TEXT NOT NULL DEFAULT '', entity_id TEXT NOT NULL DEFAULT '',
    details TEXT NOT NULL DEFAULT '', ip TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL
)";

$tables[] = $mysql ? "CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(60) NOT NULL, svalue TEXT NULL, PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "CREATE TABLE IF NOT EXISTS settings (
    skey TEXT NOT NULL PRIMARY KEY, svalue TEXT NULL
)";

$done = [];
try {
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    $done[] = 'Таблицы созданы/проверены: users, tokens, events, categories, products, addresses, zones, payments, orders, admins, admin_log, settings.';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Ошибка установки: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

$now = now();

/* ---------- категории ---------- */
if ((int)db_val('SELECT COUNT(*) FROM categories') === 0) {
    $cats = [
        ['water', 'Вода', 10], ['pumps', 'Помпы', 20], ['coolers', 'Кулеры и раздатчики', 30],
        ['access', 'Аксессуары', 40], ['dishes', 'Посуда', 50],
    ];
    foreach ($cats as [$k, $n, $s]) {
        db_exec('INSERT INTO categories (skey, name, sort, is_active) VALUES (?, ?, ?, 1)', [$k, $n, $s]);
    }
    $done[] = 'Добавлены категории каталога.';
}

/* ---------- товары ---------- */
if ((int)db_val('SELECT COUNT(*) FROM products') === 0) {
    $products = [
        ['protera19', 'Живая вода ПРОТЕРА 18,9 л', 'water', '18,9 л', 'Возвратная тара', 11.00, null, 'i-bottle', 'Хит',
            'Природная питьевая вода из подземного источника ледникового происхождения. Более 50 макро- и микроэлементов, оптимальное содержание фтора. Без водопровода и фильтров, изменяющих состав.'],
        ['pumpK7', 'Помпа электрическая К7', 'pumps', '', 'Аксессуары', 29.00, null, 'i-pump', '',
            'Электрическая помпа для бутыли 18,9 л: одним нажатием подаёт воду в стакан. Работает от двух батареек, подходит для любых стандартных бутылей.'],
        ['coolerV21', 'Кулер Ecotronic V21-TE', 'coolers', '', 'Для дома и офиса', 230.00, null, 'i-cooler-t', '',
            'Напольный кулер с нагревом и охлаждением воды. Два крана, защита от перегрева, компактный корпус. Подходит для дома и небольшого офиса.'],
        ['tap', 'Кран-клапан', 'access', '', 'Аксессуары', 10.00, null, 'i-tap', '',
            'Простой кран-клапан на бутыль: устанавливается без инструментов, не подтекает, легко снимается для мытья.'],
        ['light19', 'Протера Лайт 18,9 л', 'water', '18,9 л', 'Возвратная тара · без накипи', 11.00, null, 'i-bottle-light', '',
            'Очищенная вода со скорректированным составом. При кипячении не образует накипи — подходит для чая, кофе и детского питания.'],
        ['pumpA25', 'Помпа HotFrost А25', 'pumps', '', 'Аксессуары · механическая', 18.00, null, 'i-pump-m', '',
            'Механическая помпа с ручным приводом: не требует электричества и батареек, надёжна при частом использовании.'],
        ['pumpA22', 'Помпа HotFrost А22', 'pumps', '', 'Аксессуары · электрическая', 55.00, null, 'i-pump', '',
            'Электрическая помпа повышенной производительности для бутылей 18,9 л. Удобна для офиса, где воду набирают часто.'],
        ['dispL1', 'Раздатчик воды L1-WD', 'coolers', '', 'Для дома и офиса', 40.00, null, 'i-dispenser', '',
            'Настольный раздатчик без охлаждения: компактно ставится на стол, помогает аккуратно наливать воду из бутыли.'],
        ['stand', 'Подставка под бутыль', 'access', '', 'Аксессуары · металл', 13.20, null, 'i-stand', '',
            'Металлическая подставка под бутыль 18,9 л. Устойчивая конструкция, защищает пол и облегчает установку бутыли.'],
        ['holder', 'Держатель для стаканов', 'access', '', 'Аксессуары', 23.00, null, 'i-holder', '',
            'Держатель для одноразовых стаканов: крепится на кулер или рядом с ним, всегда под рукой.'],
        ['cup200', 'Стакан 0,18 л (200 шт)', 'dishes', '0,18 л', 'Одноразовая посуда', 19.00, null, 'i-cup', '',
            'Прозрачные одноразовые стаканы объёмом 0,18 л, упаковка 200 штук. Плотный пластик, не деформируются от горячей воды.'],
        ['cup50', 'Стакан «Протера» (50 шт)', 'dishes', '0,18 л', 'Одноразовая посуда', 5.50, null, 'i-cup', '',
            'Одноразовые стаканы с фирменной символикой, упаковка 50 штук. Удобно для офиса и мероприятий.'],
    ];
    foreach ($products as $i => $p) {
        db_exec(
            'INSERT INTO products (slug, name, category, volume, short_desc, description, price, old_price, icon, image, badge, is_active, sort, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$p[0], $p[1], $p[2], $p[3], $p[4], $p[9], $p[5], $p[6], $p[7], '', $p[8], ($i + 1) * 10, $now, $now]
        );
    }
    $done[] = 'Добавлены 12 товаров каталога.';
}

/* ---------- зоны доставки ---------- */
if ((int)db_val('SELECT COUNT(*) FROM zones') === 0) {
    $zones = [
        ['Пуховичский район', ['Марьина Горка', 'Дружный', 'Свислочь', 'Руденск'],
            ['09:00–12:00', '12:00–15:00', '15:00–18:00', '18:00–21:00'], 'Доставка на следующий день', 10],
        ['Червенский район', ['Червень'], ['10:00–13:00', '13:00–16:00', '16:00–19:00'], 'Доставка на следующий день', 20],
        ['Осиповичи', ['Осиповичи'], ['10:00–14:00', '14:00–18:00'], 'Доставка по графику района', 30],
    ];
    foreach ($zones as [$name, $cities, $intervals, $note, $sort]) {
        db_exec(
            'INSERT INTO zones (name, cities, intervals, note, sort, is_active) VALUES (?, ?, ?, ?, ?, 1)',
            [$name, json_encode($cities, JSON_UNESCAPED_UNICODE), json_encode($intervals, JSON_UNESCAPED_UNICODE), $note, $sort]
        );
    }
    $done[] = 'Добавлены зоны доставки и интервалы.';
}

/* ---------- способы оплаты ---------- */
if ((int)db_val('SELECT COUNT(*) FROM payments') === 0) {
    $payments = [
        ['cash', 'Наличными курьеру', 'Оплата при получении', 10],
        ['card', 'Картой курьеру', 'Терминал у курьера', 20],
        ['transfer', 'Перевод / ЕРИП', 'Реквизиты пришлём после подтверждения', 30],
    ];
    foreach ($payments as [$code, $title, $note, $sort]) {
        db_exec('INSERT INTO payments (code, title, note, sort, is_active) VALUES (?, ?, ?, ?, 1)', [$code, $title, $note, $sort]);
    }
    $done[] = 'Добавлены способы оплаты.';
}

/* ---------- настройки сайта ---------- */
$defaults = [
    'site_phone' => '+7 (911) 011-70-20',
    'site_phone_href' => '+79110117020',
    'site_email' => 'info@aqua-delivery.ru',
    'site_area' => 'Минская и Могилёвская области',
    'site_hours' => 'с 8:00 до 20:00',
    'hero_title' => 'Вода, которую удобно заказывать',
    'hero_sub' => 'Свежая питьевая вода для дома и офиса. Доставим в удобное время.',
    'about_title' => 'О компании PROTERA',
    'about_p1' => '«Живая вода ПРОТЕРА» — природная питьевая вода из уникального подземного источника ледникового происхождения. Без водопровода и фильтров, изменяющих состав: более 50 жизненно необходимых макро- и микроэлементов, оптимальное содержание фтора.',
    'about_p2' => 'Производитель первым в мире внедрил запатентованную ультразвуковую мойку оборотных бутылей — самые чистые бутыли для вашей воды. Вся продукция сертифицирована по международным стандартам.',
    'delivery_note' => 'Минская область (Червенский, Пуховичский районы) и Могилёвская область (Осиповичи)',
    'seo_title' => 'PROTERA — доставка питьевой воды 18,9 л в Минской и Могилёвской областях',
    'seo_description' => 'Доставка питьевой воды 18,9 л в Минской и Могилёвской областях: Марьина Горка, Дружный, Свислочь, Руденск, Червень, Осиповичи. Заказ на сайте или по телефону.',
    'footer_text' => '© PROTERA. Все права защищены.',
];
$added = 0;
foreach ($defaults as $k => $v) {
    if (db_one('SELECT skey FROM settings WHERE skey = ?', [$k]) === null) {
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$k, $v]);
        $added++;
    }
}
if ($added > 0) {
    $done[] = 'Добавлены настройки сайта (' . $added . ').';
}

/* ---------- администратор ---------- */
$adminPass = '';
if ((int)db_val('SELECT COUNT(*) FROM admins') === 0) {
    $adminPass = (string)($CONFIG['admin_password'] ?? 'admin12345');
    db_exec(
        'INSERT INTO admins (login, name, pass_hash, created_at) VALUES (?, ?, ?, ?)',
        ['admin', 'Администратор', password_hash($adminPass, PASSWORD_DEFAULT), $now]
    );
    $done[] = 'Создан администратор: логин <b>admin</b>, пароль <b>' . htmlspecialchars($adminPass, ENT_QUOTES, 'UTF-8') . '</b> (смените его в админке).';
}

$done[] = 'Драйвер БД: ' . $driver . '. Пользователей: ' . (int)db_val('SELECT COUNT(*) FROM users') . ', товаров: ' . (int)db_val('SELECT COUNT(*) FROM products');

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="ru"><meta charset="utf-8"><title>Установка PROTERA</title>'
    . '<body style="font-family:Arial,sans-serif;background:#eaf2fb;padding:40px;color:#0d2b4e;">'
    . '<div style="max-width:680px;margin:0 auto;background:#fff;border-radius:16px;padding:28px;">'
    . '<h1 style="font-size:20px;">Установка завершена</h1><ul style="line-height:1.8;font-size:14px;">';
foreach ($done as $row) {
    echo '<li>' . $row . '</li>';
}
echo '</ul><p style="font-size:14px;color:#c93b3b;"><b>Удалите файл api/install.php</b> и смените install_key в config.php.</p>'
    . '<p style="font-size:13px;"><a href="../admin/">Перейти в админ-панель</a> · <a href="../">На сайт</a></p></div></body></html>';
