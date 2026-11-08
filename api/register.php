<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();

$name     = field('name');
$email    = mb_strtolower(field('email'));
$phone    = normalize_phone(field('phone'));
$password = (string)(request_body()['password'] ?? '');
$agree    = !empty(request_body()['agree']);

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    fail('name', 'Укажите имя (2–100 символов)');
}
if (!valid_email($email)) {
    fail('email', 'Проверьте адрес e-mail');
}
if ($phone === null) {
    fail('phone', 'Введите телефон в формате +375 (29) 123-45-67');
}
if (($problem = password_problem($password)) !== null) {
    fail('password', $problem);
}
if (!$agree) {
    fail('agree', 'Нужно согласие на обработку персональных данных');
}
if (too_many('register', client_ip(), 5, 3600)) {
    fail('rate', 'Слишком много попыток регистрации. Попробуйте через час.', 429);
}

if (db_one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
    fail('email_taken', 'Аккаунт с таким e-mail уже существует. Войдите или восстановите пароль.', 409);
}
if (db_one('SELECT id FROM users WHERE phone = ?', [$phone]) !== null) {
    fail('phone_taken', 'Этот телефон уже привязан к другому аккаунту.', 409);
}

$now = now();
db_exec(
    'INSERT INTO users (email, phone, name, city, address, pass_hash, email_verified, pass_changed_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
    [$email, $phone, $name, '', '', password_hash($password, PASSWORD_DEFAULT), $now, $now, $now]
);
$userId = db_id();

$token = issue_token($userId, 'verify', (int)cfg('security.verify_ttl'));
$sent = send_verify_email($email, $name, $token);

add_event('register', $email);

json_out([
    'ok'        => true,
    'mail_sent' => $sent,
    'email'     => $email,
    'message'   => $sent
        ? 'Аккаунт создан. Мы отправили письмо для подтверждения e-mail.'
        : 'Аккаунт создан, но письмо отправить не удалось. Нажмите «Отправить ещё раз» или напишите нам.',
], 201);
