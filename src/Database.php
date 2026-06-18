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
        // Run migrations for renames
        try {
            $checkIssues = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='issues'")->fetch();
            if ($checkIssues) {
                try { self::$pdo->exec("ALTER TABLE task_lists RENAME TO job_lists"); } catch (PDOException $e) {}
                try { self::$pdo->exec("ALTER TABLE tasks RENAME TO jobs"); } catch (PDOException $e) {}
                try { self::$pdo->exec("ALTER TABLE user_inbox RENAME COLUMN task_id TO job_id"); } catch (PDOException $e) {}
                try { self::$pdo->exec("ALTER TABLE issues RENAME TO tasks"); } catch (PDOException $e) {}
                try { self::$pdo->exec("ALTER TABLE issue_sub_tasks RENAME TO sub_tasks"); } catch (PDOException $e) {}
            }
        } catch (PDOException $e) {}

        // Separate column renames to run unconditionally in case a partial migration occurred
        try {
            self::$pdo->exec("ALTER TABLE sub_tasks RENAME COLUMN issue_id TO task_id");
        } catch (PDOException $e) {}
        try {
            self::$pdo->exec("ALTER TABLE comments RENAME COLUMN issue_id TO task_id");
        } catch (PDOException $e) {}
        try {
            self::$pdo->exec("UPDATE attachments SET parent_type = 'task' WHERE parent_type = 'issue'");
        } catch (PDOException $e) {}

        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT,
                password_hash TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                timezone TEXT DEFAULT 'UTC',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                color TEXT DEFAULT '#007bff',
                text_color TEXT DEFAULT '#ffffff',
                complete_moves_to TEXT DEFAULT 'Completed',
                owner_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS tasks (
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
                is_archived INTEGER DEFAULT 0,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (creator_id) REFERENCES users(id),
                FOREIGN KEY (assigned_to_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                comment TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_type TEXT NOT NULL, -- 'task' or 'comment'
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
            "CREATE TABLE IF NOT EXISTS job_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                owner_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                assigned_to_id INTEGER,
                priority TEXT DEFAULT 'Medium',
                is_one_time INTEGER DEFAULT 1,
                recurring_period TEXT, -- daily, weekly, monthly, yearly
                recurring_value INTEGER DEFAULT 1,
                start_date DATETIME,
                status TEXT DEFAULT 'incomplete', -- incomplete, completed, ND, WND
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (list_id) REFERENCES job_lists(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS user_inbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                job_id INTEGER NOT NULL,
                is_read INTEGER DEFAULT 0,
                status TEXT DEFAULT 'incomplete',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                due_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
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
            )",
            "CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                access INTEGER,
                data TEXT
            )",
            "CREATE TABLE IF NOT EXISTS sub_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                is_completed INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            )"
        ];

        foreach ($queries as $query) {
            self::$pdo->exec($query);
        }

        // Migration for existing tables
        try {
            self::$pdo->exec("ALTER TABLE projects ADD COLUMN complete_moves_to TEXT DEFAULT 'Completed'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE tasks ADD COLUMN type TEXT DEFAULT 'Bug'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE projects ADD COLUMN text_color TEXT DEFAULT '#ffffff'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE tasks ADD COLUMN priority TEXT DEFAULT 'Medium'");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        try {
            self::$pdo->exec("ALTER TABLE users ADD COLUMN timezone TEXT DEFAULT 'UTC'");
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

        // Migrations for job management tables
        try {
            self::$pdo->exec("CREATE TABLE job_lists (
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
            self::$pdo->exec("CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                assigned_to_id INTEGER,
                priority TEXT DEFAULT 'Medium',
                is_one_time INTEGER DEFAULT 1,
                recurring_period TEXT, -- daily, weekly, monthly, yearly
                recurring_value INTEGER DEFAULT 1,
                start_date DATETIME,
                status TEXT DEFAULT 'incomplete', -- incomplete, completed, ND, WND
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (list_id) REFERENCES job_lists(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to_id) REFERENCES users(id)
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        try {
            self::$pdo->exec("CREATE TABLE user_inbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                job_id INTEGER NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                due_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
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

        // Migration for status column in user_inbox table
        try {
            self::$pdo->exec("ALTER TABLE user_inbox ADD COLUMN status TEXT DEFAULT 'incomplete'");
            
            // Set status to 'completed' for read items as a baseline
            self::$pdo->exec("UPDATE user_inbox SET status = 'completed' WHERE is_read = 1");
            
            // Sync one-time jobs: If the parent job has a specific status (ND, WND), reflect that in the inbox item
            $sql = "
                UPDATE user_inbox 
                SET status = (SELECT status FROM jobs WHERE jobs.id = user_inbox.job_id)
                WHERE job_id IN (SELECT id FROM jobs WHERE is_one_time = 1)
                AND is_read = 1
            ";
            self::$pdo->exec($sql);
        } catch (PDOException $e) {
            // Column likely already exists
        }

        // Migration for recurring_value column in jobs table
        try {
            self::$pdo->exec("ALTER TABLE jobs ADD COLUMN recurring_value INTEGER DEFAULT 1");
        } catch (PDOException $e) {
            // Column likely already exists
        }

        // Migration for sessions table
        try {
            self::$pdo->exec("CREATE TABLE sessions (
                id TEXT PRIMARY KEY,
                access INTEGER,
                data TEXT
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        // Migration for sub_tasks table
        try {
            self::$pdo->exec("CREATE TABLE IF NOT EXISTS sub_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                is_completed INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            )");
        } catch (PDOException $e) {
            // Table likely already exists
        }

        // Migration for is_archived column in tasks table
        try {
            self::$pdo->exec("ALTER TABLE tasks ADD COLUMN is_archived INTEGER DEFAULT 0");
        } catch (PDOException $e) {
            // Column likely already exists
        }
    }
}
