<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;

$where = '';
$params = [];
if ($q !== '') {
    $where = ' WHERE (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$total = (int)db_val('SELECT COUNT(*) FROM users u' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = db_all(
    'SELECT u.*,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
        (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status <> \'Отменён\') AS orders_sum,
        (SELECT COUNT(*) FROM addresses a WHERE a.user_id = u.id) AS addresses_count
     FROM users u' . $where . ' ORDER BY u.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

admin_head('Клиенты');
?>
<div class="toolbar">
  <div><h1>Клиенты</h1><p class="sub">Всего: <?= $total ?></p></div>
  <form class="filters" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="Имя, e-mail или телефон"
           style="padding:8px 10px;border:1px solid var(--border);border-radius:9px;min-width:240px">
    <button class="btn" type="submit">Найти</button>
    <?php if ($q): ?><a class="btn" href="users.php">Сбросить</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty">Клиентов не найдено.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="t">
        <tr><th>Клиент</th><th>E-mail</th><th>Заказов</th><th>Сумма</th><th>Адреса</th><th>Регистрация</th></tr>
        <?php foreach ($rows as $u): ?>
          <tr>
            <td><b><?= e($u['name']) ?></b><div class="small muted"><?= e($u['phone']) ?></div></td>
            <td class="small"><?= e($u['email']) ?>
              <?= (int)$u['email_verified'] ? '<span class="badge ok">подтверждён</span>' : '<span class="badge warn">не подтверждён</span>' ?>
            </td>
            <td>
              <?php if ((int)$u['orders_count']): ?>
                <a href="orders.php?q=<?= e($u['phone']) ?>"><?= (int)$u['orders_count'] ?></a>
              <?php else: ?>0<?php endif; ?>
            </td>
            <td class="num"><?= number_format((float)$u['orders_sum'], 2, ',', ' ') ?> руб.</td>
            <td><?= (int)$u['addresses_count'] ?></td>
            <td class="small"><?= e($u['created_at']) ?><div class="muted">вход: <?= e($u['last_login_at'] ?: '—') ?></div></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="toolbar" style="margin:14px 0 0">
        <div class="small muted">Страница <?= $page ?> из <?= $pages ?></div>
        <div class="filters">
          <?php if ($page > 1): ?>
            <a class="btn btn-sm" href="?<?= e(http_build_query(array_filter(['q' => $q, 'page' => $page - 1]))) ?>">← Назад</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="btn btn-sm" href="?<?= e(http_build_query(array_filter(['q' => $q, 'page' => $page + 1]))) ?>">Вперёд →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_foot(); ?>
