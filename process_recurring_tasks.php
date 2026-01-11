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
        SELECT t.*, tl.owner_id as list_owner_id, u.timezone as assignee_timezone
        FROM tasks t
        JOIN task_lists tl ON t.list_id = tl.id
        LEFT JOIN users u ON t.assigned_to_id = u.id
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
        while (shouldAddRecurringTask($db, $task)) {
            // Check if this exact task instance was already added to inbox today
            // Note: When backfilling, we might be adding tasks for previous days,
            // so checking against DATE(NOW) might prevent valid backfilling if we strictly enforced it.
            // However, calculateNextOccurrenceForTask relies on the DB state (MAX due_at).
            // So we rely on that to ensure we don't add duplicates for the same due date.
            
            // Calculate due_at for recurring task
            $dueAt = calculateNextOccurrenceForTask($db, $task['id']);
            
            if ($dueAt) {
                // Add to user's inbox
                $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                $inboxStmt->execute([$task['assigned_to_id'], $task['id'], $dueAt]);

                // Log the action
                Logger::log('Recurring Task Added to Inbox', "Task ID: {$task['id']}, User ID: {$task['assigned_to_id']}, Due: {$dueAt}");
            } else {
                // Should not happen if shouldAddRecurringTask is true, but break safety
                break;
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
        $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
        // due_at is NULL for one-time tasks
        $inboxStmt->execute([$task['assigned_to_id'], $task['id'], null]);

        // Log the action
        Logger::log('Restored One-Time Task to Inbox', "Task ID: {$task['id']}, User ID: {$task['assigned_to_id']}");
    }
}


// Helper function to determine if a recurring task should be added to inbox
function shouldAddRecurringTask($db, $task) {
    $assigneeTimezone = new DateTimeZone($task['assignee_timezone'] ?? 'UTC');
    $now = new DateTime('now', $assigneeTimezone);
    $todayStr = $now->format('Y-m-d');
    
    $startDate = new DateTime($task['start_date'], $assigneeTimezone);
    $startDayStr = $startDate->format('Y-m-d');
    
    // If start date is in the future day, don't add yet
    if ($startDayStr > $todayStr) {
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

// Run the recurring task processor
processRecurringTasks();