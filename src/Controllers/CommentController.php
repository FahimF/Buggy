<?php

class CommentController {
    public function create() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $taskId = $_POST['task_id'] ?? $_POST['issue_id'] ?? null;
            $comment = $_POST['comment'];
            $userId = Auth::user()['id'];

            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO comments (task_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$taskId, $userId, $comment]);

            header("Location: /tasks/$taskId");

            // Send Email Notifications
            try {
                (new NotificationService())->sendCommentNotification($taskId, $comment, $userId);
            } catch (Exception $e) {
                Logger::log('Notification Error', $e->getMessage());
            }
        }
    }

    public function edit($id) {
        Auth::requireLogin();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch();

        if (!$comment) {
            http_response_code(404);
            echo "Comment not found";
            return;
        }

        if (Auth::user()['id'] != $comment['user_id']) {
            http_response_code(403);
            echo "Unauthorized";
            return;
        }

        require __DIR__ . '/../Views/comments/edit.php';
    }

    public function update($id) {
        Auth::requireLogin();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch();

        if (!$comment) {
            http_response_code(404);
            echo "Comment not found";
            return;
        }

        if (Auth::user()['id'] != $comment['user_id']) {
            http_response_code(403);
            echo "Unauthorized";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newComment = $_POST['comment'];
            $stmt = $db->prepare("UPDATE comments SET comment = ? WHERE id = ?");
            $stmt->execute([$newComment, $id]);
            header("Location: /tasks/" . $comment['task_id']);
        }
    }

    public function delete($id) {
        Auth::requireLogin();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch();

        if (!$comment) {
            http_response_code(404);
            echo "Comment not found";
            return;
        }

        $user = Auth::user();
        if ($user['id'] != $comment['user_id'] && !$user['is_admin']) {
            http_response_code(403);
            echo "Unauthorized";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: /tasks/" . $comment['task_id']);
        }
    }
}
