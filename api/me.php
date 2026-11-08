<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = current_user();

json_out([
    'ok'   => true,
    'user' => $user !== null ? public_user($user) : null,
    'csrf' => csrf_token(),
]);
