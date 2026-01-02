<?php

class NotificationService {
    public function sendCommentNotification($issueId, $comment, $userId) {
        if (Settings::get('enable_email') != '1') return;
        if (Settings::get('send_email_comment', '1') != '1') return;

        $this->dispatch('comment', [
            'issue_id' => $issueId,
            'comment' => $comment,
            'user_id' => $userId
        ]);
    }

    public function sendAssignmentNotification($issueId, $newAssigneeId, $assignerId) {
        if (Settings::get('enable_email') != '1') return;
        if (Settings::get('send_email_assign') != '1') return;
        
        // Don't notify if assigning to self
        if ($newAssigneeId == $assignerId) return;

        $this->dispatch('assign', [
            'issue_id' => $issueId,
            'assignee_id' => $newAssigneeId,
            'assigner_id' => $assignerId
        ]);
    }

    private function dispatch($type, $data) {
        $payload = [
            'type' => $type,
            'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'data' => $data
        ];
        
        // Use a temp file to pass data to avoid shell escaping issues with large comments
        $tempFile = tempnam(sys_get_temp_dir(), 'buggy_email_');
        file_put_contents($tempFile, json_encode($payload));
        
        // Path to the worker script
        $workerPath = realpath(__DIR__ . '/../Scripts/email_worker.php');
        
        // Run in background
        // Redirect output to /dev/null to ensure async
        $cmd = "php " . escapeshellarg($workerPath) . " " . escapeshellarg($tempFile) . " > /dev/null 2>&1 &";
        exec($cmd);
    }
}
