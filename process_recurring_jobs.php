<?php
// Script to process recurring jobs and add them to user inboxes
// This should be run via cron job (e.g., every hour)

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Logger.php';

// Function to process recurring jobs
function processRecurringJobs() {
    $db = Database::connect();

    // 1. Process Recurring Jobs
    // Get all recurring jobs that should be added to inbox based on their schedule
    $sql = "
        SELECT t.*, tl.owner_id as list_owner_id, u.timezone as assignee_timezone
        FROM jobs t
        JOIN job_lists tl ON t.list_id = tl.id
        LEFT JOIN users u ON t.assigned_to_id = u.id
        WHERE t.is_one_time = 0 
        AND t.start_date IS NOT NULL
        AND t.status = 'incomplete'
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $recurringJobs = $stmt->fetchAll();

    foreach ($recurringJobs as $job) {
        // Determine if we should add this job to the inbox based on recurrence rules
        while (shouldAddRecurringJob($db, $job)) {
            // Calculate due_at for recurring job
            $dueAt = calculateNextOccurrenceForJob($db, $job['id']);
            
            if ($dueAt) {
                // Add to user's inbox
                $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, job_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
                $inboxStmt->execute([$job['assigned_to_id'], $job['id'], $dueAt]);

                // Log the action
                Logger::log('Recurring Job Added to Inbox', "Job ID: {$job['id']}, User ID: {$job['assigned_to_id']}, Due: {$dueAt}");
            } else {
                break;
            }
        }
    }

    // 2. Process One-Time Jobs (Restoring missing inbox items)
    // Get incomplete one-time jobs that are not in the assigned user's inbox
    $oneTimeSql = "
        SELECT t.*
        FROM jobs t
        LEFT JOIN user_inbox ui ON t.id = ui.job_id AND ui.user_id = t.assigned_to_id
        WHERE t.is_one_time = 1 
        AND t.status = 'incomplete'
        AND ui.id IS NULL
    ";
    
    $stmt = $db->prepare($oneTimeSql);
    $stmt->execute();
    $oneTimeJobs = $stmt->fetchAll();

    foreach ($oneTimeJobs as $job) {
        // Add to user's inbox
        $inboxStmt = $db->prepare("INSERT INTO user_inbox (user_id, job_id, due_at, status) VALUES (?, ?, ?, 'incomplete')");
        $inboxStmt->execute([$job['assigned_to_id'], $job['id'], null]);

        // Log the action
        Logger::log('Restored One-Time Job to Inbox', "Job ID: {$job['id']}, User ID: {$job['assigned_to_id']}");
    }
}


// Helper function to determine if a recurring job should be added to inbox
function shouldAddRecurringJob($db, $job) {
    $assigneeTimezone = new DateTimeZone($job['assignee_timezone'] ?? 'UTC');
    $now = new DateTime('now', $assigneeTimezone);
    $todayStr = $now->format('Y-m-d');
    
    $startDate = new DateTime($job['start_date'], $assigneeTimezone);
    $startDayStr = $startDate->format('Y-m-d');
    
    // If start date is in the future day, don't add yet
    if ($startDayStr > $todayStr) {
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
                return false;
        }
        return $nextDue->format('Y-m-d') <= $todayStr;
    } else {
        return true;
    }
}

// Helper function to calculate next occurrence for a specific job
function calculateNextOccurrenceForJob($db, $jobId) {
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

// Run the recurring job processor
processRecurringJobs();