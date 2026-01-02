<?php

require_once __DIR__ . '/../Database.php';

echo "Starting migration of comments to QuillJS format...\n";

$db = Database::connect();

// 1. Fetch all comments
$stmt = $db->query("SELECT * FROM comments");
$comments = $stmt->fetchAll();

$count = 0;

foreach ($comments as $comment) {
    $originalComment = $comment['comment'];
    
    // Check if it already looks like HTML (basic check)
    if (strpos($originalComment, '<p>') !== false || strpos($originalComment, '<br>') !== false) {
        // Skip if already likely HTML (or maybe just wrap it to be safe? but let's assume plain text for migration)
        // actually, safety first: if it doesn't have tags, convert it.
    }

    // Convert plain text newlines to HTML paragraphs
    // A simple way is to wrap lines in <p>.
    // Or just use nl2br? Quill prefers <p> blocks.
    $lines = explode("\n", $originalComment);
    $htmlComment = "";
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $htmlComment .= "<p>" . htmlspecialchars($line) . "</p>";
        }
    }
    
    // If empty (e.g. just whitespace), ensure at least one empty p
    if (empty($htmlComment)) {
        $htmlComment = "<p>" . htmlspecialchars($originalComment) . "</p>";
    }

    // 2. Fetch attachments for this comment
    $stmtAtt = $db->prepare("SELECT * FROM attachments WHERE parent_type = 'comment' AND parent_id = ?");
    $stmtAtt->execute([$comment['id']]);
    $attachments = $stmtAtt->fetchAll();

    if (!empty($attachments)) {
        $htmlComment .= "<p><br></p>"; // Spacer
        foreach ($attachments as $att) {
            $imagePath = "/uploads/" . $att['file_path'];
            // Append image tag
            $htmlComment .= '<p><img src="' . $imagePath . '" alt="Attachment"></p>';
        }
        echo " - Merged " . count($attachments) . " attachments for comment ID " . $comment['id'] . "\n";
    }

    // 3. Update the comment
    $updateStmt = $db->prepare("UPDATE comments SET comment = ? WHERE id = ?");
    $updateStmt->execute([$htmlComment, $comment['id']]);
    
    // 4. Delete attachment records (files stay on disk)
    if (!empty($attachments)) {
        $delStmt = $db->prepare("DELETE FROM attachments WHERE parent_type = 'comment' AND parent_id = ?");
        $delStmt->execute([$comment['id']]);
    }

    $count++;
}

echo "Migration complete. Processed $count comments.\n";

