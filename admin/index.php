<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

admin_require();

$ordersTotal = (int)db_val('SELECT COUNT(*) FROM orders');
$ordersNew = (int)db_val("SELECT COUNT(*) FROM orders WHERE status = 'Новый'");
$ordersSum = (float)db_val("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'Отменён'");
$users = (int)db_val('SELECT COUNT(*) FROM users');
$usersVerified = (int)db_val('SELECT COUNT(*) FROM users WHERE email_verified = 1');
$products = (int)db_val('SELECT COUNT(*) FROM products WHERE is_active = 1');
$addresses = (int)db_val('SELECT COUNT(*) FROM addresses');

$recentOrders = db_all('SELECT o.*, u.name AS user_name, u.phone AS user_phone FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 6');
$recentLog = db_all('SELECT * FROM admin_log ORDER BY id DESC LIMIT 8');

admin_head('Обзор');
?>
<div class="toolbar">
  <div>
    <h1>Обзор</h1>
    <p class="sub">Сводка по заказам, клиентам и последним действиям в панели</p>
  </div>
  <a class="btn btn-primary" href="product-edit.php">+ Новый товар</a>
</div>

<div class="grid cols-4">
  <div class="stat"><b><?= $ordersNew ?></b><span>новых заказов</span></div>
  <div class="stat"><b><?= $ordersTotal ?></b><span>заказов всего</span></div>
  <div class="stat"><b><?= number_format($ordersSum, 2, ',', ' ') ?> руб.</b><span>сумма заказов</span></div>
  <div class="stat"><b><?= $users ?></b><span>клиентов (<?= $usersVerified ?> подтверждённых)</span></div>
</div>

<div class="grid cols-4" style="margin-top:14px">
  <div class="stat"><b><?= $products ?></b><span>активных товаров</span></div>
  <div class="stat"><b><?= $addresses ?></b><span>адресов доставки</span></div>
  <div class="stat"><b><?= (int)db_val('SELECT COUNT(*) FROM zones WHERE is_active = 1') ?></b><span>зон доставки</span></div>
  <div class="stat"><b><?= (int)db_val('SELECT COUNT(*) FROM payments WHERE is_active = 1') ?></b><span>способов оплаты</span></div>
</div>

<div class="grid cols-2" style="margin-top:18px">
  <div class="card" style="margin-bottom:0">
    <h2>Последние заказы <a class="btn btn-sm" href="orders.php">Все заказы</a></h2>
    <?php if (!$recentOrders): ?>
      <div class="empty">Заказов пока нет.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="t">
          <tr><th>№</th><th>Клиент</th><th>Сумма</th><th>Статус</th></tr>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td class="num"><a href="order.php?id=<?= (int)$o['id'] ?>"><?= e($o['number']) ?></a></td>
              <td><?= e($o['user_name']) ?><div class="small muted"><?= e($o['created_at']) ?></div></td>
              <td class="num"><?= number_format((float)$o['total'], 2, ',', ' ') ?> руб.</td>
              <td><span class="badge <?= status_class((string)$o['status']) ?>"><?= e($o['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-bottom:0">
    <h2>Последние действия <a class="btn btn-sm" href="log.php">Весь журнал</a></h2>
    <?php if (!$recentLog): ?>
      <div class="empty">Записей пока нет.</div>
    <?php else: ?>
      <?php foreach ($recentLog as $row): ?>
        <div style="padding:8px 0;border-bottom:1px solid #eef5fc;font-size:12.5px">
          <div><b><?= e($row['admin_login']) ?></b> · <?= e($row['action']) ?><?= $row['entity'] ? ' · ' . e($row['entity']) : '' ?></div>
          <div class="small muted"><?= e($row['details']) ?></div>
          <div class="small muted"><?= e($row['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_foot(); ?>
