<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$id = (int)($_GET['id'] ?? 0);
$order = $id ? db_one('SELECT o.*, u.name AS user_name, u.phone AS user_phone, u.email AS user_email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$id]) : null;
if (!$order) {
    flash('Заказ не найден', 'err');
    redirect('orders.php');
}

$statuses = ['Новый', 'Подтверждён', 'Доставлен', 'Отменён'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $newStatus = (string)($_POST['status'] ?? '');
    if (in_array($newStatus, $statuses, true) && $newStatus !== $order['status']) {
        db_exec('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?', [$newStatus, now(), $id]);
        alog('update', 'order', $id, 'Статус заказа ' . $order['number'] . ': ' . $order['status'] . ' → ' . $newStatus);
        flash('Статус заказа изменён на «' . $newStatus . '»');
        redirect('order.php?id=' . $id);
    }
    flash('Статус не изменён', 'err');
    redirect('order.php?id=' . $id);
}

$items = json_decode((string)$order['items'], true) ?: [];

admin_head('Заказ ' . $order['number']);
?>
<div class="toolbar">
  <div>
    <h1>Заказ <?= e($order['number']) ?></h1>
    <p class="sub"><?= e($order['created_at']) ?> · <?= e($order['user_name']) ?>, <?= e($order['user_phone']) ?></p>
  </div>
  <a class="btn" href="orders.php">← К списку</a>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Состав заказа</h2>
    <div class="table-wrap">
      <table class="t order-items">
        <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th class="right">Сумма</th></tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['name'] ?? '') ?></td>
            <td class="num"><?= number_format((float)($it['price'] ?? 0), 2, ',', ' ') ?></td>
            <td><?= (int)($it['qty'] ?? 0) ?></td>
            <td class="num right"><?= number_format((float)($it['price'] ?? 0) * (int)($it['qty'] ?? 0), 2, ',', ' ') ?> руб.</td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" class="right"><b>Итого</b></td>
          <td class="right"><b><?= number_format((float)$order['total'], 2, ',', ' ') ?> руб.</b></td>
        </tr>
      </table>
    </div>
    <?php if ($order['comment']): ?>
      <hr class="sep">
      <div class="small muted">Комментарий клиента</div>
      <div><?= nl2br(e($order['comment'])) ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Доставка и оплата</h2>
    <dl class="kv">
      <dt>Клиент</dt><dd><a href="users.php?q=<?= e($order['user_phone']) ?>"><?= e($order['user_name']) ?></a><br><span class="small muted"><?= e($order['user_phone']) ?> · <?= e($order['user_email']) ?></span></dd>
      <dt>Город</dt><dd><?= e($order['city']) ?></dd>
      <dt>Адрес</dt><dd><?= e($order['address_text']) ?></dd>
      <dt>Зона</dt><dd><?= e($order['zone']) ?></dd>
      <dt>Интервал</dt><dd><?= e($order['interval']) ?></dd>
      <dt>Оплата</dt><dd><?= e($order['payment_title']) ?></dd>
      <dt>Статус</dt><dd><span class="badge <?= status_class((string)$order['status']) ?>"><?= e($order['status']) ?></span></dd>
    </dl>

    <hr class="sep">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(admin_csrf()) ?>">
      <div class="field" style="margin-bottom:10px">
        <label>Изменить статус</label>
        <select name="status">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Сохранить статус</button>
    </form>
  </div>
</div>
<?php admin_foot(); ?>
