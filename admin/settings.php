<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$fields = [
    'Главный экран' => [
        'hero_title' => ['Заголовок', 'input', 'Вода, которую удобно заказывать'],
        'hero_sub' => ['Подзаголовок', 'textarea', 'Свежая питьевая вода для дома и офиса. Доставим в удобное время.'],
    ],
    'Контакты' => [
        'site_phone' => ['Телефон (как показывать)', 'input', '+375 (29) 000-00-00'],
        'site_phone_href' => ['Телефон для ссылки tel:', 'input', '+375290000000'],
        'site_email' => ['E-mail', 'input', 'info@example.by'],
        'site_area' => ['Зона доставки', 'input', 'Минская и Могилёвская области'],
        'site_hours' => ['Режим работы', 'input', 'с 8:00 до 20:00'],
    ],
    'О компании' => [
        'about_title' => ['Заголовок блока', 'input', 'О компании PROTERA'],
        'about_p1' => ['Первый абзац', 'textarea', ''],
        'about_p2' => ['Второй абзац', 'textarea', ''],
    ],
    'Доставка' => [
        'delivery_note' => ['Подпись к разделу «Доставка»', 'input', 'Минская область ...'],
    ],
    'SEO' => [
        'seo_title' => ['Title страницы', 'input', ''],
        'seo_description' => ['Description', 'textarea', ''],
    ],
    'Подвал' => [
        'footer_text' => ['Копирайт', 'input', '© PROTERA. Все права защищены.'],
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $changed = [];
    foreach ($fields as $group) {
        foreach ($group as $key => $meta) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $value = trim((string)$_POST[$key]);
            if (setting($key) !== $value) {
                save_setting($key, $value);
                $changed[] = $key;
            }
        }
    }
    alog('update', 'settings', '', $changed ? 'Изменены настройки: ' . implode(', ', $changed) : 'Настройки сохранены без изменений');
    flash($changed ? 'Настройки сохранены (' . count($changed) . ')' : 'Изменений нет');
    redirect('settings.php');
}

admin_head('Настройки сайта');
?>
<div class="toolbar">
  <div><h1>Настройки сайта</h1><p class="sub">Тексты, контакты и SEO — изменения сразу видны на сайте</p></div>
</div>

<form method="post">
  <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
  <?php foreach ($fields as $group => $items): ?>
    <div class="card">
      <h2><?= e($group) ?></h2>
      <div class="form-grid">
        <?php foreach ($items as $key => $meta): [$label, $type, $placeholder] = $meta; $value = setting($key, $placeholder); ?>
          <div class="field <?= $type === 'textarea' ? 'full' : '' ?>">
            <label for="<?= e($key) ?>"><?= e($label) ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="<?= $key === 'seo_description' ? 3 : 4 ?>" placeholder="<?= e($placeholder) ?>"><?= e($value) ?></textarea>
            <?php else: ?>
              <input id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Сохранить настройки</button>
</form>
<?php admin_foot(); ?>
