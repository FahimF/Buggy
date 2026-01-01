<?php

class CommentController {
    public function create() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $issueId = $_POST['issue_id'];
            $comment = $_POST['comment'];
            $userId = Auth::user()['id'];

            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO comments (issue_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$issueId, $userId, $comment]);

            // Handle Attachments (Simplified: Single file for now, or multiple if array)
            if (!empty($_FILES['attachments']['name'][0])) {
                $commentId = $db->lastInsertId();
                $total = count($_FILES['attachments']['name']);
                
                for( $i=0 ; $i < $total ; $i++ ) {
                    $tmpFilePath = $_FILES['attachments']['tmp_name'][$i];
                    if ($tmpFilePath != ""){
                        $filename = uniqid() . '_' . basename($_FILES['attachments']['name'][$i]);
                        $newFilePath = __DIR__ . "/../../public/uploads/" . $filename;
                        if(move_uploaded_file($tmpFilePath, $newFilePath)) {
                            $stmtAtt = $db->prepare("INSERT INTO attachments (parent_type, parent_id, file_path) VALUES ('comment', ?, ?)");
                            $stmtAtt->execute([$commentId, $filename]);
                        }
                    }
                }
            }

            header("Location: /issues/$issueId");

            // Send Email Notifications
            if (Settings::get('enable_email') == '1') {
                $this->sendNotifications($db, $issueId, $comment, $userId);
            }
        }
    }

    private function sendNotifications($db, $issueId, $comment, $currentUserId) {
        // Get Issue Details
        $stmt = $db->prepare("SELECT title, creator_id FROM issues WHERE id = ?");
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
            // Exclude current commenter
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
        Logger::log('Notification Debug', "Issue $issueId: Sending notifications to User IDs: $ids");

        $stmt = $db->prepare("SELECT email FROM users WHERE id IN ($ids) AND email IS NOT NULL AND email != ''");
        // SQLite doesn't support IN (?) with array directly in older versions or some drivers easily for dynamic lists 
        // without constructing the query string, which I did above.
        $stmt->execute();
        
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($emails)) return;

        // Send Emails
        $subject = "New Comment on Issue: " . $issue['title'];
        $message = "A new comment was posted on issue: " . $issue['title'] . "\n\n" .
                   "Comment:\n" . $comment . "\n\n" .
                   "View Issue: http://" . $_SERVER['HTTP_HOST'] . "/issues/$issueId";
        
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
                    // Send individually to hide other recipients (BCC effect)
                    // Re-connect for each might be slow but safer for simple impl. 
                    // Optimization: Keep connection open if SMTP class supported it, but our simple class closes on send.
                    $smtp->send($email, $subject, $message, $headers);
                } catch (Exception $e) {
                    Logger::log('Email Partial Failure', "Failed to send to $email: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            // Log error but don't stop execution
            Logger::log('Email Error', "Failed to init SMTP or send notifications: " . $e->getMessage());
        }
    }
}
