<?php
declare(strict_types=1);

/** Общая обёртка админ-панели. */

function admin_menu(): array
{
    return [
        'index.php'       => 'Обзор',
        'orders.php'      => 'Заказы',
        'products.php'    => 'Товары',
        'categories.php'  => 'Категории',
        'delivery.php'    => 'Доставка',
        'payments.php'    => 'Оплата',
        'users.php'       => 'Клиенты',
        'settings.php'    => 'Настройки сайта',
        'log.php'         => 'Журнал действий',
        'password.php'    => 'Пароль',
    ];
}

function admin_head(string $title): void
{
    $admin = admin_user();
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $flash = take_flash();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — PROTERA админ</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-in">
    <a class="brand" href="index.php" style="text-decoration:none">
      <span class="dot"></span>
      <span>PROTERA<small>админ-панель</small></span>
    </a>
    <span class="spacer"></span>
    <a class="btn btn-sm" href="../" target="_blank" rel="noopener">Открыть сайт</a>
    <?php if ($admin): ?>
      <span class="who">Вы вошли как <b><?= e($admin['login']) ?></b></span>
      <a class="btn btn-sm" href="logout.php">Выйти</a>
    <?php endif; ?>
  </div>
</header>
<?php if ($admin): ?>
<nav class="nav">
  <div class="nav-in">
    <?php foreach (admin_menu() as $file => $label): ?>
      <a href="<?= e($file) ?>" class="<?= $file === $current ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
<main class="wrap">
<?php if ($flash): ?>
  <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
    <?php
}

function admin_foot(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

function status_class(string $status): string
{
    switch ($status) {
        case 'Новый':       return 'warn';
        case 'Подтверждён': return '';
        case 'Доставлен':   return 'ok';
        case 'Отменён':     return 'err';
        default:            return 'gray';
    }
}

function product_thumb(array $product): string
{
    if (!empty($product['image'])) {
        return '<span class="thumb"><img src="../' . e($product['image']) . '" alt=""></span>';
    }
    return '<span class="thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg></span>';
}
