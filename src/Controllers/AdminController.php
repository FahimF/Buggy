<?php

class AdminController {
    public function __construct() {
        Auth::requireLogin();
        if (!Auth::user()['is_admin']) {
            http_response_code(403);
            die("Access Denied");
        }
    }

    public function users() {
        $db = Database::connect();
        $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
        $view = 'users';
        require __DIR__ . '/../Views/admin/layout.php';
    }

    public function logs() {
        $db = Database::connect();
        $logs = $db->query("
            SELECT l.*, u.username 
            FROM logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            ORDER BY l.created_at DESC LIMIT 100
        ")->fetchAll();
        $view = 'logs';
        require __DIR__ . '/../Views/admin/layout.php';
    }

    public function settings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST['settings'] as $key => $value) {
                Settings::set($key, $value);
            }
            Logger::log('Settings Updated', 'User updated application settings');
            header('Location: /admin/settings?saved=1');
            exit;
        }
        $settings = Settings::all();
        $view = 'settings';
        require __DIR__ . '/../Views/admin/layout.php';
    }

    public function toggleAdmin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'];
            // Prevent removing admin from self if it's the last admin (simplified: just don't remove self)
            if ($userId == Auth::user()['id']) {
                // Ideally show error
                header('Location: /admin/users');
                exit;
            }

            $db = Database::connect();
            $stmt = $db->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
            $stmt->execute([$userId]);
            
            Logger::log('User Role Changed', "Toggled admin status for user ID $userId");
            header('Location: /admin/users');
        }
    }
    
    public function deleteUser() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'];
            if ($userId == Auth::user()['id']) return; // Can't delete self

            $db = Database::connect();
            // Simple delete - ON DELETE constraints in DB handles cleanup if defined, 
            // but for safety in SQLite with default FK off, we might need manual cleanup or ensure FKs enabled.
            // In Database.php we didn't explicitly enable FKs for every connection in `connect` except maybe by default. 
            // Better to enable PRAGMA foreign_keys = ON;
            $db->exec("PRAGMA foreign_keys = ON;");
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            Logger::log('User Deleted', "Deleted user ID $userId");
            header('Location: /admin/users');
        }
    }
}
