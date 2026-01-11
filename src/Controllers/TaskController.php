<?php

class TaskController {
    public function index() {
        Auth::requireLogin();
        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Fetch all task lists owned by the current user
        $sql = "
            SELECT tl.*, u.username as owner_name,
                (SELECT COUNT(*) FROM tasks WHERE list_id = tl.id) as total_tasks,
                (SELECT COUNT(*) FROM tasks WHERE list_id = tl.id AND status = 'incomplete') as incomplete_tasks
            FROM task_lists tl
            JOIN users u ON tl.owner_id = u.id
            WHERE tl.owner_id = ?
            ORDER BY tl.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $taskLists = $stmt->fetchAll();

        require __DIR__ . '/../Views/tasks/index.php';
    }

    public function createList() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'];
            $ownerId = Auth::user()['id'];

            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO task_lists (title, owner_id) VALUES (?, ?)");
            $stmt->execute([$title, $ownerId]);

            Logger::log('Task List Created', "Task List: $title");
            header('Location: /tasks');
        }
    }

    public function updateList() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $title = $_POST['title'];

            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list
            $listCheckStmt = $db->prepare("SELECT id FROM task_lists WHERE id = ? AND owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /tasks');
                exit;
            }

            $stmt = $db->prepare("UPDATE task_lists SET title = ? WHERE id = ?");
            $stmt->execute([$title, $id]);

            Logger::log('Task List Updated', "Task List ID: $id");
            header('Location: /tasks');
        }
    }

    public function deleteList() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list
            $listCheckStmt = $db->prepare("SELECT id FROM task_lists WHERE id = ? AND owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /tasks');
                exit;
            }

            $stmt = $db->prepare("DELETE FROM task_lists WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log('Task List Deleted', "Task List ID: $id");
            header('Location: /tasks');
        }
    }

    public function listTasks($listId) {
        Auth::requireLogin();
        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Fetch the task list
        $listSql = "SELECT * FROM task_lists WHERE id = ? AND owner_id = ?";
        $listStmt = $db->prepare($listSql);
        $listStmt->execute([$listId, $currentUserId]);
        $taskList = $listStmt->fetch();

        if (!$taskList) {
            header('Location: /tasks');
            exit;
        }

        // Fetch all tasks in this list
        $sql = "
            SELECT t.*, u.username as assigned_to_name, u.timezone as assignee_timezone
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to_id = u.id
            WHERE t.list_id = ?
            ORDER BY t.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$listId]);
        $tasks = $stmt->fetchAll();

        // Calculate next occurrence for each task
        for ($i = 0; $i < count($tasks); $i++) {
            if ($tasks[$i]['is_one_time'] == 1) {
                $tasks[$i]['next_occurrence'] = 'Now';
            } else {
                $assigneeTimezone = new DateTimeZone($tasks[$i]['assignee_timezone'] ?? 'UTC');
                $now = new DateTime('now', $assigneeTimezone);
                $startDate = new DateTime($tasks[$i]['start_date'] ?? date('Y-m-d H:i:s'), $assigneeTimezone);

                // If start date is in the future, return that date
                if ($startDate > $now) {
                    $tasks[$i]['next_occurrence'] = $startDate->format('M j, Y g:i A');
                } else {
                    // Check the last time this specific task was added to the inbox
                    $lastAddedSql = "
                        SELECT MAX(ui.due_at) as last_added
                        FROM user_inbox ui
                        WHERE ui.task_id = ?
                    ";
                    $lastAddedStmt = $db->prepare($lastAddedSql);
                    $lastAddedStmt->execute([$tasks[$i]['id']]);
                    $lastAddedResult = $lastAddedStmt->fetch();

                    $lastAdded = $lastAddedResult['last_added'] ? new DateTime($lastAddedResult['last_added'], $assigneeTimezone) : $startDate;

                    // Calculate next due date based on recurrence
                    $nextDue = clone $lastAdded;

                    // Loop until we find an occurrence that is in the future relative to now
                    // This handles cases where we want to see the *next* due date if the current one is passed
                    // or the *current* due date if it hasn't passed yet.
                    while ($nextDue <= $now) {
                        $recurringValue = $tasks[$i]['recurring_value'] ?? 1;
                        switch ($tasks[$i]['recurring_period']) {
                            case 'daily':
                                $nextDue->modify('+' . $recurringValue . ' day');
                                break;
                            case 'weekly':
                                $nextDue->modify('+' . $recurringValue . ' week');
                                break;
                            case 'monthly':
                                $nextDue->modify('+' . $recurringValue . ' month');
                                break;
                            case 'yearly':
                                $nextDue->modify('+' . $recurringValue . ' year');
                                break;
                            default:
                                break 2; // Break out of switch and while
                        }
                    }

                    $tasks[$i]['next_occurrence'] = $nextDue->format('M j, Y g:i A');
                }
            }
        }

        // Fetch all users for assignment
        $users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

        require __DIR__ . '/../Views/tasks/list.php';
    }

    public function createTask() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $listId = $_POST['list_id'];
            $title = $_POST['title'];
            $description = $_POST['description'] ?? '';
            $assignedToId = $_POST['assigned_to_id'] ?? Auth::user()['id'];
            $priority = $_POST['priority'] ?? 'Medium';
            $isOneTime = isset($_POST['is_one_time']) ? 1 : 0;
            $recurringPeriod = $_POST['recurring_period'] ?? null;
            $recurringValue = $_POST['recurring_value'] ?? 1;
            $startDate = $_POST['start_date'] ?? null;

            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list
            $listCheckStmt = $db->prepare("SELECT id FROM task_lists WHERE id = ? AND owner_id = ?");
            $listCheckStmt->execute([$listId, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /tasks');
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO tasks (list_id, title, description, assigned_to_id, priority, is_one_time, recurring_period, recurring_value, start_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$listId, $title, $description, $assignedToId, $priority, $isOneTime, $recurringPeriod, $recurringValue, $startDate]);

            $taskId = $db->lastInsertId();

            // Add to user's inbox if it's a one-time task or if it's the start date for recurring
            $now = new DateTime();
            $today = (clone $now)->setTime(0, 0, 0);
            $startDay = $startDate ? (clone new DateTime($startDate))->setTime(0, 0, 0) : null;

            if ($isOneTime || (!$isOneTime && $startDay && $startDay <= $today)) {
                // Calculate due_at: null for one-time tasks, next occurrence for recurring tasks
                $dueAt = $isOneTime ? null : $this->calculateNextOccurrence($db, $taskId);
                $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                $inboxStmt->execute([$assignedToId, $taskId, $dueAt]);
            }

            Logger::log('Task Created', "Task: $title in List ID: $listId");
            header('Location: /tasks/list/' . $listId);
        }
    }

    public function updateTask() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $listId = $_POST['list_id'];
            $title = $_POST['title'];
            $description = $_POST['description'] ?? '';
            $assignedToId = $_POST['assigned_to_id'] ?? Auth::user()['id'];
            $priority = $_POST['priority'] ?? 'Medium';
            $isOneTime = isset($_POST['is_one_time']) ? 1 : 0;
            $recurringPeriod = $_POST['recurring_period'] ?? null;
            $recurringValue = $_POST['recurring_value'] ?? 1;
            $startDate = $_POST['start_date'] ?? null;
            $status = $_POST['status'] ?? 'incomplete';

            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list that contains this task
            $listCheckStmt = $db->prepare("SELECT tl.id FROM task_lists tl JOIN tasks t ON t.list_id = tl.id WHERE t.id = ? AND tl.owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /tasks');
                exit;
            }

            $stmt = $db->prepare("
                UPDATE tasks
                SET title = ?, description = ?, assigned_to_id = ?, priority = ?,
                    is_one_time = ?, recurring_period = ?, recurring_value = ?, start_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $assignedToId, $priority, $isOneTime, $recurringPeriod, $recurringValue, $startDate, $status, $id]);

            Logger::log('Task Updated', "Task ID: $id");
            header('Location: /tasks/list/' . $listId);
        }
    }

    public function deleteTask() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $listId = $_POST['list_id'];
            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list that contains this task
            $listCheckStmt = $db->prepare("SELECT tl.id FROM task_lists tl JOIN tasks t ON t.list_id = tl.id WHERE t.id = ? AND tl.owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /tasks');
                exit;
            }

            // Delete from user inbox as well
            $inboxStmt = $db->prepare("DELETE FROM user_inbox WHERE task_id = ?");
            $inboxStmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log('Task Deleted', "Task ID: $id");
            header('Location: /tasks/list/' . $listId);
        }
    }

    public function updateTaskStatus() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $status = $_POST['status'];
            $listId = $_POST['list_id'] ?? null;
            $inboxId = $_POST['inbox_id'] ?? null;

            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list that contains this task
            $listCheckStmt = $db->prepare("SELECT tl.id, t.assigned_to_id, t.is_one_time FROM task_lists tl JOIN tasks t ON t.list_id = tl.id WHERE t.id = ? AND tl.owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $taskInfo = $listCheckStmt->fetch();

            if (!$taskInfo) {
                header('Location: /tasks');
                exit;
            }

            // 1. Update Inbox Item Status
            // If we have a specific inbox_id, update only that one
            if ($inboxId) {
                $inboxStmt = $db->prepare("UPDATE user_inbox SET status = ? WHERE id = ? AND user_id = ?");
                $inboxStmt->execute([$status, $inboxId, $currentUserId]);
            } else {
                // Fallback (e.g., from Task List view where specific inbox item might not be known)
                // We update all incomplete inbox items for this task for this user
                $inboxStmt = $db->prepare("UPDATE user_inbox SET status = ? WHERE task_id = ? AND user_id = ? AND status = 'incomplete'");
                $inboxStmt->execute([$status, $id, $currentUserId]);
            }

            // 2. Update Parent Task Status (Only for One-Time Tasks)
            if ($taskInfo['is_one_time'] == 1) {
                $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$status, $id]);
            }

            Logger::log('Task Status Updated', "Task ID: $id, Status: $status");

            // If the task is being marked as incomplete from a completed/ND/WND state,
            // and it's a one-time task, we might need to reactivate it.
            // But since we are now driving from inbox status, if we reactivate a one-time task via this method,
            // we should ensure an inbox item exists.
            
            if ($status === 'incomplete' && $taskInfo['is_one_time'] == 1) {
                // Check if active inbox item exists
                $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM user_inbox WHERE task_id = ? AND user_id = ? AND status = 'incomplete'");
                $checkStmt->execute([$id, $taskInfo['assigned_to_id']]);
                if ($checkStmt->fetch()['count'] == 0) {
                     // Add back to inbox
                     $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                     $inboxStmt->execute([$taskInfo['assigned_to_id'], $id, null]);
                }
            }

            // Redirect appropriately based on context
            if ($listId) {
                header('Location: /tasks/list/' . $listId);
            } else {
                header('Location: /dashboard');
            }
        }
    }

    public function inbox() {
        Auth::requireLogin();
        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Fetch tasks in the user's inbox
        $sql = "
            SELECT t.*, tl.title as list_title, u.username as assigned_by_name, ui.due_at, ui.status as inbox_status, ui.id as inbox_id
            FROM user_inbox ui
            JOIN tasks t ON ui.task_id = t.id
            JOIN task_lists tl ON t.list_id = tl.id
            LEFT JOIN users u ON tl.owner_id = u.id
            WHERE ui.user_id = ? AND ui.status = 'incomplete'
            ORDER BY ui.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $inboxTasks = $stmt->fetchAll();

        require __DIR__ . '/../Views/tasks/inbox.php';
    }

    public function markInboxCompleted() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $taskId = $_POST['task_id'];
            $currentUserId = (int)Auth::user()['id'];
            
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE user_inbox SET status = 'completed' WHERE user_id = ? AND task_id = ?");
            $stmt->execute([$currentUserId, $taskId]);

            header('Location: /tasks/inbox');
        }
    }

    public function markInboxAllCompleted() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentUserId = (int)Auth::user()['id'];
            
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE user_inbox SET status = 'completed' WHERE user_id = ? AND status = 'incomplete'");
            $stmt->execute([$currentUserId]);

            header('Location: /tasks/inbox');
        }
    }

    public function processRecurringTasks() {
        // This method would be called by a cron job or scheduled task
        $db = Database::connect();

        // Get all recurring tasks that should be added to inbox based on their schedule
        $sql = "
            SELECT t.*, tl.owner_id as list_owner_id
            FROM tasks t
            JOIN task_lists tl ON t.list_id = tl.id
            WHERE t.is_one_time = 0
            AND t.start_date IS NOT NULL
            AND t.status = 'incomplete'
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $recurringTasks = $stmt->fetchAll();

        foreach ($recurringTasks as $task) {
            // Determine if we should add this task to the inbox based on recurrence rules
            // Use a loop to backfill multiple missing occurrences if necessary
            while ($this->shouldAddRecurringTask($db, $task)) {
                // Calculate due_at for recurring task
                $dueAt = $this->calculateNextOccurrence($db, $task['id']);
                
                if ($dueAt) {
                    // Add to user's inbox
                    $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                    $inboxStmt->execute([$task['assigned_to_id'], $task['id'], $dueAt]);

                    Logger::log('Recurring Task Added to Inbox', "Task ID: {$task['id']}, User ID: {$task['assigned_to_id']}, Due: {$dueAt}");
                } else {
                    break;
                }
            }
        }
    }

    private function shouldAddRecurringTask($db, $task) {
        $now = new DateTime();
        $startDate = new DateTime($task['start_date']);

        // If start date is in the future, don't add yet
        if ($startDate > $now) {
            return false;
        }

        // Check the last time this task was added to the inbox
        $lastAddedSql = "
            SELECT MAX(ui.due_at) as last_added
            FROM user_inbox ui
            JOIN tasks t2 ON ui.task_id = t2.id
            WHERE t2.list_id = ? AND t2.title = ?
        ";
        $lastAddedStmt = $db->prepare($lastAddedSql);
        $lastAddedStmt->execute([$task['list_id'], $task['title']]);
        $lastAddedResult = $lastAddedStmt->fetch();

        $lastAdded = $lastAddedResult['last_added'] ? new DateTime($lastAddedResult['last_added']) : $startDate;

        // Calculate next due date based on recurrence
        $nextDue = clone $lastAdded;
        $recurringValue = $task['recurring_value'] ?? 1;

        switch ($task['recurring_period']) {
            case 'daily':
                $nextDue->modify('+' . $recurringValue . ' day');
                break;
            case 'weekly':
                $nextDue->modify('+' . $recurringValue . ' week');
                break;
            case 'monthly':
                $nextDue->modify('+' . $recurringValue . ' month');
                break;
            case 'yearly':
                $nextDue->modify('+' . $recurringValue . ' year');
                break;
            default:
                return false;
        }

        // Return true if it's time to add the task again
        return $nextDue <= $now;
    }

    private function calculateNextOccurrence($db, $taskId) {
        // Get task details with assignee timezone
        $taskSql = "
            SELECT t.*, u.timezone as assignee_timezone 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to_id = u.id 
            WHERE t.id = ?
        ";
        $taskStmt = $db->prepare($taskSql);
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch();

        if (!$task || $task['is_one_time'] == 1) {
            return null; // One-time tasks have null due_at
        }

        $assigneeTimezone = new DateTimeZone($task['assignee_timezone'] ?? 'UTC');
        $now = new DateTime('now', $assigneeTimezone);
        $startDate = new DateTime($task['start_date'] ?? date('Y-m-d H:i:s'), $assigneeTimezone);

        // If start date is in the future, return that date
        if ($startDate > $now) {
            return $startDate->format('Y-m-d H:i:s');
        }

        // Check the last time this specific task was added to the inbox
        $lastAddedSql = "
            SELECT MAX(ui.due_at) as last_added
            FROM user_inbox ui
            WHERE ui.task_id = ?
        ";
        $lastAddedStmt = $db->prepare($lastAddedSql);
        $lastAddedStmt->execute([$taskId]);
        $lastAddedResult = $lastAddedStmt->fetch();

        if ($lastAddedResult['last_added']) {
            $lastAdded = new DateTime($lastAddedResult['last_added'], $assigneeTimezone);
            $nextDue = clone $lastAdded;
            $recurringValue = $task['recurring_value'] ?? 1;
            
            switch ($task['recurring_period']) {
                case 'daily':
                    $nextDue->modify('+' . $recurringValue . ' day');
                    break;
                case 'weekly':
                    $nextDue->modify('+' . $recurringValue . ' week');
                    break;
                case 'monthly':
                    $nextDue->modify('+' . $recurringValue . ' month');
                    break;
                case 'yearly':
                    $nextDue->modify('+' . $recurringValue . ' year');
                    break;
                default:
                    return null;
            }
        } else {
            $nextDue = $startDate;
        }

        return $nextDue->format('Y-m-d H:i:s');
    }
}