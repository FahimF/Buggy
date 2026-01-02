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

            header("Location: /issues/$issueId");

            // Send Email Notifications
            try {
                (new NotificationService())->sendCommentNotification($issueId, $comment, $userId);
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
            header("Location: /issues/" . $comment['issue_id']);
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
            header("Location: /issues/" . $comment['issue_id']);
        }
    }
}
