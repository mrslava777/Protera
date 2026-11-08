<?php
/**
 * Настройки сайта PROTERA.
 * Этот файл не отдаётся напрямую (защищён api/.htaccess) и не должен попадать в git.
 */
if (!defined('PROTERA')) {
    http_response_code(403);
    exit;
}

return [
    // Адрес сайта без слэша на конце. Нужен для ссылок в письмах.
    'site_url' => 'https://example.by',

    'timezone' => 'Europe/Minsk',

    'db' => [
        // 'mysql' для hb.by, 'sqlite' — для локальной проверки
        'driver' => 'mysql',
        'host'   => 'localhost',
        'port'   => 3306,
        'name'   => 'protera',
        'user'   => 'protera',
        'pass'   => '',
        // только для sqlite:
        'path'   => __DIR__ . '/../storage/protera.sqlite',
    ],

    'mail' => [
        // mail — встроенная функция PHP, smtp — ящик на хостинге, log — запись в файл (для тестов)
        'driver'    => 'mail',
        'from'      => 'no-reply@example.by',
        'from_name' => 'PROTERA',
        'reply_to'  => 'info@example.by',
        'smtp' => [
            'host'   => 'mail.example.by',
            'port'   => 465,
            'secure' => 'ssl',   // ssl | tls | '' (без шифрования)
            'user'   => 'no-reply@example.by',
            'pass'   => '',
        ],
    ],

    // Загрузка фото товаров: лимит файла и авто-сжатие
    'uploads' => [
        'max_mb'     => 20,    // максимальный размер загружаемого файла
        'max_width'  => 1600,  // до какой ширины уменьшать
        'max_height' => 1600,  // до какой высоты уменьшать
        'quality'    => 82,    // качество JPEG/WEBP после сжатия (55–95)
    ],

    'security' => [
        'verify_ttl'    => 86400,   // ссылка подтверждения — 24 часа
        'reset_ttl'     => 3600,    // ссылка сброса пароля — 1 час
        'remember_days' => 30,      // «Запомнить меня»
    ],

    // Ключ для запуска install.php. После установки удалите install.php и смените ключ.
    'install_key' => 'change-me-install-key',

    // Пароль администратора, который создаётся при установке (логин admin).
    // Сразу после входа смените его в разделе «Пароль».
    'admin_password' => 'admin12345',
];
