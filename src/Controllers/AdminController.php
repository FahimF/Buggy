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

    public function clearLogs() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::connect();
            $db->exec("DELETE FROM logs");
            Logger::log('Logs Cleared', 'Admin cleared all system logs');
            header('Location: /admin/logs');
        }
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
        $db = Database::connect();
        $users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
        $view = 'settings';
        require __DIR__ . '/../Views/admin/layout.php';
    }

    public function testEmail() {
        // Prevent PHP warnings from messing up the JSON response
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

                $input = json_decode(file_get_contents('php://input'), true);
                $email = $input['email'] ?? '';
        
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                    exit;
                }
        
                $smtpHost = $input['smtp_host'] ?? Settings::get('smtp_host');
                $smtpPort = $input['smtp_port'] ?? Settings::get('smtp_port', 587);
                $smtpUser = $input['smtp_user'] ?? Settings::get('smtp_user');
                $smtpPass = $input['smtp_pass'] ?? Settings::get('smtp_pass');
                $smtpEncryption = $input['smtp_encryption'] ?? Settings::get('smtp_encryption', 'tls');
                $smtpFrom = $input['smtp_from'] ?? Settings::get('smtp_from', 'noreply@buggy.local');
        
                if (empty($smtpHost)) {
                    echo json_encode(['success' => false, 'message' => 'SMTP Host is required']);
                    exit;
                }
        
                $subject = 'Test Email from Buggy';
                $message = 'This is a test email from your Buggy application settings. If you received this, email sending is working correctly.';
                $headers = [
                    'From' => "Buggy <$smtpFrom>",
                    'Reply-To' => $smtpFrom,
                    'X-Mailer' => 'Buggy/1.0'
                ];
        
                // Clear any previous output
                if (ob_get_length()) ob_clean();
        
                // Send via SMTP
                try {
                    $smtp = new SMTP(
                        $smtpHost,
                        $smtpPort,
                        $smtpUser,
                        $smtpPass,
                        $smtpEncryption
                    );
                    
                    if ($smtp->send($email, $subject, $message, $headers)) {
                        echo json_encode(['success' => true, 'message' => 'Test email sent successfully via SMTP']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to send email (Unknown error)']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()]);
                }
                exit;
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

    public function createUser() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $password = $_POST['password'];
            $isAdmin = isset($_POST['is_admin']) ? 1 : 0;

            if (empty($username) || empty($password)) {
                die("Username and password are required");
            }

            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                die("Invalid email address");
            }

            // Check if username exists
            $db = Database::connect();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                die("Username already exists");
            }

            Auth::register($username, $password, $isAdmin, $email);
            Logger::log('User Created', "Created user: $username");
            header('Location: /admin/users');
        }
    }

    public function updateUser() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'];
            $username = $_POST['username'];
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $password = $_POST['password'];
            $isAdmin = isset($_POST['is_admin']) ? 1 : 0;

            if (empty($username)) {
                die("Username is required");
            }

            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                die("Invalid email address");
            }

            $db = Database::connect();

            // Check uniqueness if username changed
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                die("Username already exists");
            }

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, is_admin = ? WHERE id = ?");
                $stmt->execute([$username, $email, $hash, $isAdmin, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, is_admin = ? WHERE id = ?");
                $stmt->execute([$username, $email, $isAdmin, $userId]);
            }

            Logger::log('User Updated', "Updated user ID: $userId");
            header('Location: /admin/users');
        }
    }

    public function inbox() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 50; // items per page
        $offset = ($page - 1) * $limit;

        $db = Database::connect();

        // Get inbox items with user and task information
        $sql = "
            SELECT ui.*, u.username, t.title as task_title, t.list_id as task_list_id
            FROM user_inbox ui
            LEFT JOIN users u ON ui.user_id = u.id
            LEFT JOIN tasks t ON ui.task_id = t.id
            ORDER BY ui.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $inboxItems = $stmt->fetchAll();

        // Get total count for pagination
        $countStmt = $db->query("SELECT COUNT(*) as total FROM user_inbox");
        $totalCount = $countStmt->fetch()['total'];
        $totalPages = ceil($totalCount / $limit);

        // Generate pagination HTML
        $pagination = $this->generatePagination($page, $totalPages, '/admin/inbox');

        $view = 'inbox';
        require __DIR__ . '/../Views/admin/layout.php';
    }

    public function deleteSelectedInboxItems() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inbox_ids']) && is_array($_POST['inbox_ids'])) {
            $db = Database::connect();
            $inboxIds = array_map('intval', $_POST['inbox_ids']); // Sanitize IDs

            if (!empty($inboxIds)) {
                $placeholders = str_repeat('?,', count($inboxIds) - 1) . '?';
                $stmt = $db->prepare("DELETE FROM user_inbox WHERE id IN ($placeholders)");
                $stmt->execute($inboxIds);

                Logger::log('Inbox Items Deleted', "Deleted " . count($inboxIds) . " inbox items by admin");
            }

            header('Location: /admin/inbox');
        }
    }

    public function clearReadInboxItems() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::connect();

            // Delete all read inbox items (where is_read = 1)
            $stmt = $db->prepare("DELETE FROM user_inbox WHERE is_read = 1");
            $stmt->execute();

            $deletedCount = $stmt->rowCount();
            Logger::log('Read Inbox Items Cleared', "Cleared $deletedCount read inbox items by admin");

            header('Location: /admin/inbox');
        }
    }

    private function generatePagination($currentPage, $totalPages, $baseUrl) {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav><ul class="pagination justify-content-center">';

        // Previous button
        if ($currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
        }

        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
        }

        // Next button
        if ($currentPage < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }
}
