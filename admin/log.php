<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$action = trim((string)($_GET['action'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 100;

$where = '';
$params = [];
if ($action !== '') {
    $where = ' WHERE action = ?';
    $params = [$action];
}
$total = (int)db_val('SELECT COUNT(*) FROM admin_log' . $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = db_all('SELECT * FROM admin_log' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
$actions = array_column(db_all('SELECT DISTINCT action FROM admin_log ORDER BY action'), 'action');

function action_label(string $action): string
{
    $map = [
        'login' => 'Вход', 'logout' => 'Выход', 'create' => 'Создание', 'update' => 'Изменение',
        'delete' => 'Удаление', 'password' => 'Смена пароля',
    ];
    return $map[$action] ?? $action;
}

admin_head('Журнал действий');
?>
<div class="toolbar">
  <div><h1>Журнал действий</h1><p class="sub">Все изменения в админ-панели: кто, что и когда сделал</p></div>
  <form class="filters" method="get">
    <select name="action" style="padding:8px 10px;border:1px solid var(--border);border-radius:9px;background:#fff">
      <option value="">Все действия</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(action_label((string)$a)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Фильтр</button>
    <?php if ($action): ?><a class="btn" href="log.php">Сбросить</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty">Записей пока нет.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="t">
        <tr><th>Дата</th><th>Администратор</th><th>Действие</th><th>Объект</th><th>Подробности</th><th>IP</th></tr>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="num small"><?= e($row['created_at']) ?></td>
            <td><?= e($row['admin_login']) ?></td>
            <td><span class="badge <?= $row['action'] === 'delete' ? 'err' : ($row['action'] === 'create' ? 'ok' : 'gray') ?>"><?= e(action_label((string)$row['action'])) ?></span></td>
            <td class="small"><?= e($row['entity']) ?><?= $row['entity_id'] ? ' #' . e($row['entity_id']) : '' ?></td>
            <td class="small"><?= e($row['details']) ?></td>
            <td class="mono small"><?= e($row['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="toolbar" style="margin:14px 0 0">
        <div class="small muted">Страница <?= $page ?> из <?= $pages ?></div>
        <div class="filters">
          <?php if ($page > 1): ?>
            <a class="btn btn-sm" href="?<?= e(http_build_query(array_filter(['action' => $action, 'page' => $page - 1]))) ?>">← Назад</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="btn btn-sm" href="?<?= e(http_build_query(array_filter(['action' => $action, 'page' => $page + 1]))) ?>">Вперёд →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_foot(); ?>
