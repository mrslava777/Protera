<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

$admin = admin_require();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $product = db_one('SELECT * FROM products WHERE id = ?', [$id]);
    $action = (string)($_POST['action'] ?? '');

    if (!$product) {
        flash('Товар не найден', 'err');
    } elseif ($action === 'delete') {
        delete_upload($product['image']);
        db_exec('DELETE FROM products WHERE id = ?', [$id]);
        alog('delete', 'product', $id, 'Удалён товар: ' . $product['name']);
        flash('Товар удалён');
    } elseif ($action === 'toggle') {
        db_exec('UPDATE products SET is_active = ?, updated_at = ? WHERE id = ?', [(int)!$product['is_active'], now(), $id]);
        alog('update', 'product', $id, ((int)$product['is_active'] === 1 ? 'Скрыт' : 'Опубликован') . ' товар: ' . $product['name']);
        flash('Статус товара изменён');
    }
    redirect('products.php');
}

$rows = db_all('SELECT * FROM products ORDER BY sort, id');
$cats = [];
foreach (db_all('SELECT skey, name FROM categories') as $c) {
    $cats[$c['skey']] = $c['name'];
}

admin_head('Товары');
?>
<div class="toolbar">
  <div>
    <h1>Товары</h1>
    <p class="sub">Карточки каталога: фото, описание, цена и видимость на сайте</p>
  </div>
  <a class="btn btn-primary" href="product-edit.php">+ Добавить товар</a>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty">Товаров пока нет. Нажмите «Добавить товар».</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="t">
        <tr>
          <th>Товар</th><th>Категория</th><th>Цена</th><th>Статус</th><th class="right">Действия</th>
        </tr>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td>
              <div class="prod-cell">
                <?= product_thumb($p) ?>
                <span>
                  <b><a href="product-edit.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></b>
                  <span><?= e($p['short_desc']) ?><?= $p['volume'] ? ' · ' . e($p['volume']) : '' ?></span>
                </span>
              </div>
            </td>
            <td><?= e($cats[$p['category']] ?? $p['category']) ?></td>
            <td class="num"><?= number_format((float)$p['price'], 2, ',', ' ') ?> руб.</td>
            <td>
              <?php if ((int)$p['is_active']): ?><span class="badge ok">На сайте</span>
              <?php else: ?><span class="badge gray">Скрыт</span><?php endif; ?>
            </td>
            <td class="right">
              <div class="row-actions" style="justify-content:flex-end">
                <a class="btn btn-sm" href="product-edit.php?id=<?= (int)$p['id'] ?>">Изменить</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="action" value="toggle">
                  <button class="btn btn-sm" type="submit"><?= (int)$p['is_active'] ? 'Скрыть' : 'Опубликовать' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Удалить товар «<?= e($p['name']) ?>» безвозвратно?')">
                  <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn btn-sm btn-danger" type="submit">Удалить</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php admin_foot(); ?>
