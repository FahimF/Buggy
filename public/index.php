<?php
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

// Session Configuration
ini_set('session.gc_maxlifetime', 1209600);
ini_set('session.cookie_lifetime', 1209600);

// Register Custom Session Handler
$handler = new DatabaseSessionHandler();
session_set_save_handler($handler, true);

session_start();

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

// Run automatic archiving on page access (WordPress-style cron)
try {
    $archiveDays = Settings::get('archive_after_days', '30');
    if (is_numeric($archiveDays) && $archiveDays >= 0) {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE tasks 
                              SET is_archived = 1, updated_at = CURRENT_TIMESTAMP 
                              WHERE is_archived = 0 
                              AND status IN ('Completed', 'WND') 
                              AND updated_at <= datetime('now', '-' . (int)$archiveDays . ' days')");
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            Logger::log('Automatic Tasks Archiving', "Archived $count completed tasks older than $archiveDays days");
        }
    }
} catch (Exception $e) {
    // Fail silently in case database/migration hasn't run yet or table is missing
}

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
} elseif ($uri === '/profile') {
    (new ProfileController())->index();
} elseif ($uri === '/profile/update') {
    (new ProfileController())->update();
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
} elseif ($uri === '/projects/import-tasks') {
    (new ProjectController())->importTasks();
} elseif (preg_match('#^/projects/(\d+)$#', $uri, $matches)) {
    (new TaskController())->index($matches[1]);
} elseif (preg_match('#^/projects/(\d+)/kanban$#', $uri, $matches)) {
    (new TaskController())->kanban($matches[1]);
} elseif (preg_match('#^/projects/(\d+)/status$#', $uri, $matches)) {
    (new TaskController())->status($matches[1]);
} elseif ($uri === '/tasks/create') {
    (new TaskController())->create();
} elseif ($uri === '/tasks/update_status') {
    (new TaskController())->updateStatus();
} elseif ($uri === '/tasks/sub-tasks') {
    (new TaskController())->getSubTasks();
} elseif ($uri === '/tasks/sub-tasks/create') {
    (new TaskController())->createSubTask();
} elseif ($uri === '/tasks/sub-tasks/toggle') {
    (new TaskController())->toggleSubTask();
} elseif ($uri === '/tasks/sub-tasks/delete') {
    (new TaskController())->deleteSubTask();
} elseif ($uri === '/tasks/nest') {
    (new TaskController())->nestTask();
} elseif ($uri === '/tasks/reorder') {
    (new TaskController())->reorder();
} elseif (preg_match('#^/tasks/(\d+)/archive$#', $uri, $matches)) {
    (new TaskController())->archive($matches[1]);
} elseif (preg_match('#^/projects/(\d+)/archive-status$#', $uri, $matches)) {
    (new TaskController())->archiveStatus($matches[1]);
} elseif (preg_match('#^/tasks/(\d+)$#', $uri, $matches)) {
    (new TaskController())->show($matches[1]);
} elseif ($uri === '/tasks/details') {
    (new TaskController())->details();
} elseif (preg_match('#^/tasks/(\d+)/edit$#', $uri, $matches)) {
    (new TaskController())->edit($matches[1]);
} elseif (preg_match('#^/tasks/(\d+)/update$#', $uri, $matches)) {
    (new TaskController())->update($matches[1]);
} elseif (preg_match('#^/tasks/(\d+)/delete$#', $uri, $matches)) {
    (new TaskController())->delete($matches[1]);
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
} elseif ($uri === '/admin/archive') {
    (new AdminController())->archiveIndex();
} elseif (preg_match('#^/admin/archive/project/(\d+)$#', $uri, $matches)) {
    (new AdminController())->archiveProject($matches[1]);
} elseif ($uri === '/admin/archive/delete-all') {
    (new AdminController())->archiveDeleteAll();
} elseif (preg_match('#^/admin/archive/tasks/(\d+)/unarchive$#', $uri, $matches)) {
    (new AdminController())->archiveUnarchiveTask($matches[1]);
} elseif (preg_match('#^/admin/archive/tasks/(\d+)/delete$#', $uri, $matches)) {
    (new AdminController())->archiveDeleteTask($matches[1]);
} elseif ($uri === '/jobs') {
    (new JobController())->index();
} elseif ($uri === '/jobs/create-list') {
    (new JobController())->createList();
} elseif ($uri === '/jobs/update-list') {
    (new JobController())->updateList();
} elseif ($uri === '/jobs/delete-list') {
    (new JobController())->deleteList();
} elseif (preg_match('#^/jobs/list/(\d+)$#', $uri, $matches)) {
    (new JobController())->listTasks($matches[1]);
} elseif ($uri === '/jobs/create-task') {
    (new JobController())->createTask();
} elseif ($uri === '/jobs/update-task') {
    (new JobController())->updateTask();
} elseif ($uri === '/jobs/delete-task') {
    (new JobController())->deleteTask();
} elseif ($uri === '/jobs/update-status') {
    (new JobController())->updateTaskStatus();
} elseif ($uri === '/jobs/inbox') {
    (new JobController())->inbox();
} elseif ($uri === '/jobs/mark-completed') {
    (new JobController())->markInboxCompleted();
} elseif ($uri === '/jobs/mark-all-completed') {
    (new JobController())->markInboxAllCompleted();
} elseif ($uri === '/jobs/process-recurring') {
    (new JobController())->processRecurringTasks();
} else {
    http_response_code(404);
    echo "404 Not Found";
}
