<?php

class ProjectController {
    public function index() {
        Auth::requireLogin();
        $db = Database::connect();
        
        // Handle sort persistence
        if (isset($_GET['sort'])) {
            $sort = $_GET['sort'];
            $_SESSION['project_sort'] = $sort;
        } elseif (isset($_SESSION['project_sort'])) {
            $sort = $_SESSION['project_sort'];
        } else {
            $sort = 'created_at';
        }

        $orderBy = 'p.created_at DESC';
        
        if ($sort === 'updated') {
            $orderBy = 'last_activity DESC';
        } elseif ($sort === 'active_issues') {
            $orderBy = 'active_issues DESC';
        } elseif ($sort === 'my_active_issues') {
            $orderBy = 'my_active_issues DESC';
        } elseif ($sort === 'name') {
            $orderBy = 'p.name ASC';
        }

        $currentUserId = (int)Auth::user()['id'];

        // Search Logic
        $whereClause = "";
        $params = [];
        if (isset($_GET['q']) && !empty($_GET['q'])) {
            $whereClause = "WHERE p.name LIKE ?";
            $params[] = "%" . $_GET['q'] . "%";
        }

        // Fetch projects with owner name and issue counts
        $sql = "
            SELECT p.*, u.username as owner_name,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id) as total_issues,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id AND status NOT IN ('Completed', 'WND')) as active_issues,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id AND assigned_to_id = $currentUserId AND status NOT IN ('Completed', 'WND')) as my_active_issues,
                COALESCE((SELECT MAX(updated_at) FROM issues WHERE project_id = p.id), p.created_at) as last_activity
            FROM projects p 
            JOIN users u ON p.owner_id = u.id 
            $whereClause
            ORDER BY $orderBy
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll();
        
        // Fetch users for the modal
        $users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

        // Fetch user stats
        $statsQuery = "
            SELECT i.project_id, u.username, COUNT(i.id) as count
            FROM issues i
            JOIN users u ON i.assigned_to_id = u.id
            WHERE i.status NOT IN ('Completed', 'WND')
            GROUP BY i.project_id, u.username
        ";
        $statsRows = $db->query($statsQuery)->fetchAll();
        
        $projectStats = [];
        foreach ($statsRows as $row) {
            $projectStats[$row['project_id']][] = [
                'username' => $row['username'],
                'count' => $row['count']
            ];
        }
        
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
