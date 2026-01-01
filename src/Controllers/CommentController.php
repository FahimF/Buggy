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
        }
    }
}
