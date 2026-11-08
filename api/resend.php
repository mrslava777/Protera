<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_post();
csrf_check();

$email = mb_strtolower(field('email'));

$generic = [
    'ok'      => true,
    'message' => 'Если такой адрес есть в базе, письмо отправлено. Проверьте входящие и папку «Спам».',
];

if (!valid_email($email)) {
    json_out($generic);
}
if (too_many('resend_email', $email, 3, 3600) || too_many('resend_ip', client_ip(), 10, 3600)) {
    fail('rate', 'Слишком много запросов. Попробуйте через час.', 429);
}

add_event('resend_email', $email);
add_event('resend_ip', client_ip());

$user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
if ($user !== null && (int)$user['email_verified'] !== 1) {
    $token = issue_token((int)$user['id'], 'verify', (int)cfg('security.verify_ttl'));
    send_verify_email((string)$user['email'], (string)$user['name'], $token);
}

json_out($generic);
