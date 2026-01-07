<?php
// Script to process recurring tasks and add them to user inboxes
// This should be run via cron job (e.g., every hour)

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Logger.php';

// Function to process recurring tasks
function processRecurringTasks() {
    $db = Database::connect();
    
    // 1. Process Recurring Tasks
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
        $shouldAdd = shouldAddRecurringTask($db, $task);
        
        if ($shouldAdd) {
            // Check if this exact task instance was already added to inbox today
            $checkSql = "
                SELECT COUNT(*) as count
                FROM user_inbox ui
                JOIN tasks t2 ON ui.task_id = t2.id
                WHERE t2.list_id = ? AND t2.title = ? AND DATE(ui.created_at) = DATE(?)
            ";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([$task['list_id'], $task['title'], date('Y-m-d H:i:s')]);
            $result = $checkStmt->fetch();
            
            if ($result['count'] == 0) {
                // Calculate due_at for recurring task
                $dueAt = calculateNextOccurrenceForTask($db, $task['id']);
                // Add to user's inbox
                $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at) VALUES (?, ?, ?)");
                $inboxStmt->execute([$task['assigned_to_id'], $task['id'], $dueAt]);

                // Log the action
                Logger::log('Recurring Task Added to Inbox', "Task ID: {$task['id']}, User ID: {$task['assigned_to_id']}");
            }
        }
    }

    // 2. Process One-Time Tasks (Restoring missing inbox items)
    // Get incomplete one-time tasks that are not in the assigned user's inbox
    $oneTimeSql = "
        SELECT t.*
        FROM tasks t
        LEFT JOIN user_inbox ui ON t.id = ui.task_id AND ui.user_id = t.assigned_to_id
        WHERE t.is_one_time = 1 
        AND t.status = 'incomplete'
        AND ui.id IS NULL
    ";
    
    $stmt = $db->prepare($oneTimeSql);
    $stmt->execute();
    $oneTimeTasks = $stmt->fetchAll();

    foreach ($oneTimeTasks as $task) {
        // Add to user's inbox
        $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at) VALUES (?, ?, ?)");
        // due_at is NULL for one-time tasks
        $inboxStmt->execute([$task['assigned_to_id'], $task['id'], null]);

        // Log the action
        Logger::log('Restored One-Time Task to Inbox', "Task ID: {$task['id']}, User ID: {$task['assigned_to_id']}");
    }
}

// Helper to get system timezone
function getSystemTimezone() {
    try {
        $offset = trim(shell_exec("date +%z"));
        if ($offset) {
            return new DateTimeZone($offset);
        }
    } catch (Exception $e) {
        // Fallback to default
    }
    return null;
}

// Helper function to determine if a recurring task should be added to inbox
function shouldAddRecurringTask($db, $task) {
    $tz = getSystemTimezone();
    $now = $tz ? new DateTime("now", $tz) : new DateTime();
    $todayStr = $now->format('Y-m-d');
    
    $startDate = new DateTime($task['start_date']);
    $startDayStr = $startDate->format('Y-m-d');
    
    // If start date is in the future day, don't add yet
    if ($startDayStr > $todayStr) {
        return false;
    }
    
    // Check the last time this task was added to the inbox
    $lastAddedSql = "
        SELECT MAX(ui.created_at) as last_added
        FROM user_inbox ui
        JOIN tasks t2 ON ui.task_id = t2.id
        WHERE t2.list_id = ? AND t2.title = ?
    ";
    $lastAddedStmt = $db->prepare($lastAddedSql);
    $lastAddedStmt->execute([$task['list_id'], $task['title']]);
    $lastAddedResult = $lastAddedStmt->fetch();
    
    if ($lastAddedResult['last_added']) {
        $lastAdded = new DateTime($lastAddedResult['last_added']);
        // If we have a system timezone, assume the DB timestamps were stored in it or convert?
        // Actually, let's keep it simple: Compare Y-m-d strings.
        // We need to calculate the *next due date* and compare that string to today's string.
        
        $nextDue = clone $lastAdded;
        
        switch ($task['recurring_period']) {
            case 'daily':
                $nextDue->modify('+1 day');
                break;
            case 'weekly':
                $nextDue->modify('+1 week');
                break;
            case 'monthly':
                $nextDue->modify('+1 month');
                break;
            case 'yearly':
                $nextDue->modify('+1 year');
                break;
            default:
                return false;
        }
        return $nextDue->format('Y-m-d') <= $todayStr;
    } else {
        // No previous occurrence, and we already checked startDay <= today
        return true;
    }
}

// Helper function to calculate next occurrence for a specific task
function calculateNextOccurrenceForTask($db, $taskId) {
    // Get task details
    $taskSql = "SELECT * FROM tasks WHERE id = ?";
    $taskStmt = $db->prepare($taskSql);
    $taskStmt->execute([$taskId]);
    $task = $taskStmt->fetch();

    if (!$task || $task['is_one_time'] == 1) {
        return null; // One-time tasks have null due_at
    }

    $now = new DateTime();
    $startDate = new DateTime($task['start_date'] ?? date('Y-m-d H:i:s'));

    // If start date is in the future, return that date
    if ($startDate > $now) {
        return $startDate->format('Y-m-d H:i:s');
    }

    // Check the last time this specific task was added to the inbox
    $lastAddedSql = "
        SELECT MAX(ui.created_at) as last_added
        FROM user_inbox ui
        WHERE ui.task_id = ?
    ";
    $lastAddedStmt = $db->prepare($lastAddedSql);
    $lastAddedStmt->execute([$taskId]);
    $lastAddedResult = $lastAddedStmt->fetch();

    if ($lastAddedResult['last_added']) {
        $lastAdded = new DateTime($lastAddedResult['last_added']);
        $nextDue = clone $lastAdded;

        switch ($task['recurring_period']) {
            case 'daily':
                $nextDue->modify('+1 day');
                break;
            case 'weekly':
                $nextDue->modify('+1 week');
                break;
            case 'monthly':
                $nextDue->modify('+1 month');
                break;
            case 'yearly':
                $nextDue->modify('+1 year');
                break;
            default:
                return null;
        }
    } else {
        $nextDue = $startDate;
    }

    return $nextDue->format('Y-m-d H:i:s');
}

// Run the recurring task processor
processRecurringTasks();