<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $p = db_one('SELECT * FROM payments WHERE id = ?', [$id]);
        if ($p) {
            db_exec('DELETE FROM payments WHERE id = ?', [$id]);
            alog('delete', 'payment', $id, 'Удалён способ оплаты: ' . $p['title']);
            flash('Способ оплаты удалён');
        }
    } else {
        $code = preg_replace('/[^a-z0-9_-]/', '', mb_strtolower(trim((string)($_POST['code'] ?? '')))) ?: '';
        $title = trim((string)($_POST['title'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $sort = (int)($_POST['sort'] ?? 100);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '' || $title === '') {
            flash('Укажите код (латиницей) и название', 'err');
        } elseif (db_one('SELECT id FROM payments WHERE code = ? AND id <> ?', [$code, $id])) {
            flash('Способ с таким кодом уже существует', 'err');
        } elseif ($id) {
            db_exec('UPDATE payments SET code=?, title=?, note=?, sort=?, is_active=? WHERE id=?', [$code, $title, $note, $sort, $active, $id]);
            alog('update', 'payment', $id, 'Изменён способ оплаты: ' . $title);
            flash('Способ оплаты сохранён');
        } else {
            db_exec('INSERT INTO payments (code, title, note, sort, is_active) VALUES (?, ?, ?, ?, ?)', [$code, $title, $note, $sort, $active]);
            alog('create', 'payment', db_id(), 'Добавлен способ оплаты: ' . $title);
            flash('Способ оплаты добавлен');
        }
    }
    redirect('payments.php');
}

$editId = (int)($_GET['id'] ?? 0);
$edit = $editId ? db_one('SELECT * FROM payments WHERE id = ?', [$editId]) : null;
$rows = db_all('SELECT * FROM payments ORDER BY sort, id');

admin_head('Оплата');
?>
<div class="toolbar">
  <div><h1>Способы оплаты</h1><p class="sub">Варианты, которые клиент видит при оформлении заказа</p></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Список</h2>
    <div class="table-wrap">
      <table class="t">
        <tr><th>Название</th><th>Код</th><th>Статус</th><th></th></tr>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td><b><?= e($p['title']) ?></b><div class="small muted"><?= e($p['note']) ?></div></td>
            <td class="mono"><?= e($p['code']) ?></td>
            <td><?= (int)$p['is_active'] ? '<span class="badge ok">Вкл</span>' : '<span class="badge gray">Выкл</span>' ?></td>
            <td class="right">
              <div class="row-actions" style="justify-content:flex-end">
                <a class="btn btn-sm" href="payments.php?id=<?= (int)$p['id'] ?>">Изменить</a>
                <form method="post" onsubmit="return confirm('Удалить способ оплаты?')">
                  <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Удалить</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="card">
    <h2><?= $edit ? 'Изменить способ' : 'Новый способ оплаты' ?></h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field" style="margin-bottom:10px">
        <label>Название</label>
        <input name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="Наличными курьеру" required>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Код (латиница)</label>
        <input name="code" value="<?= e($edit['code'] ?? '') ?>" placeholder="cash" required>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Пояснение</label>
        <input name="note" value="<?= e($edit['note'] ?? '') ?>" placeholder="Оплата при получении">
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Порядок</label>
        <input name="sort" value="<?= (int)($edit['sort'] ?? 100) ?>" inputmode="numeric">
      </div>
      <label class="check" style="margin-bottom:14px"><input type="checkbox" name="is_active" value="1" <?= (int)($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Доступен клиентам</label>
      <div><button class="btn btn-primary" type="submit"><?= $edit ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($edit): ?><a class="btn" href="payments.php">Отмена</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php admin_foot(); ?>
