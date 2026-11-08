<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/** Переход по ссылке из письма. Всегда завершается редиректом на сайт. */

$token = (string)($_GET['token'] ?? '');

$redirect = static function (string $query): void {
    header('Location: ' . site_url('/' . $query), true, 302);
    exit;
};

try {
    $row = find_token($token, 'verify');
    if ($row === null) {
        $redirect('?verify=invalid');
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [(int)$row['user_id']]);
    if ($user === null) {
        $redirect('?verify=invalid');
    }

    use_token((int)$row['id']);

    $now = now();
    db_exec(
        'UPDATE users SET email_verified = 1, verified_at = ?, updated_at = ? WHERE id = ?',
        [$now, $now, (int)$user['id']]
    );
    db_exec('DELETE FROM tokens WHERE user_id = ? AND type = ?', [(int)$user['id'], 'verify']);

    // Владелец почты подтверждён — сразу авторизуем в этом браузере.
    session_regenerate_id(true);
    $_SESSION['uid']  = (int)$user['id'];
    $_SESSION['at']   = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(16));

    add_event('email_verified', (string)$user['email']);
    $redirect('?verified=1');
} catch (Throwable $e) {
    error_log('[protera] verify: ' . $e->getMessage());
    $redirect('?verify=invalid');
}
