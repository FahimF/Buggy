<?php

class Logger {
    public static function log($action, $details = null) {
        $userId = Auth::user() ? Auth::user()['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    }
}
