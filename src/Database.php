<?php

class Database {
    private static $pdo;

    public static function connect() {
        if (self::$pdo === null) {
            try {
                $dbPath = __DIR__ . '/../data/buggy.db';
                self::$pdo = new PDO("sqlite:" . $dbPath);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::initialize();
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    private static function initialize() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT,
                password_hash TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                color TEXT DEFAULT '#007bff',
                text_color TEXT DEFAULT '#ffffff',
                owner_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS issues (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                type TEXT DEFAULT 'Bug',
                creator_id INTEGER NOT NULL,
                assigned_to_id INTEGER,
                status TEXT DEFAULT 'Unassigned',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                sort_order INTEGER DEFAULT 0,
                priority TEXT DEFAULT 'Medium',
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (creator_id) REFERENCES users(id),
                FOREIGN KEY (assigned_to_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                issue_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                comment TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_type TEXT NOT NULL, -- 'issue' or 'comment'
                parent_id INTEGER NOT NULL,
                file_path TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS task_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                owner_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                assigned_to_id INTEGER,
                priority TEXT DEFAULT 'Medium',
                is_one_time INTEGER DEFAULT 1,
                recurring_period TEXT, -- daily, weekly, monthly, yearly
                start_date DATETIME,
                status TEXT DEFAULT 'incomplete', -- incomplete, completed, ND, WND
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (list_id) REFERENCES task_lists(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS user_inbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                due_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS user_projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL,
                is_pinned INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (project_id) REFERENCES projects(id),
                UNIQUE(user_id, project_id)
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT UNIQUE NOT NULL,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $query) {
            self::$pdo->exec($query);
        }

        // Migration for existing tables
        try {
            self::$pdo->exec("ALTER TABLE issues ADD COLUMN type TEXT DEFAULT 'Bug'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE projects ADD COLUMN text_color TEXT DEFAULT '#ffffff'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE issues ADD COLUMN priority TEXT DEFAULT 'Medium'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        // Migration for user_projects table
        try {
            self::$pdo->exec("CREATE TABLE user_projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL,
                is_pinned INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (project_id) REFERENCES projects(id),
                UNIQUE(user_id, project_id)
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        // Migrations for task management tables
        try {
            self::$pdo->exec("CREATE TABLE task_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                owner_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        try {
            self::$pdo->exec("CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                assigned_to_id INTEGER,
                priority TEXT DEFAULT 'Medium',
                is_one_time INTEGER DEFAULT 1,
                recurring_period TEXT, -- daily, weekly, monthly, yearly
                start_date DATETIME,
                status TEXT DEFAULT 'incomplete', -- incomplete, completed, ND, WND
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (list_id) REFERENCES task_lists(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to_id) REFERENCES users(id)
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        try {
            self::$pdo->exec("CREATE TABLE user_inbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                due_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        // Migration for due_at column in user_inbox table
        try {
            self::$pdo->exec("ALTER TABLE user_inbox ADD COLUMN due_at DATETIME NULL");
        } catch (PDOException $e) {
            // Column likely already exists
        }
    }
}
