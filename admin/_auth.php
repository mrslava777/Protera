<?php
declare(strict_types=1);

define('PROTERA', true);

$CONFIG = require dirname(__DIR__) . '/api/config.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set((string)($CONFIG['timezone'] ?? 'Europe/Minsk'));

require dirname(__DIR__) . '/api/lib.php';
require dirname(__DIR__) . '/api/db.php';

start_session();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function admin_csrf(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_check(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals(admin_csrf(), $token)) {
        http_response_code(419);
        exit('Сессия устарела. Обновите страницу и повторите действие.');
    }
}

function admin_user(): ?array
{
    static $admin = null;
    static $loaded = false;
    if ($loaded) {
        return $admin;
    }
    $loaded = true;
    $id = (int)($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $admin = db_one('SELECT * FROM admins WHERE id = ?', [$id]);
    if (!$admin) {
        unset($_SESSION['admin_id']);
        return null;
    }
    return $admin;
}

function admin_require(): array
{
    $admin = admin_user();
    if ($admin === null) {
        redirect('login.php');
    }
    return $admin;
}

function alog(string $action, string $entity = '', $entityId = '', string $details = ''): void
{
    $admin = admin_user();
    db_exec(
        'INSERT INTO admin_log (admin_id, admin_login, action, entity, entity_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$admin['id'] ?? null, $admin['login'] ?? 'system', $action, $entity, (string)$entityId, mb_substr($details, 0, 500), client_ip(), now()]
    );
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $f = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return $f;
}

function setting(string $key, string $default = ''): string
{
    $row = db_one('SELECT svalue FROM settings WHERE skey = ?', [$key]);
    return $row ? (string)$row['svalue'] : $default;
}

function save_setting(string $key, string $value): void
{
    if (db_one('SELECT skey FROM settings WHERE skey = ?', [$key]) === null) {
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
    } else {
        db_exec('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
    }
}

function admin_bool($value): int
{
    return !empty($value) ? 1 : 0;
}

/**
 * Настройки загрузки фото.
 */
function upload_config(): array
{
    $cfg = (array)(cfg('uploads') ?: []);
    return [
        'max_mb'     => max(1, (int)($cfg['max_mb'] ?? 20)),
        'max_width'  => max(400, (int)($cfg['max_width'] ?? 1600)),
        'max_height' => max(400, (int)($cfg['max_height'] ?? 1600)),
        'quality'    => min(95, max(55, (int)($cfg['quality'] ?? 82))),
    ];
}

function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' МБ';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' КБ';
    }
    return $bytes . ' Б';
}

/**
 * Сжимает уже сохранённый файл: поворот по EXIF, уменьшение до max_width/max_height,
 * перекодирование с потерей качества (JPEG/WEBP) или PNG-компрессией.
 * Возвращает текст для сообщения администратору.
 */
function compress_image_file(string $path, int $type): string
{
    if (!function_exists('imagecreatetruecolor')) {
        return 'GD не установлен — фото сохранено без сжатия';
    }
    if ($type === IMAGETYPE_GIF) {
        return '';
    }

    $cfg = upload_config();
    $before = (int)@filesize($path);
    $info = @getimagesize($path);
    if ($info === false) {
        return '';
    }
    [$width, $height] = $info;

    $source = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default        => false,
    };
    if (!$source) {
        return '';
    }

    // Поворот фотографий с телефона по EXIF
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle !== 0) {
            $rotated = @imagerotate($source, $angle, 0);
            if ($rotated) {
                imagedestroy($source);
                $source = $rotated;
                $width = imagesx($source);
                $height = imagesy($source);
            }
        }
    }

    $scale = min(1, $cfg['max_width'] / max(1, $width), $cfg['max_height'] / max(1, $height));
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));

    $target = imagecreatetruecolor($newWidth, $newHeight);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
    }
    imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($source);

    $tmp = $path . '.tmp';
    $saved = false;
    if ($type === IMAGETYPE_JPEG) {
        imageinterlace($target, true);
        $saved = imagejpeg($target, $tmp, $cfg['quality']);
    } elseif ($type === IMAGETYPE_PNG) {
        $saved = imagepng($target, $tmp, 9);
    } elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) {
        $saved = imagewebp($target, $tmp, $cfg['quality']);
    }
    imagedestroy($target);

    if (!$saved || !is_file($tmp) || filesize($tmp) < 1) {
        @unlink($tmp);
        return '';
    }

    $after = (int)filesize($tmp);
    // Если сжатие не помогло и размер не менялся — оставляем оригинал
    if ($after >= $before && $scale === 1.0) {
        @unlink($tmp);
        return '';
    }
    @unlink($path);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return '';
    }

    $verb = $after < $before ? 'сжато' : 'обработано';
    return 'Фото ' . $verb . ': ' . human_size($before) . ' → ' . human_size($after)
        . ($scale < 1 ? ', размер ' . $newWidth . '×' . $newHeight . ' px' : '');
}

/**
 * Сохраняет загруженное изображение.
 * @return array{path:string,note:string}|null
 */
function upload_image(string $field, string $prefix = 'img'): ?array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    $maxMb = upload_config()['max_mb'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new RuntimeException('Файл больше ' . $maxMb . ' МБ (или превышен лимит PHP upload_max_filesize)');
        }
        throw new RuntimeException('Не удалось загрузить файл (код ' . (int)$file['error'] . ')');
    }
    if ($file['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException('Файл больше ' . $maxMb . ' МБ');
    }

    $info = @getimagesize($file['tmp_name']);
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if ($info === false || !isset($map[$info[2]])) {
        throw new RuntimeException('Поддерживаются только JPG, PNG, WEBP и GIF');
    }

    $dir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('Нет папки uploads/products — создайте её и дайте права на запись');
    }

    $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $map[$info[2]];
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Не удалось сохранить файл');
    }

    $note = compress_image_file($path, (int)$info[2]);

    return ['path' => 'uploads/products/' . $name, 'note' => $note];
}

function delete_upload(?string $relative): void
{
    if (!$relative || strpos($relative, 'uploads/products/') !== 0) {
        return;
    }
    $path = dirname(__DIR__) . '/' . $relative;
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Города всех активных зон (для выбора в адресах). */
function all_cities(): array
{
    $cities = [];
    foreach (db_all('SELECT cities FROM zones WHERE is_active = 1 ORDER BY sort, id') as $row) {
        $list = json_decode((string)$row['cities'], true);
        if (is_array($list)) {
            foreach ($list as $city) {
                $cities[$city] = true;
            }
        }
    }
    return array_keys($cities);
}
