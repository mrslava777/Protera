<?php
declare(strict_types=1);

define('PROTERA', true);

$CONFIG = require __DIR__ . '/config.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set((string)($CONFIG['timezone'] ?? 'Europe/Minsk'));

require __DIR__ . '/lib.php';
require __DIR__ . '/db.php';
require __DIR__ . '/mailer.php';

set_exception_handler(static function (Throwable $e): void {
    error_log('[protera] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        ['ok' => false, 'code' => 'server', 'message' => 'Внутренняя ошибка сервера. Попробуйте позже.'],
        JSON_UNESCAPED_UNICODE
    );
});

start_session();
