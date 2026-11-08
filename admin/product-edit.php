<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$id = (int)($_GET['id'] ?? 0);
$product = $id ? db_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) {
    flash('Товар не найден', 'err');
    redirect('products.php');
}

$cats = db_all('SELECT skey, name FROM categories ORDER BY sort, id');
$icons = ['i-bottle' => 'Бутыль', 'i-bottle-light' => 'Бутыль Лайт', 'i-pump' => 'Помпа', 'i-pump-m' => 'Помпа механическая',
    'i-cooler-t' => 'Кулер', 'i-dispenser' => 'Раздатчик', 'i-tap' => 'Кран', 'i-stand' => 'Подставка',
    'i-holder' => 'Держатель', 'i-cup' => 'Стакан', 'i-drop' => 'Капля'];

$error = '';
$f = [
    'name' => $product['name'] ?? '',
    'slug' => $product['slug'] ?? '',
    'category' => $product['category'] ?? ($cats[0]['skey'] ?? 'water'),
    'volume' => $product['volume'] ?? '',
    'price' => $product['price'] ?? '',
    'old_price' => $product['old_price'] ?? '',
    'short_desc' => $product['short_desc'] ?? '',
    'description' => $product['description'] ?? '',
    'badge' => $product['badge'] ?? '',
    'icon' => $product['icon'] ?? 'i-bottle',
    'sort' => $product['sort'] ?? 100,
    'is_active' => $product['is_active'] ?? 1,
];

function make_slug(string $text): string
{
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
        'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $text = mb_strtolower(trim($text));
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'product-' . random_int(1000, 9999);
}

function unique_slug(string $slug, int $exceptId): string
{
    $base = $slug;
    $i = 2;
    while (db_one('SELECT id FROM products WHERE slug = ? AND id <> ?', [$slug, $exceptId]) !== null) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    foreach ($f as $key => $_) {
        $f[$key] = is_int($f[$key]) ? $f[$key] : trim((string)($_POST[$key] ?? ''));
    }
    $f['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    if (mb_strlen((string)$f['name']) < 2) {
        $error = 'Укажите название товара (минимум 2 символа)';
    } elseif (!is_numeric($f['price']) || (float)$f['price'] < 0) {
        $error = 'Цена должна быть числом';
    } else {
        $catKeys = array_column($cats, 'skey');
        if (!in_array($f['category'], $catKeys, true)) {
            $f['category'] = $catKeys[0] ?? 'water';
        }
        $slug = unique_slug(make_slug((string)($f['slug'] !== '' ? $f['slug'] : $f['name'])), $id);

        $image = $product['image'] ?? '';
        $uploadNote = '';
        try {
            $uploaded = upload_image('image', 'product-' . $slug);
            if ($uploaded !== null) {
                if ($image) {
                    delete_upload($image);
                }
                $image = $uploaded['path'];
                $uploadNote = (string)$uploaded['note'];
            } elseif (!empty($_POST['remove_image']) && $image) {
                delete_upload($image);
                $image = '';
            }

            $oldPrice = $f['old_price'] === '' ? null : (float)$f['old_price'];
            $now = now();
            if ($product) {
                db_exec(
                    'UPDATE products SET slug=?, name=?, category=?, volume=?, short_desc=?, description=?, price=?, old_price=?, icon=?, image=?, badge=?, is_active=?, sort=?, updated_at=? WHERE id=?',
                    [$slug, $f['name'], $f['category'], $f['volume'], $f['short_desc'], $f['description'],
                     (float)$f['price'], $oldPrice, $f['icon'], $image, $f['badge'], $f['is_active'], (int)$f['sort'], $now, $id]
                );
                alog('update', 'product', $id, 'Изменён товар: ' . $f['name'] . ($uploadNote ? '; ' . $uploadNote : ''));
                flash('Товар сохранён' . ($uploadNote ? '. ' . $uploadNote : ''));
            } else {
                db_exec(
                    'INSERT INTO products (slug, name, category, volume, short_desc, description, price, old_price, icon, image, badge, is_active, sort, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$slug, $f['name'], $f['category'], $f['volume'], $f['short_desc'], $f['description'],
                     (float)$f['price'], $oldPrice, $f['icon'], $image, $f['badge'], $f['is_active'], (int)$f['sort'], $now, $now]
                );
                alog('create', 'product', db_id(), 'Добавлен товар: ' . $f['name'] . ($uploadNote ? '; ' . $uploadNote : ''));
                flash('Товар добавлен' . ($uploadNote ? '. ' . $uploadNote : ''));
            }
            redirect('products.php');
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
}

admin_head($product ? 'Товар: ' . $product['name'] : 'Новый товар');
?>
<div class="toolbar">
  <div>
    <h1><?= $product ? 'Редактирование товара' : 'Новый товар' ?></h1>
    <p class="sub">Фото загружается с устройства, описание показывается в карточке на сайте</p>
  </div>
  <a class="btn" href="products.php">← К списку</a>
</div>

<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card">
  <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
  <div class="form-grid">
    <div class="field full">
      <label for="name">Название *</label>
      <input id="name" name="name" value="<?= e($f['name']) ?>" required maxlength="160">
    </div>
    <div class="field">
      <label for="category">Категория</label>
      <select id="category" name="category">
        <?php foreach ($cats as $c): ?>
          <option value="<?= e($c['skey']) ?>" <?= $f['category'] === $c['skey'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="volume">Объём / фасовка</label>
      <input id="volume" name="volume" value="<?= e($f['volume']) ?>" placeholder="18,9 л">
    </div>
    <div class="field">
      <label for="price">Цена, руб. *</label>
      <input id="price" name="price" value="<?= e((string)$f['price']) ?>" inputmode="decimal" required>
    </div>
    <div class="field">
      <label for="old_price">Старая цена (для зачёркивания)</label>
      <input id="old_price" name="old_price" value="<?= e((string)$f['old_price']) ?>" inputmode="decimal" placeholder="необязательно">
    </div>
    <div class="field">
      <label for="short_desc">Короткое описание (под названием)</label>
      <input id="short_desc" name="short_desc" value="<?= e($f['short_desc']) ?>" maxlength="200" placeholder="Возвратная тара">
    </div>
    <div class="field">
      <label for="badge">Плашка</label>
      <input id="badge" name="badge" value="<?= e($f['badge']) ?>" maxlength="30" placeholder="Хит / Новинка">
    </div>
    <div class="field full">
      <label for="description">Описание товара</label>
      <textarea id="description" name="description" rows="7" placeholder="Что показать в карточке товара"><?= e($f['description']) ?></textarea>
    </div>
    <div class="field">
      <label for="icon">Иконка (если нет фото)</label>
      <select id="icon" name="icon">
        <?php foreach ($icons as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $f['icon'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sort">Порядок вывода</label>
      <input id="sort" name="sort" value="<?= (int)$f['sort'] ?>" inputmode="numeric">
      <span class="hint">Меньше число — выше в каталоге</span>
    </div>
    <div class="field">
      <label for="slug">Адрес (slug)</label>
      <input id="slug" name="slug" value="<?= e($f['slug']) ?>" placeholder="заполнится автоматически">
    </div>
    <div class="field">
      <label>Публикация</label>
      <label class="check" style="margin-top:8px"><input type="checkbox" name="is_active" value="1" <?= (int)$f['is_active'] ? 'checked' : '' ?>> Показывать на сайте</label>
    </div>
    <div class="field full">
      <label>Фото товара</label>
      <div class="img-preview">
        <?php if (!empty($product['image'])): ?>
          <img src="../<?= e($product['image']) ?>" alt="">
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
        <?php endif; ?>
      </div>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      <span class="hint">JPG, PNG, WEBP или GIF до <?= (int)upload_config()['max_mb'] ?> МБ. Фото автоматически сжимается до <?= (int)upload_config()['max_width'] ?> px. Если фото нет — показывается иконка.</span>
      <?php if (!empty($product['image'])): ?>
        <label class="check" style="margin-top:6px"><input type="checkbox" name="remove_image" value="1"> Удалить текущее фото</label>
      <?php endif; ?>
    </div>
  </div>
  <hr class="sep">
  <button class="btn btn-primary" type="submit"><?= $product ? 'Сохранить изменения' : 'Создать товар' ?></button>
  <a class="btn" href="products.php">Отмена</a>
</form>
<?php admin_foot(); ?>
