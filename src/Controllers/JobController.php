<?php

class JobController {
    public function index() {
        Auth::requireLogin();
        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Fetch all job lists owned by the current user
        $sql = "
            SELECT tl.*, u.username as owner_name,
                (SELECT COUNT(*) FROM jobs WHERE list_id = tl.id) as total_jobs,
                (SELECT COUNT(*) FROM jobs WHERE list_id = tl.id AND status = 'incomplete') as incomplete_jobs
            FROM job_lists tl
            JOIN users u ON tl.owner_id = u.id
            WHERE tl.owner_id = ?
            ORDER BY tl.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $jobLists = $stmt->fetchAll();

        require __DIR__ . '/../Views/jobs/index.php';
    }

    public function createList() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'];
            $ownerId = Auth::user()['id'];

            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO job_lists (title, owner_id) VALUES (?, ?)");
            $stmt->execute([$title, $ownerId]);

            Logger::log('Job List Created', "Job List: $title");
            header('Location: /jobs');
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
            $listCheckStmt = $db->prepare("SELECT id FROM job_lists WHERE id = ? AND owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /jobs');
                exit;
            }

            $stmt = $db->prepare("UPDATE job_lists SET title = ? WHERE id = ?");
            $stmt->execute([$title, $id]);

            Logger::log('Job List Updated', "Job List ID: $id");
            header('Location: /jobs');
        }
    }

    public function deleteList() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list
            $listCheckStmt = $db->prepare("SELECT id FROM job_lists WHERE id = ? AND owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /jobs');
                exit;
            }

            $stmt = $db->prepare("DELETE FROM job_lists WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log('Job List Deleted', "Job List ID: $id");
            header('Location: /jobs');
        }
    }

    public function listTasks($listId) {
        Auth::requireLogin();
        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Fetch the job list
        $listSql = "SELECT * FROM job_lists WHERE id = ? AND owner_id = ?";
        $listStmt = $db->prepare($listSql);
        $listStmt->execute([$listId, $currentUserId]);
        $jobList = $listStmt->fetch();

        if (!$jobList) {
            header('Location: /jobs');
            exit;
        }

        // Fetch all jobs in this list
        $sql = "
            SELECT t.*, u.username as assigned_to_name, u.timezone as assignee_timezone
            FROM jobs t
            LEFT JOIN users u ON t.assigned_to_id = u.id
            WHERE t.list_id = ?
            ORDER BY t.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$listId]);
        $jobs = $stmt->fetchAll();

        // Calculate next occurrence for each job
        for ($i = 0; $i < count($jobs); $i++) {
            if ($jobs[$i]['is_one_time'] == 1) {
                $jobs[$i]['next_occurrence'] = 'Now';
            } else {
                $assigneeTimezone = new DateTimeZone($jobs[$i]['assignee_timezone'] ?? 'UTC');
                $now = new DateTime('now', $assigneeTimezone);
                $startDate = new DateTime($jobs[$i]['start_date'] ?? date('Y-m-d H:i:s'), $assigneeTimezone);

                // If start date is in the future, return that date
                if ($startDate > $now) {
                    $jobs[$i]['next_occurrence'] = $startDate->format('M j, Y g:i A');
                } else {
                    // Check the last time this specific job was added to the inbox
                    $lastAddedSql = "
                        SELECT MAX(ui.due_at) as last_added
                        FROM user_inbox ui
                        WHERE ui.job_id = ?
                    ";
                    $lastAddedStmt = $db->prepare($lastAddedSql);
                    $lastAddedStmt->execute([$jobs[$i]['id']]);
                    $lastAddedResult = $lastAddedStmt->fetch();

                    $lastAdded = $lastAddedResult['last_added'] ? new DateTime($lastAddedResult['last_added'], $assigneeTimezone) : $startDate;

                    // Calculate next due date based on recurrence
                    $nextDue = clone $lastAdded;

                    // Loop until we find an occurrence that is in the future relative to now
                    // This handles cases where we want to see the *next* due date if the current one is passed
                    // or the *current* due date if it hasn't passed yet.
                    while ($nextDue <= $now) {
                        $recurringValue = $jobs[$i]['recurring_value'] ?? 1;
                        switch ($jobs[$i]['recurring_period']) {
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

                    $jobs[$i]['next_occurrence'] = $nextDue->format('M j, Y g:i A');
                }
            }
        }

        // Fetch all users for assignment
        $users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

        require __DIR__ . '/../Views/jobs/list.php';
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
            $listCheckStmt = $db->prepare("SELECT id FROM job_lists WHERE id = ? AND owner_id = ?");
            $listCheckStmt->execute([$listId, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /jobs');
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO jobs (list_id, title, description, assigned_to_id, priority, is_one_time, recurring_period, recurring_value, start_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$listId, $title, $description, $assignedToId, $priority, $isOneTime, $recurringPeriod, $recurringValue, $startDate]);

            $jobId = $db->lastInsertId();

            // Add to user's inbox if it's a one-time job or if it's the start date for recurring
            $now = new DateTime();
            $today = (clone $now)->setTime(0, 0, 0);
            $startDay = $startDate ? (clone new DateTime($startDate))->setTime(0, 0, 0) : null;

            if ($isOneTime || (!$isOneTime && $startDay && $startDay <= $today)) {
                // Calculate due_at: null for one-time jobs, next occurrence for recurring jobs
                $dueAt = $isOneTime ? null : $this->calculateNextOccurrence($db, $jobId);
                $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, job_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                $inboxStmt->execute([$assignedToId, $jobId, $dueAt]);
            }

            Logger::log('Job Created', "Job: $title in List ID: $listId");
            header('Location: /jobs/list/' . $listId);
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

            // Verify that the user owns the list that contains this job
            $listCheckStmt = $db->prepare("SELECT tl.id FROM job_lists tl JOIN jobs t ON t.list_id = tl.id WHERE t.id = ? AND tl.owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /jobs');
                exit;
            }

            $stmt = $db->prepare("
                UPDATE jobs
                SET title = ?, description = ?, assigned_to_id = ?, priority = ?,
                    is_one_time = ?, recurring_period = ?, recurring_value = ?, start_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $assignedToId, $priority, $isOneTime, $recurringPeriod, $recurringValue, $startDate, $status, $id]);

            Logger::log('Job Updated', "Job ID: $id");
            header('Location: /jobs/list/' . $listId);
        }
    }

    public function deleteTask() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $listId = $_POST['list_id'];
            $db = Database::connect();
            $currentUserId = (int)Auth::user()['id'];

            // Verify that the user owns the list that contains this job
            $listCheckStmt = $db->prepare("SELECT tl.id FROM job_lists tl JOIN jobs t ON t.list_id = tl.id WHERE t.id = ? AND tl.owner_id = ?");
            $listCheckStmt->execute([$id, $currentUserId]);
            $list = $listCheckStmt->fetch();

            if (!$list) {
                header('Location: /jobs');
                exit;
            }

            // Delete from user inbox as well
            $inboxStmt = $db->prepare("DELETE FROM user_inbox WHERE job_id = ?");
            $inboxStmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log('Job Deleted', "Job ID: $id");
            header('Location: /jobs/list/' . $listId);
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

            // Get job info to verify permissions (allow Owner OR Assignee)
            $stmt = $db->prepare("
                SELECT t.id, t.title, t.assigned_to_id, t.is_one_time, tl.owner_id 
                FROM jobs t 
                JOIN job_lists tl ON t.list_id = tl.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $jobInfo = $stmt->fetch();

            if (!$jobInfo) {
                // Job not found
                header('Location: /jobs');
                exit;
            }

            // Check permissions: Must be List Owner OR Assigned User
            if ($jobInfo['owner_id'] !== $currentUserId && $jobInfo['assigned_to_id'] !== $currentUserId) {
                Logger::log('Unauthorized Job Update', "User $currentUserId attempted to update Job ID: $id without permission.");
                header('Location: /jobs');
                exit;
            }

            // 1. Update Inbox Item Status
            // If we have a specific inbox_id, update only that one
            if ($inboxId) {
                $inboxStmt = $db->prepare("UPDATE user_inbox SET status = ? WHERE id = ? AND user_id = ?");
                $inboxStmt->execute([$status, $inboxId, $currentUserId]);
            } else {
                // Fallback (e.g., from Job List view where specific inbox item might not be known)
                // We update all incomplete inbox items for this job for this user
                // Let's refine: If I am the assignee, update my inbox.
                if ($jobInfo['assigned_to_id'] === $currentUserId) {
                     $inboxStmt = $db->prepare("UPDATE user_inbox SET status = ? WHERE job_id = ? AND user_id = ? AND status = 'incomplete'");
                     $inboxStmt->execute([$status, $id, $currentUserId]);
                }
            }

            // 2. Update Parent Job Status (Only for One-Time Jobs)
            // This affects the global job status.
            if ($jobInfo['is_one_time'] == 1) {
                $stmt = $db->prepare("UPDATE jobs SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$status, $id]);
            }

            Logger::log('Job Status Updated', "Job: '{$jobInfo['title']}' (ID: $id) marked as '$status' by User ID: $currentUserId");

            if ($status === 'incomplete' && $jobInfo['is_one_time'] == 1) {
                // Check if active inbox item exists
                $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM user_inbox WHERE job_id = ? AND user_id = ? AND status = 'incomplete'");
                $checkStmt->execute([$id, $jobInfo['assigned_to_id']]);
                if ($checkStmt->fetch()['count'] == 0) {
                     // Add back to inbox
                     $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, job_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                     $inboxStmt->execute([$jobInfo['assigned_to_id'], $id, null]);
                }
            }

            // Redirect appropriately based on context
            $redirectTo = $_POST['redirect_to'] ?? null;
            if ($redirectTo === 'dashboard') {
                header('Location: /dashboard');
            } elseif ($listId) {
                header('Location: /jobs/list/' . $listId);
            } else {
                header('Location: /dashboard');
            }
        }
    }

    public function inbox() {
        Auth::requireLogin();
        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Fetch jobs in the user's inbox
        $sql = "
            SELECT t.*, tl.title as list_title, u.username as assigned_by_name, ui.due_at, ui.status as inbox_status, ui.id as inbox_id
            FROM user_inbox ui
            JOIN jobs t ON ui.job_id = t.id
            JOIN job_lists tl ON t.list_id = tl.id
            LEFT JOIN users u ON tl.owner_id = u.id
            WHERE ui.user_id = ? AND ui.status = 'incomplete'
            ORDER BY ui.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $inboxJobs = $stmt->fetchAll();

        require __DIR__ . '/../Views/jobs/inbox.php';
    }

    public function markInboxCompleted() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $jobId = $_POST['task_id']; // keeps task_id from post form for compatibility if forms not updated yet, but we will update them
            if (empty($jobId)) {
                $jobId = $_POST['job_id'];
            }
            $currentUserId = (int)Auth::user()['id'];
            
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE user_inbox SET status = 'completed' WHERE user_id = ? AND job_id = ?");
            $stmt->execute([$currentUserId, $jobId]);

            header('Location: /jobs/inbox');
        }
    }

    public function markInboxAllCompleted() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentUserId = (int)Auth::user()['id'];
            
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE user_inbox SET status = 'completed' WHERE user_id = ? AND status = 'incomplete'");
            $stmt->execute([$currentUserId]);

            header('Location: /jobs/inbox');
        }
    }

    public function processRecurringTasks() {
        $db = Database::connect();

        // Get all recurring jobs that should be added to inbox based on their schedule
        $sql = "
            SELECT t.*, tl.owner_id as list_owner_id
            FROM jobs t
            JOIN job_lists tl ON t.list_id = tl.id
            WHERE t.is_one_time = 0
            AND t.start_date IS NOT NULL
            AND t.status = 'incomplete'
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $recurringJobs = $stmt->fetchAll();

        foreach ($recurringJobs as $job) {
            // Determine if we should add this job to the inbox based on recurrence rules
            while ($this->shouldAddRecurringTask($db, $job)) {
                // Calculate due_at for recurring job
                $dueAt = $this->calculateNextOccurrence($db, $job['id']);
                
                if ($dueAt) {
                    // Add to user's inbox
                    $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, job_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                    $inboxStmt->execute([$job['assigned_to_id'], $job['id'], $dueAt]);

                    Logger::log('Recurring Job Generated', "Job: '{$job['title']}' (ID: {$job['id']}) assigned to User ID: {$job['assigned_to_id']}, Due: {$dueAt}");
                } else {
                    break;
                }
            }
        }
    }

    private function shouldAddRecurringTask($db, $job) {
        $now = new DateTime();
        $startDate = new DateTime($job['start_date']);

        // If start date is in the future, don't add yet
        if ($startDate > $now) {
            return false;
        }

        // Check the last time this job was added to the inbox
        $lastAddedSql = "
            SELECT MAX(ui.due_at) as last_added
            FROM user_inbox ui
            JOIN jobs t2 ON ui.job_id = t2.id
            WHERE t2.list_id = ? AND t2.title = ?
        ";
        $lastAddedStmt = $db->prepare($lastAddedSql);
        $lastAddedStmt->execute([$job['list_id'], $job['title']]);
        $lastAddedResult = $lastAddedStmt->fetch();

        $lastAdded = $lastAddedResult['last_added'] ? new DateTime($lastAddedResult['last_added']) : $startDate;

        // Calculate next due date based on recurrence
        $nextDue = clone $lastAdded;
        $recurringValue = $job['recurring_value'] ?? 1;

        switch ($job['recurring_period']) {
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

        return $nextDue <= $now;
    }

    private function calculateNextOccurrence($db, $jobId) {
        // Get job details with assignee timezone
        $taskSql = "
            SELECT t.*, u.timezone as assignee_timezone 
            FROM jobs t 
            LEFT JOIN users u ON t.assigned_to_id = u.id 
            WHERE t.id = ?
        ";
        $taskStmt = $db->prepare($taskSql);
        $taskStmt->execute([$jobId]);
        $job = $taskStmt->fetch();

        if (!$job || $job['is_one_time'] == 1) {
            return null; // One-time jobs have null due_at
        }

        $assigneeTimezone = new DateTimeZone($job['assignee_timezone'] ?? 'UTC');
        $now = new DateTime('now', $assigneeTimezone);
        $startDate = new DateTime($job['start_date'] ?? date('Y-m-d H:i:s'), $assigneeTimezone);

        // If start date is in the future, return that date
        if ($startDate > $now) {
            return $startDate->format('Y-m-d H:i:s');
        }

        // Check the last time this specific job was added to the inbox
        $lastAddedSql = "
            SELECT MAX(ui.due_at) as last_added
            FROM user_inbox ui
            WHERE ui.job_id = ?
        ";
        $lastAddedStmt = $db->prepare($lastAddedSql);
        $lastAddedStmt->execute([$jobId]);
        $lastAddedResult = $lastAddedStmt->fetch();

        if ($lastAddedResult['last_added']) {
            $lastAdded = new DateTime($lastAddedResult['last_added'], $assigneeTimezone);
            $nextDue = clone $lastAdded;
            $recurringValue = $job['recurring_value'] ?? 1;
            
            switch ($job['recurring_period']) {
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