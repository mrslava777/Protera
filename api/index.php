<?php
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo '{"ok":false,"code":"forbidden","message":"Forbidden"}';
