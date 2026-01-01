<?php

class AuthController {
    public function setup() {
        if (Auth::hasUsers()) {
            header('Location: /login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $password = $_POST['password'];
            if (Auth::register($username, $password, 1)) {
                Auth::login($username, $password);
                header('Location: /');
                exit;
            }
        }
        
        require __DIR__ . '/../Views/setup.php';
    }

    public function login() {
        if (Auth::user()) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $password = $_POST['password'];
            if (Auth::login($username, $password)) {
                Logger::log('Login', "User logged in: $username");
                header('Location: /');
                exit;
            } else {
                Logger::log('Login Failed', "Failed login attempt for: $username");
                $error = "Invalid credentials";
            }
        }

        require __DIR__ . '/../Views/login.php';
    }

    public function logout() {
        Logger::log('Logout', "User logged out: " . (Auth::user()['username'] ?? '?'));
        Auth::logout();
        header('Location: /login');
    }
}
