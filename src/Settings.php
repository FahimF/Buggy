<?php

class Settings {
    public static function get($key, $default = null) {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }

    public static function set($key, $value) {
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) 
                              ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at");
        $stmt->execute([$key, $value]);
    }
    
    public static function all() {
        $db = Database::connect();
        $stmt = $db->query("SELECT key, value FROM settings");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
