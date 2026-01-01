<?php

class ProjectController {
    public function index() {
        Auth::requireLogin();
        $db = Database::connect();
        
        // Fetch projects with owner name and issue counts
        $stmt = $db->query("
            SELECT p.*, u.username as owner_name,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id) as total_issues,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id AND status NOT IN ('Completed', 'Won''t Do')) as active_issues
            FROM projects p 
            JOIN users u ON p.owner_id = u.id 
            ORDER BY p.created_at DESC
        ");
        $projects = $stmt->fetchAll();
        
        require __DIR__ . '/../Views/projects/index.php';
    }

    public function create() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'];
            $color = $_POST['color'];
            $textColor = $_POST['text_color'] ?? '#ffffff';
            $ownerId = Auth::user()['id'];
            
            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO projects (name, color, text_color, owner_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $color, $textColor, $ownerId]);
            
            Logger::log('Project Created', "Project: $name");
            header('Location: /');
        }
    }

    public function update() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $color = $_POST['color'];
            $textColor = $_POST['text_color'] ?? '#ffffff';
            
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE projects SET name = ?, color = ?, text_color = ? WHERE id = ?");
            $stmt->execute([$name, $color, $textColor, $id]);
            
            Logger::log('Project Updated', "Project ID: $id");
            header('Location: /');
        }
    }

    public function delete() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            
            Logger::log('Project Deleted', "Project ID: $id");
            header('Location: /');
        }
    }
}
