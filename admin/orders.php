<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$statuses = ['Новый', 'Подтверждён', 'Доставлен', 'Отменён'];
$status = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = [];
$params = [];
if (in_array($status, $statuses, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(o.number LIKE ? OR u.phone LIKE ? OR u.name LIKE ? OR o.address_text LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int)db_val('SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id' . $whereSql, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = db_all(
    'SELECT o.*, u.name AS user_name, u.phone AS user_phone FROM orders o JOIN users u ON u.id = o.user_id'
    . $whereSql . ' ORDER BY o.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

admin_head('Заказы');
?>
<div class="toolbar">
  <div><h1>Заказы</h1><p class="sub">Всего: <?= $total ?></p></div>
  <form class="filters" method="get">
    <select name="status" style="padding:8px 10px;border:1px solid var(--border);border-radius:9px;background:#fff">
      <option value="">Все статусы</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input name="q" value="<?= e($q) ?>" placeholder="Номер, имя, телефон, адрес"
           style="padding:8px 10px;border:1px solid var(--border);border-radius:9px;min-width:220px">
    <button class="btn" type="submit">Найти</button>
    <?php if ($status || $q): ?><a class="btn" href="orders.php">Сбросить</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty">Заказов не найдено.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="t">
        <tr><th>№</th><th>Дата</th><th>Клиент</th><th>Доставка</th><th>Оплата</th><th>Сумма</th><th>Статус</th></tr>
        <?php foreach ($rows as $o): ?>
          <tr>
            <td class="num"><a href="order.php?id=<?= (int)$o['id'] ?>"><b><?= e($o['number']) ?></b></a></td>
            <td class="num small"><?= e($o['created_at']) ?></td>
            <td><?= e($o['user_name']) ?><div class="small muted"><?= e($o['user_phone']) ?></div></td>
            <td class="small"><?= e($o['city']) ?>, <?= e($o['address_text']) ?><div class="muted"><?= e($o['interval']) ?></div></td>
            <td class="small"><?= e($o['payment_title']) ?></td>
            <td class="num"><?= number_format((float)$o['total'], 2, ',', ' ') ?> руб.</td>
            <td><span class="badge <?= status_class((string)$o['status']) ?>"><?= e($o['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="toolbar" style="margin:14px 0 0">
        <div class="small muted">Страница <?= $page ?> из <?= $pages ?></div>
        <div class="filters">
          <?php if ($page > 1): ?>
            <a class="btn btn-sm" href="?<?= e(http_build_query(array_filter(['status' => $status, 'q' => $q, 'page' => $page - 1]))) ?>">← Назад</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="btn btn-sm" href="?<?= e(http_build_query(array_filter(['status' => $status, 'q' => $q, 'page' => $page + 1]))) ?>">Вперёд →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_foot(); ?>
