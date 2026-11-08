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
        $cat = db_one('SELECT * FROM categories WHERE id = ?', [$id]);
        if ($cat) {
            $used = (int)db_val('SELECT COUNT(*) FROM products WHERE category = ?', [$cat['skey']]);
            if ($used > 0) {
                flash('Нельзя удалить категорию: в ней ' . $used . ' товаров', 'err');
            } else {
                db_exec('DELETE FROM categories WHERE id = ?', [$id]);
                alog('delete', 'category', $id, 'Удалена категория: ' . $cat['name']);
                flash('Категория удалена');
            }
        }
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $skey = trim((string)($_POST['skey'] ?? ''));
        $sort = (int)($_POST['sort'] ?? 100);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $skey = preg_replace('/[^a-z0-9_-]/', '', mb_strtolower($skey)) ?: '';

        if ($name === '' || $skey === '') {
            flash('Заполните название и код категории (латиницей)', 'err');
        } else {
            $exists = db_one('SELECT id FROM categories WHERE skey = ? AND id <> ?', [$skey, $id]);
            if ($exists) {
                flash('Категория с таким кодом уже есть', 'err');
            } elseif ($id) {
                db_exec('UPDATE categories SET skey=?, name=?, sort=?, is_active=? WHERE id=?', [$skey, $name, $sort, $active, $id]);
                alog('update', 'category', $id, 'Изменена категория: ' . $name);
                flash('Категория сохранена');
            } else {
                db_exec('INSERT INTO categories (skey, name, sort, is_active) VALUES (?, ?, ?, ?)', [$skey, $name, $sort, $active]);
                alog('create', 'category', db_id(), 'Добавлена категория: ' . $name);
                flash('Категория добавлена');
            }
        }
    }
    redirect('categories.php');
}

$editId = (int)($_GET['id'] ?? 0);
$edit = $editId ? db_one('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
$rows = db_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category = c.skey) AS cnt FROM categories c ORDER BY c.sort, c.id');

admin_head('Категории');
?>
<div class="toolbar">
  <div><h1>Категории каталога</h1><p class="sub">Названия фильтров на сайте и группировка товаров</p></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Список</h2>
    <div class="table-wrap">
      <table class="t">
        <tr><th>Название</th><th>Код</th><th>Товаров</th><th>Статус</th><th></th></tr>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td><b><?= e($c['name']) ?></b></td>
            <td class="mono"><?= e($c['skey']) ?></td>
            <td><?= (int)$c['cnt'] ?></td>
            <td><?= (int)$c['is_active'] ? '<span class="badge ok">Вкл</span>' : '<span class="badge gray">Выкл</span>' ?></td>
            <td class="right">
              <div class="row-actions" style="justify-content:flex-end">
                <a class="btn btn-sm" href="categories.php?id=<?= (int)$c['id'] ?>">Изменить</a>
                <form method="post" onsubmit="return confirm('Удалить категорию «<?= e($c['name']) ?>»?')">
                  <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
    <h2><?= $edit ? 'Изменить категорию' : 'Новая категория' ?></h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field" style="margin-bottom:10px">
        <label>Название</label>
        <input name="name" value="<?= e($edit['name'] ?? '') ?>" required>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Код (латиница)</label>
        <input name="skey" value="<?= e($edit['skey'] ?? '') ?>" placeholder="water" required>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Порядок</label>
        <input name="sort" value="<?= (int)($edit['sort'] ?? 100) ?>" inputmode="numeric">
      </div>
      <label class="check" style="margin-bottom:14px"><input type="checkbox" name="is_active" value="1" <?= (int)($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Показывать в фильтре</label>
      <div><button class="btn btn-primary" type="submit"><?= $edit ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($edit): ?><a class="btn" href="categories.php">Отмена</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php admin_foot(); ?>
