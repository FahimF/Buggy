<?php
session_start();

// Simple Autoloader
spl_autoload_register(function ($class_name) {
    $paths = [
        __DIR__ . '/../src/' . $class_name . '.php',
        __DIR__ . '/../src/Controllers/' . $class_name . '.php',
        __DIR__ . '/../src/Models/' . $class_name . '.php',
        __DIR__ . '/../src/Services/' . $class_name . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Detect and set system timezone
$systemTimezone = 'UTC';
if (is_link('/etc/localtime')) {
    // Mac/Linux: Resolve /etc/localtime symlink
    $filename = readlink('/etc/localtime');
    if (strpos($filename, '/usr/share/zoneinfo/') !== false) {
        $systemTimezone = substr($filename, strpos($filename, '/usr/share/zoneinfo/') + 20);
    } elseif (strpos($filename, '/var/db/timezone/zoneinfo/') !== false) {
         // Some macOS versions
        $systemTimezone = substr($filename, strpos($filename, '/var/db/timezone/zoneinfo/') + 25);
    }
} else {
    // Fallback: Try to get from shell
    $tz = trim(shell_exec('date +%Z'));
    // Note: %Z returns abbreviation (EST), which isn't always a valid identifier for DateTimeZone.
    // %z returns offset (-0500).
    // Better to stick to UTC if we can't get a proper Region/City identifier,
    // or rely on PHP's internal guess if valid.
    if (date_default_timezone_get() !== 'UTC') {
        $systemTimezone = date_default_timezone_get();
    }
}
// Validate timezone
if (!in_array($systemTimezone, DateTimeZone::listIdentifiers())) {
    $systemTimezone = 'UTC'; 
}
date_default_timezone_set($systemTimezone);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Route handling
if ($uri === '/' || $uri === '/dashboard') {
    if (!Auth::hasUsers()) {
        header('Location: /setup');
        exit;
    }
    if (!Auth::user()) {
        header('Location: /login');
        exit;
    }
    (new ProjectController())->dashboard();
} elseif ($uri === '/projects' || $uri === '/projects/index.php') {
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
} elseif ($uri === '/projects/pin') {
    (new ProjectController())->pin();
} elseif ($uri === '/projects/unpin') {
    (new ProjectController())->unpin();
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
} elseif (preg_match('#^/comments/(\d+)/edit$#', $uri, $matches)) {
    (new CommentController())->edit($matches[1]);
} elseif (preg_match('#^/comments/(\d+)/update$#', $uri, $matches)) {
    (new CommentController())->update($matches[1]);
} elseif (preg_match('#^/comments/(\d+)/delete$#', $uri, $matches)) {
    (new CommentController())->delete($matches[1]);
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
} elseif ($uri === '/admin/logs/clear') {
    (new AdminController())->clearLogs();
} elseif ($uri === '/admin/logs') {
    (new AdminController())->logs();
} elseif ($uri === '/admin/settings') {
    (new AdminController())->settings();
} elseif ($uri === '/admin/settings/test-email') {
    (new AdminController())->testEmail();
} elseif ($uri === '/admin/inbox') {
    (new AdminController())->inbox();
} elseif ($uri === '/admin/inbox/delete-selected') {
    (new AdminController())->deleteSelectedInboxItems();
} elseif ($uri === '/admin/inbox/clear-completed') {
    (new AdminController())->clearCompletedInboxItems();
} elseif ($uri === '/tasks') {
    (new TaskController())->index();
} elseif ($uri === '/tasks/create-list') {
    (new TaskController())->createList();
} elseif ($uri === '/tasks/update-list') {
    (new TaskController())->updateList();
} elseif ($uri === '/tasks/delete-list') {
    (new TaskController())->deleteList();
} elseif (preg_match('#^/tasks/list/(\d+)$#', $uri, $matches)) {
    (new TaskController())->listTasks($matches[1]);
} elseif ($uri === '/tasks/create-task') {
    (new TaskController())->createTask();
} elseif ($uri === '/tasks/update-task') {
    (new TaskController())->updateTask();
} elseif ($uri === '/tasks/delete-task') {
    (new TaskController())->deleteTask();
} elseif ($uri === '/tasks/update-status') {
    (new TaskController())->updateTaskStatus();
} elseif ($uri === '/tasks/inbox') {
    (new TaskController())->inbox();
} elseif ($uri === '/tasks/mark-completed') {
    (new TaskController())->markInboxCompleted();
} elseif ($uri === '/tasks/mark-all-completed') {
    (new TaskController())->markInboxAllCompleted();
} elseif ($uri === '/tasks/process-recurring') {
    (new TaskController())->processRecurringTasks();
} else {
    http_response_code(404);
    echo "404 Not Found";
}
