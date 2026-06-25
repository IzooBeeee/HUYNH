<?php
// Custom PHP built-in server router
// Viết access log sang STDERR thay vì STDOUT để không lẫn vào HTTP response

error_reporting(E_ERROR);
ini_set('display_errors', '0');

// publicPath phải trỏ đến thư mục public/ — không dùng getcwd() vì CWD là project root
$publicPath = __DIR__ . '/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Serve static files trực tiếp
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}

// Ghi access log sang STDERR (không ảnh hưởng HTTP response)
@file_put_contents('php://stderr',
    '[' . date('D M j H:i:s Y') . '] '
    . $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT']
    . ' [' . $_SERVER['REQUEST_METHOD'] . '] URI: ' . $uri . "\n"
);

require_once $publicPath . '/index.php';
