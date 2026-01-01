<?php
session_start();

// Simple Autoloader
spl_autoload_register(function ($class_name) {
    $paths = [
        __DIR__ . '/../src/' . $class_name . '.php',
        __DIR__ . '/../src/Controllers/' . $class_name . '.php',
        __DIR__ . '/../src/Models/' . $class_name . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Route handling
if ($uri === '/' || $uri === '/index.php') {
    if (!Auth::hasUsers()) {
        header('Location: /setup');
        exit;
    }
    if (!Auth::user()) {
        header('Location: /login');
        exit;
    }
    (new ProjectController())->index();
} elseif ($uri === '/setup') {
    (new AuthController())->setup();
} elseif ($uri === '/login') {
    (new AuthController())->login();
} elseif ($uri === '/logout') {
    (new AuthController())->logout();
} elseif ($uri === '/projects/create') {
    (new ProjectController())->create();
} elseif ($uri === '/projects/update') {
    (new ProjectController())->update();
} elseif ($uri === '/projects/delete') {
    (new ProjectController())->delete();
} elseif (preg_match('#^/projects/(\d+)$#', $uri, $matches)) {
    (new IssueController())->index($matches[1]);
} elseif (preg_match('#^/projects/(\d+)/kanban$#', $uri, $matches)) {
    (new IssueController())->kanban($matches[1]);
} elseif ($uri === '/issues/create') {
    (new IssueController())->create();
} elseif ($uri === '/issues/update_status') {
    (new IssueController())->updateStatus();
} elseif ($uri === '/issues/reorder') {
    (new IssueController())->reorder();
} elseif (preg_match('#^/issues/(\d+)$#', $uri, $matches)) {
    (new IssueController())->show($matches[1]);
} elseif (preg_match('#^/issues/(\d+)/edit$#', $uri, $matches)) {
    (new IssueController())->edit($matches[1]);
} elseif (preg_match('#^/issues/(\d+)/update$#', $uri, $matches)) {
    (new IssueController())->update($matches[1]);
} elseif (preg_match('#^/issues/(\d+)/delete$#', $uri, $matches)) {
    (new IssueController())->delete($matches[1]);
} elseif ($uri === '/comments/create') {
    (new CommentController())->create();
} elseif ($uri === '/admin' || $uri === '/admin/users') {
    (new AdminController())->users();
} elseif ($uri === '/admin/users/toggle_admin') {
    (new AdminController())->toggleAdmin();
} elseif ($uri === '/admin/users/create') {
    (new AdminController())->createUser();
} elseif ($uri === '/admin/users/update') {
    (new AdminController())->updateUser();
} elseif ($uri === '/admin/users/delete') {
    (new AdminController())->deleteUser();
} elseif ($uri === '/admin/logs') {
    (new AdminController())->logs();
} elseif ($uri === '/admin/settings') {
    (new AdminController())->settings();
} else {
    http_response_code(404);
    echo "404 Not Found";
}
