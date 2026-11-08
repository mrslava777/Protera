<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';

if (admin_user() !== null) {
    redirect('index.php');
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $login = trim((string)($_POST['login'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if (too_many('admin_fail', mb_strtolower($login), 6, 900) || too_many('admin_fail_ip', client_ip(), 20, 900)) {
        $error = 'Слишком много попыток. Повторите через 15 минут.';
    } elseif ($login === '' || $pass === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $admin = db_one('SELECT * FROM admins WHERE login = ?', [$login]);
        if ($admin === null || !password_verify($pass, (string)$admin['pass_hash'])) {
            add_event('admin_fail', mb_strtolower($login));
            add_event('admin_fail_ip', client_ip());
            $error = 'Неверный логин или пароль.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
            db_exec('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), (int)$admin['id']]);
            db_exec(
                'INSERT INTO admin_log (admin_id, admin_login, action, entity, entity_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int)$admin['id'], $admin['login'], 'login', 'admin', (string)$admin['id'], 'Вход в админ-панель', client_ip(), now()]
            );
            redirect('index.php');
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вход в админ-панель — PROTERA</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="login-wrap">
  <form class="login-card" method="post" autocomplete="on">
    <div class="login-brand"><span class="dot"></span><b>PROTERA</b></div>
    <h1>Админ-панель</h1>
    <p class="sub">Вход по логину и паролю администратора</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
    <div class="field" style="margin-bottom:12px">
      <label for="login">Логин</label>
      <input id="login" name="login" value="<?= e($_POST['login'] ?? '') ?>" autocomplete="username" required autofocus>
    </div>
    <div class="field" style="margin-bottom:16px">
      <label for="password">Пароль</label>
      <input id="password" type="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary" type="submit">Войти</button>
    <p class="small muted" style="margin:14px 0 0">Доступ только для сотрудников. Все действия фиксируются в журнале.</p>
  </form>
</div>
</body>
</html>
