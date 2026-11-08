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
        $zone = db_one('SELECT * FROM zones WHERE id = ?', [$id]);
        if ($zone) {
            db_exec('DELETE FROM zones WHERE id = ?', [$id]);
            alog('delete', 'zone', $id, 'Удалена зона: ' . $zone['name']);
            flash('Зона удалена');
        }
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $sort = (int)($_POST['sort'] ?? 100);
        $active = isset($_POST['is_active']) ? 1 : 0;

        $cities = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['cities'] ?? '')))));
        $intervals = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($_POST['intervals'] ?? '')))));
        if ($name === '' || !$cities || !$intervals) {
            flash('Заполните название, хотя бы один город и один интервал', 'err');
        } else {
            $citiesJson = json_encode($cities, JSON_UNESCAPED_UNICODE);
            $intervalsJson = json_encode($intervals, JSON_UNESCAPED_UNICODE);
            if ($id) {
                db_exec('UPDATE zones SET name=?, cities=?, intervals=?, note=?, sort=?, is_active=? WHERE id=?',
                    [$name, $citiesJson, $intervalsJson, $note, $sort, $active, $id]);
                alog('update', 'zone', $id, 'Изменена зона: ' . $name);
                flash('Зона сохранена');
            } else {
                db_exec('INSERT INTO zones (name, cities, intervals, note, sort, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                    [$name, $citiesJson, $intervalsJson, $note, $sort, $active]);
                alog('create', 'zone', db_id(), 'Добавлена зона: ' . $name);
                flash('Зона добавлена');
            }
        }
    }
    redirect('delivery.php');
}

$editId = (int)($_GET['id'] ?? 0);
$edit = $editId ? db_one('SELECT * FROM zones WHERE id = ?', [$editId]) : null;
$zones = db_all('SELECT * FROM zones ORDER BY sort, id');

admin_head('Доставка');
?>
<div class="toolbar">
  <div><h1>Зоны доставки и интервалы</h1><p class="sub">Интервалы показываются клиенту при оформлении заказа — в зависимости от города</p></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Зоны</h2>
    <?php if (!$zones): ?>
      <div class="empty">Зон пока нет.</div>
    <?php endif; ?>
    <?php foreach ($zones as $z): ?>
      <?php $cities = json_decode((string)$z['cities'], true) ?: []; $intervals = json_decode((string)$z['intervals'], true) ?: []; ?>
      <div style="border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <div>
            <b><?= e($z['name']) ?></b>
            <?= (int)$z['is_active'] ? '<span class="badge ok">Вкл</span>' : '<span class="badge gray">Выкл</span>' ?>
          </div>
          <div class="row-actions">
            <a class="btn btn-sm" href="delivery.php?id=<?= (int)$z['id'] ?>">Изменить</a>
            <form method="post" onsubmit="return confirm('Удалить зону «<?= e($z['name']) ?>»?')">
              <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Удалить</button>
            </form>
          </div>
        </div>
        <div class="small muted" style="margin-top:6px">Города: <?= e(implode(', ', $cities)) ?></div>
        <div class="small muted">Интервалы: <?= e(implode(' · ', $intervals)) ?></div>
        <?php if ($z['note']): ?><div class="small muted"><?= e($z['note']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2><?= $edit ? 'Изменить зону' : 'Новая зона' ?></h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field" style="margin-bottom:10px">
        <label>Название зоны</label>
        <input name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="Пуховичский район" required>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Города (через запятую)</label>
        <input name="cities" value="<?= e($edit ? implode(', ', json_decode((string)$edit['cities'], true) ?: []) : '') ?>" placeholder="Марьина Горка, Дружный, Свислочь" required>
        <span class="hint">Названия должны совпадать с городами в адресах клиентов</span>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Интервалы доставки (по одному в строке)</label>
        <textarea name="intervals" rows="5" required><?= e($edit ? implode("\n", json_decode((string)$edit['intervals'], true) ?: []) : '') ?></textarea>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Примечание</label>
        <input name="note" value="<?= e($edit['note'] ?? '') ?>" placeholder="Доставка на следующий день">
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Порядок</label>
        <input name="sort" value="<?= (int)($edit['sort'] ?? 100) ?>" inputmode="numeric">
      </div>
      <label class="check" style="margin-bottom:14px"><input type="checkbox" name="is_active" value="1" <?= (int)($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Зона активна</label>
      <div><button class="btn btn-primary" type="submit"><?= $edit ? 'Сохранить' : 'Добавить зону' ?></button>
      <?php if ($edit): ?><a class="btn" href="delivery.php">Отмена</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php admin_foot(); ?>
