<?php

class ProfileController {
    public function __construct() {
        Auth::requireLogin();
    }

    public function index() {
        $db = Database::connect();
        $user = Auth::user();
        
        // Fetch fresh user data
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();

        require __DIR__ . '/../Views/profile.php';
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = Auth::user();
            $userId = $user['id'];
            
            $email = !empty($_POST['email']) ? $_POST['email'] : null;
            $timezone = $_POST['timezone'] ?? 'UTC';
            $password = $_POST['password'];

            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                die("Invalid email address");
            }

            $db = Database::connect();

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET email = ?, timezone = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$email, $timezone, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET email = ?, timezone = ? WHERE id = ?");
                $stmt->execute([$email, $timezone, $userId]);
            }

            Logger::log('Profile Updated', "User ID: $userId updated their profile");
            header('Location: /profile?saved=1');
            exit;
        }
    }
}
