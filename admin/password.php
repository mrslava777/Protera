<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

$admin = admin_require();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $current = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['password'] ?? '');
    $repeat = (string)($_POST['password2'] ?? '');

    if (!password_verify($current, (string)$admin['pass_hash'])) {
        $error = 'Текущий пароль неверный';
    } elseif (($problem = password_problem($new)) !== null) {
        $error = $problem;
    } elseif ($new !== $repeat) {
        $error = 'Пароли не совпадают';
    } elseif (hash_equals($current, $new)) {
        $error = 'Новый пароль должен отличаться от текущего';
    } else {
        db_exec('UPDATE admins SET pass_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$admin['id']]);
        alog('password', 'admin', $admin['id'], 'Смена пароля администратора');
        flash('Пароль изменён');
        redirect('password.php');
    }
}

admin_head('Пароль');
?>
<div class="toolbar">
  <div><h1>Пароль администратора</h1><p class="sub">Логин: <b><?= e($admin['login']) ?></b></p></div>
</div>

<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:520px">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
    <div class="field" style="margin-bottom:12px">
      <label for="current">Текущий пароль</label>
      <input id="current" type="password" name="current" autocomplete="current-password" required>
    </div>
    <div class="field" style="margin-bottom:12px">
      <label for="password">Новый пароль</label>
      <input id="password" type="password" name="password" autocomplete="new-password" required>
      <span class="hint">Минимум 8 символов, хотя бы одна буква и цифра</span>
    </div>
    <div class="field" style="margin-bottom:16px">
      <label for="password2">Повторите новый пароль</label>
      <input id="password2" type="password" name="password2" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary" type="submit">Сменить пароль</button>
  </form>
</div>
<?php admin_foot(); ?>
