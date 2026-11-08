<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';

if (admin_user() !== null) {
    alog('logout', 'admin', admin_user()['id'], 'Выход из админ-панели');
}

unset($_SESSION['admin_id'], $_SESSION['admin_csrf']);
session_regenerate_id(true);

redirect('login.php');
