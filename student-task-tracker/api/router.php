<?php
/**
 * router.php — PHP built-in server router for local development.
 *
 * Run from the api/ directory:
 *   php -S 0.0.0.0:8000 router.php
 *
 * Routes:
 *   /auth*   → auth.php  (register / login / logout)
 *   /tasks*  → tasks.php (CRUD)
 */

// Serve real static files (CSS, images, etc.) directly if they exist
if (php_sapi_name() === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file(__DIR__ . $urlPath)) {
        return false;
    }
}

// Determine which segment to route on
$urlPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($urlPath, '/'));

$segment = null;
foreach (['auth', 'tasks'] as $route) {
    if (in_array($route, $pathParts)) {
        $segment = $route;
        break;
    }
}

switch ($segment) {
    case 'auth':
        require __DIR__ . '/auth.php';
        break;
    case 'tasks':
    default:
        require __DIR__ . '/tasks.php';
}
?>
