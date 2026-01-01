<?php

class Auth {
    public static function login($username, $password) {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            return true;
        }
        return false;
    }

    public static function logout() {
        session_destroy();
    }

    public static function user() {
        if (!isset($_SESSION['user_id'])) return null;
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'is_admin' => $_SESSION['is_admin']
        ];
    }

    public static function requireLogin() {
        if (!self::user()) {
            header('Location: /login');
            exit;
        }
    }

    public static function register($username, $password, $isAdmin = 0) {
        $db = Database::connect();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $hash, $isAdmin]);
    }
    
    public static function hasUsers() {
        $db = Database::connect();
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn() > 0;
    }
}
