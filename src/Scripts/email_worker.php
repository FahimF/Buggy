<?php

// Bootstrap
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/../Logger.php';
require_once __DIR__ . '/../SMTP.php';

if ($argc < 2) {
    exit(1);
}

$payloadFile = $argv[1];
if (!file_exists($payloadFile)) {
    Logger::log('Email Worker Error', "Payload file not found: $payloadFile");
    exit(1);
}

$payload = json_decode(file_get_contents($payloadFile), true);
unlink($payloadFile);

if (!$payload) {
    Logger::log('Email Worker Error', "Invalid payload JSON");
    exit(1);
}

$type = $payload['type'];
$host = $payload['host'] ?? 'localhost';
$data = $payload['data'];

try {
    $db = Database::connect();
    
    if ($type === 'comment') {
        processCommentNotification($db, $host, $data);
    } elseif ($type === 'assign') {
        processAssignmentNotification($db, $host, $data);
    }
} catch (Exception $e) {
    Logger::log('Email Worker Exception', $e->getMessage());
}

function processCommentNotification($db, $host, $data) {
    $issueId = $data['issue_id'];
    $comment = $data['comment'];
    $currentUserId = $data['user_id'];

    // Get Issue Details
    $stmt = $db->prepare("SELECT i.title, i.creator_id, p.name as project_name FROM issues i JOIN projects p ON i.project_id = p.id WHERE i.id = ?");
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch();
    if (!$issue) return;

    $recipients = [];

    // Add Creator (if not current user)
    if ($issue['creator_id'] != $currentUserId) {
        $recipients[$issue['creator_id']] = true;
    }

    // Add Previous Commenters
    $stmt = $db->prepare("SELECT DISTINCT user_id FROM comments WHERE issue_id = ?");
    $stmt->execute([$issueId]);
    while ($row = $stmt->fetch()) {
        if ((int)$row['user_id'] !== (int)$currentUserId) {
            $recipients[$row['user_id']] = true;
        }
    }

    // Parse Mentions (@username)
    preg_match_all('/@(\w+)/', $comment, $matches);
    if (!empty($matches[1])) {
        $placeholders = implode(',', array_fill(0, count($matches[1]), '?'));
        $stmt = $db->prepare("SELECT id FROM users WHERE username IN ($placeholders)");
        $stmt->execute($matches[1]);
        while ($row = $stmt->fetch()) {
            if ((int)$row['id'] !== (int)$currentUserId) {
                $recipients[$row['id']] = true;
            }
        }
    }

    if (empty($recipients)) return;

    // Fetch Emails
    $ids = implode(',', array_keys($recipients));
    $stmt = $db->prepare("SELECT email FROM users WHERE id IN ($ids) AND email IS NOT NULL AND email != ''");
    // Note: In a real app we'd bind params properly or chunk, but reusing existing logic style
    // However, for CLI robustnes, let's just do it. SQLite/MySQL usually handle this string injection if IDs are safe (ints).
    // array_keys of $recipients are integers from DB, so safe.
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($emails)) return;

    $subject = "[" . $issue['project_name'] . "] " . "New Comment on Issue: " . $issue['title'];
    $message = "A new comment was posted on issue: " . $issue['title'] . "\nProject: " . $issue['project_name'] . "\n\n" .
               "Comment:\n" . $comment . "\n\n" .
               "View Issue: http://" . $host . "/issues/$issueId";

    sendEmails($emails, $subject, $message);
}

function processAssignmentNotification($db, $host, $data) {
    $issueId = $data['issue_id'];
    $assigneeId = $data['assignee_id'];
    $assignerId = $data['assigner_id'];

    // Get Issue
    $stmt = $db->prepare("SELECT i.title, p.name as project_name FROM issues i JOIN projects p ON i.project_id = p.id WHERE i.id = ?");
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch();
    if (!$issue) return;

    // Get Assignee Email
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$assigneeId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) return;

    // Get Assigner Name
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$assignerId]);
    $assigner = $stmt->fetch();
    $assignerName = $assigner ? $assigner['username'] : 'System';

    $subject = "[" . $issue['project_name'] . "] " . "Assigned to Issue: " . $issue['title'];
    $message = "You have been assigned to issue: " . $issue['title'] . "\nProject: " . $issue['project_name'] . "\nAssigner: $assignerName.\n\n" .
               "View Issue: http://" . $host . "/issues/$issueId";

    sendEmails([$user['email']], $subject, $message);
}

function sendEmails($emails, $subject, $message) {
    $fromEmail = Settings::get('smtp_from', 'noreply@buggy.local');
    $headers = [
        'From' => "Buggy <$fromEmail>",
        'Reply-To' => $fromEmail,
        'X-Mailer' => 'Buggy/1.0'
    ];

    try {
        $smtp = new SMTP(
            Settings::get('smtp_host'),
            Settings::get('smtp_port', 587),
            Settings::get('smtp_user'),
            Settings::get('smtp_pass'),
            Settings::get('smtp_encryption', 'tls')
        );

        foreach ($emails as $email) {
            try {
                $smtp->send($email, $subject, $message, $headers);
            } catch (Exception $e) {
                Logger::log('Email Worker Failure', "Failed to send to $email: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        Logger::log('Email Worker Critical', "Failed to init SMTP: " . $e->getMessage());
    }
}
