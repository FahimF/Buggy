<?php
// Script to process recurring tasks and add them to user inboxes
// This should be run via cron job (e.g., every hour)

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Logger.php';

// Function to process recurring tasks
function processRecurringTasks() {
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
                // Add to user's inbox
                $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, task_id) VALUES (?, ?)");
                $inboxStmt->execute([$task['assigned_to_id'], $task['id']]);
                
                // Log the action
                Logger::log('Recurring Task Added to Inbox', "Task ID: {$task['id']}, User ID: {$task['assigned_to_id']}");
            }
        }
    }
}

// Helper function to determine if a recurring task should be added to inbox
function shouldAddRecurringTask($db, $task) {
    $now = new DateTime();
    $startDate = new DateTime($task['start_date']);
    
    // If start date is in the future, don't add yet
    if ($startDate > $now) {
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
    
    $lastAdded = $lastAddedResult['last_added'] ? new DateTime($lastAddedResult['last_added']) : $startDate;
    
    // Calculate next due date based on recurrence
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
    
    // Return true if it's time to add the task again
    return $nextDue <= $now;
}

// Run the recurring task processor
processRecurringTasks();