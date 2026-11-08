<?php
declare(strict_types=1);

/** Отправка писем: PHP mail(), SMTP ящика хостинга или запись в лог (для тестов). */

function mail_from(): string
{
    return (string)cfg('mail.from');
}

function mail_from_name(): string
{
    return (string)(cfg('mail.from_name') ?: 'PROTERA');
}

/**
 * @return bool true, если письмо принято почтовым сервером
 */
function send_mail(string $to, string $subject, string $html, string $text): bool
{
    $driver = (string)cfg('mail.driver');

    if ($driver === 'log') {
        return mail_log($to, $subject, $text);
    }

    $boundary = 'protera-' . bin2hex(random_bytes(8));
    $headers = mail_headers($boundary);
    $body = mail_multipart_body($html, $text, $boundary);

    if ($driver === 'smtp') {
        try {
            $smtp = new SmtpMailer((array)cfg('mail.smtp'));
            $smtp->send($to, $subject, $headers, $body);
            return true;
        } catch (Throwable $e) {
            error_log('[protera] SMTP error: ' . $e->getMessage());
            return false;
        }
    }

    $subjectEncoded = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    $from = mail_from();
    $ok = @mail($to, $subjectEncoded, $body, $headers, '-f' . $from);
    if (!$ok) {
        error_log('[protera] mail() failed for ' . $to);
    }
    return (bool)$ok;
}

function mail_headers(string $boundary): string
{
    $fromEmail = mail_from();
    $fromName = mb_encode_mimeheader(mail_from_name(), 'UTF-8', 'B', "\r\n");
    $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);

    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . (string)(cfg('mail.reply_to') ?: $fromEmail),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    return implode("\r\n", $headers);
}

function mail_multipart_body(string $html, string $text, string $boundary): string
{
    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n")
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . "--{$boundary}--\r\n";
    return $body;
}

function mail_log(string $to, string $subject, string $text): bool
{
    $dir = dirname((string)cfg('db.path'));
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '===== ' . now() . ' =====' . PHP_EOL
        . 'To: ' . $to . PHP_EOL
        . 'Subject: ' . $subject . PHP_EOL
        . $text . PHP_EOL . PHP_EOL;
    return @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
}

/** Простой SMTP-клиент с AUTH LOGIN и поддержкой SSL/STARTTLS. */
final class SmtpMailer
{
    private $socket = null;
    private array $cfg;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function send(string $to, string $subject, string $headers, string $body): void
    {
        $host = (string)($this->cfg['host'] ?? '');
        $port = (int)($this->cfg['port'] ?? 465);
        $secure = strtolower((string)($this->cfg['secure'] ?? 'ssl'));
        if ($host === '') {
            throw new RuntimeException('SMTP host не задан');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            throw new RuntimeException('Не удалось подключиться к SMTP: ' . $errstr);
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, 20);

        $this->expect([220]);
        $ehlo = $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

        if ($secure === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Не удалось включить TLS');
            }
            $ehlo = $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }

        $user = (string)($this->cfg['user'] ?? '');
        $pass = (string)($this->cfg['pass'] ?? '');
        if ($user !== '') {
            // AUTH PLAIN, если сервер не предлагает LOGIN
            if (stripos($ehlo, 'LOGIN') === false && stripos($ehlo, 'PLAIN') !== false) {
                $this->command('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235]);
            } else {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($user), [334]);
                $this->command(base64_encode($pass), [235]);
            }
        }

        $this->command('MAIL FROM:<' . mail_from() . '>', [250]);
        $this->command('RCPT TO:<' . $to . '>', [250, 251]);
        $this->command('DATA', [354]);

        $data = $headers . "\r\n"
            . 'To: <' . $to . '>' . "\r\n"
            . 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n") . "\r\n\r\n"
            . $body;
        // dot-stuffing
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($this->socket, $data . "\r\n.\r\n");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        fclose($this->socket);
        $this->socket = null;
    }

    private function command(string $command, array $expected): string
    {
        fwrite($this->socket, $command . "\r\n");
        return $this->expect($expected);
    }

    private function expect(array $expected): string
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP ответ ' . trim($response));
        }
        return $response;
    }
}

/* ---------- Шаблоны писем ---------- */

function email_layout(string $title, string $bodyHtml): string
{
    return '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#eaf2fb;font-family:Arial,Helvetica,sans-serif;color:#0d2b4e;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eaf2fb;padding:28px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:18px;overflow:hidden;">'
        . '<tr><td style="padding:26px 30px 6px;">'
        . '<div style="font-size:20px;font-weight:bold;color:#0d2b4e;">PROTERA</div>'
        . '<div style="font-size:12px;color:#6b87a8;margin-top:2px;">Доставка питьевой воды</div>'
        . '</td></tr>'
        . '<tr><td style="padding:10px 30px 28px;">'
        . '<h1 style="font-size:20px;margin:14px 0 14px;color:#0d2b4e;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $bodyHtml
        . '</td></tr>'
        . '<tr><td style="padding:16px 30px 24px;border-top:1px solid #e3eef9;font-size:11px;color:#8aa2bd;">'
        . 'Письмо отправлено автоматически, отвечать на него не нужно.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function email_button(string $url, string $label): string
{
    return '<p style="margin:22px 0;"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="display:inline-block;background:#1279c8;color:#ffffff;text-decoration:none;font-weight:bold;'
        . 'font-size:15px;padding:14px 26px;border-radius:999px;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></p>';
}

function verify_email_content(string $name, string $token): array
{
    $url = site_url('api/verify.php?token=' . urlencode($token));
    $ttl = (int)ceil(((int)cfg('security.verify_ttl')) / 3600);
    $hello = htmlspecialchars($name !== '' ? $name : 'друг', ENT_QUOTES, 'UTF-8');

    $subject = 'Подтвердите e-mail — PROTERA';
    $html = email_layout(
        'Подтвердите e-mail',
        '<p style="font-size:14px;line-height:1.6;color:#3d5f88;margin:0 0 10px;">Здравствуйте, ' . $hello . '!</p>'
        . '<p style="font-size:14px;line-height:1.6;color:#3d5f88;margin:0;">Вы регистрировались на сайте доставки воды PROTERA. '
        . 'Нажмите кнопку ниже, чтобы подтвердить адрес и войти в аккаунт.</p>'
        . email_button($url, 'Подтвердить e-mail')
        . '<p style="font-size:12px;line-height:1.6;color:#6b87a8;margin:0;">Ссылка действует ' . $ttl . ' ч. '
        . 'Если вы не регистрировались, просто удалите это письмо.</p>'
        . '<p style="font-size:12px;line-height:1.6;color:#8aa2bd;margin:16px 0 0;word-break:break-all;">Если кнопка не работает: ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</p>'
    );
    $text = "Здравствуйте, {$name}!\n\n"
        . "Подтвердите e-mail, чтобы войти в аккаунт PROTERA:\n{$url}\n\n"
        . "Ссылка действует {$ttl} ч. Если вы не регистрировались, удалите письмо.\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text, 'url' => $url];
}

function send_verify_email(string $email, string $name, string $token): bool
{
    $content = verify_email_content($name, $token);
    return send_mail($email, $content['subject'], $content['html'], $content['text']);
}

function reset_email_content(string $name, string $token): array
{
    $url = site_url('?reset=' . urlencode($token));
    $ttl = (int)ceil(((int)cfg('security.reset_ttl')) / 60);
    $hello = htmlspecialchars($name !== '' ? $name : 'друг', ENT_QUOTES, 'UTF-8');

    $subject = 'Сброс пароля — PROTERA';
    $html = email_layout(
        'Сброс пароля',
        '<p style="font-size:14px;line-height:1.6;color:#3d5f88;margin:0 0 10px;">Здравствуйте, ' . $hello . '!</p>'
        . '<p style="font-size:14px;line-height:1.6;color:#3d5f88;margin:0;">Мы получили запрос на восстановление пароля. '
        . 'Чтобы задать новый пароль, нажмите кнопку ниже.</p>'
        . email_button($url, 'Задать новый пароль')
        . '<p style="font-size:12px;line-height:1.6;color:#6b87a8;margin:0;">Ссылка действует ' . $ttl . ' мин. '
        . 'Если вы не запрашивали сброс, ничего делать не нужно — пароль останется прежним.</p>'
        . '<p style="font-size:12px;line-height:1.6;color:#8aa2bd;margin:16px 0 0;word-break:break-all;">Если кнопка не работает: ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</p>'
    );
    $text = "Здравствуйте, {$name}!\n\n"
        . "Ссылка для сброса пароля PROTERA (действует {$ttl} мин):\n{$url}\n\n"
        . "Если вы не запрашивали сброс, просто проигнорируйте письмо.\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text, 'url' => $url];
}

function send_reset_email(string $email, string $name, string $token): bool
{
    $content = reset_email_content($name, $token);
    return send_mail($email, $content['subject'], $content['html'], $content['text']);
}
