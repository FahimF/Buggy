<?php

class ProjectController {
    public function index() {
        Auth::requireLogin();
        $db = Database::connect();
        
        // Fetch projects with owner name
        $stmt = $db->query("
            SELECT p.*, u.username as owner_name 
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
            $ownerId = Auth::user()['id'];
            
            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO projects (name, color, owner_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $color, $ownerId]);
            
            Logger::log('Project Created', "Project: $name");
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
