<?php

class IssueController {
    
    private function getProject($id) {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function getIssues($projectId, $orderBy = 'created_at DESC', $hideCompleted = false) {
        $db = Database::connect();
        // Allow safe column names for sorting
        $allowedSorts = ['created_at', 'updated_at', 'title', 'status', 'type', 'sort_order', 'priority', 'assigned_to_name', 'creator_name'];
        $orderParts = explode(' ', $orderBy);
        $col = $orderParts[0];
        $dir = isset($orderParts[1]) ? $orderParts[1] : 'ASC';
        
        if (!in_array($col, $allowedSorts)) $col = 'created_at';
        if (!in_array(strtoupper($dir), ['ASC', 'DESC'])) $dir = 'DESC';

        $whereClause = "WHERE i.project_id = ?";
        if ($hideCompleted) {
             $whereClause .= " AND i.status NOT IN ('Completed', 'WND')";
        }

        $orderClause = "$col $dir";
        if ($col === 'priority') {
             $orderClause = "CASE 
                WHEN priority = 'High' THEN 1 
                WHEN priority = 'Medium' THEN 2 
                WHEN priority = 'Low' THEN 3 
                ELSE 4 END $dir";
        }

        if ($col === 'status') {
            $orderClause = "CASE 
               WHEN status = 'Unassigned' THEN 1 
               WHEN status = 'In Progress' THEN 2 
               WHEN status = 'Ready for QA' THEN 3 
               WHEN status = 'Completed' THEN 4 
               WHEN status = 'WND' THEN 5
               ELSE 6 END $dir";
       }

        $sql = "SELECT i.*, u.username as assigned_to_name, c.username as creator_name 
                FROM issues i 
                LEFT JOIN users u ON i.assigned_to_id = u.id 
                JOIN users c ON i.creator_id = c.id
                $whereClause 
                ORDER BY $orderClause";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }
    
    public function index($projectId) {
        Auth::requireLogin();
        $project = $this->getProject($projectId);
        if (!$project) die("Project not found");

        $sort = $_GET['sort'] ?? 'created_at';
        $dir = $_GET['dir'] ?? 'DESC';
        $hideCompleted = !isset($_GET['hide_completed']) || $_GET['hide_completed'] == '1';

        $issues = $this->getIssues($projectId, "$sort $dir", $hideCompleted);
        
        $users = $this->getAllUsers(); // For assignment in modals if needed

        require __DIR__ . '/../Views/issues/index.php';
    }

    public function kanban($projectId) {
        Auth::requireLogin();
        $project = $this->getProject($projectId);
        if (!$project) die("Project not found");

        $issues = $this->getIssues($projectId, 'sort_order ASC');
        $columns = ['Unassigned', 'In Progress', 'Ready for QA', 'Completed', "WND"];
        
        // Group by status
        $kanbanData = array_fill_keys($columns, []);
        foreach ($issues as $issue) {
            $status = $issue['status'];
            if (!isset($kanbanData[$status])) $status = 'Unassigned';
            $kanbanData[$status][] = $issue;
        }

        $users = $this->getAllUsers();
        
        require __DIR__ . '/../Views/issues/kanban.php';
    }

    public function create() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $projectId = $_POST['project_id'];
            $title = $_POST['title'];
            $type = $_POST['type'] ?? 'Bug';
            $priority = $_POST['priority'] ?? 'Medium';
            $description = $_POST['description']; // HTML from Quill
            $assignedTo = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
            $status = $_POST['status'] ?? 'Unassigned';
            $creatorId = Auth::user()['id'];

            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO issues (project_id, title, type, priority, description, creator_id, assigned_to_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$projectId, $title, $type, $priority, $description, $creatorId, $assignedTo, $status]);
            
            Logger::log('Issue Created', "Issue: $title ($type) in Project ID: $projectId");
            
            // Handle attachments if any (simplification: assume handled separately or simple file upload)
            
            header("Location: /projects/$projectId");
        }
    }

    public function show($id) {
        Auth::requireLogin();
        $db = Database::connect();
        
        // Get Issue
        $stmt = $db->prepare("
            SELECT i.*, u.username as assigned_to_name, c.username as creator_name, p.name as project_name
            FROM issues i 
            LEFT JOIN users u ON i.assigned_to_id = u.id 
            JOIN users c ON i.creator_id = c.id
            JOIN projects p ON i.project_id = p.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $issue = $stmt->fetch();
        
        if (!$issue) die("Issue not found");

        // Get Comments
        $stmt = $db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.issue_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$id]);
        $comments = $stmt->fetchAll();

        require __DIR__ . '/../Views/issues/show.php';
    }

    public function edit($id) {
        Auth::requireLogin();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM issues WHERE id = ?");
        $stmt->execute([$id]);
        $issue = $stmt->fetch();
        
        if (!$issue) die("Issue not found");
        
        $project = $this->getProject($issue['project_id']);
        $users = $this->getAllUsers();

        require __DIR__ . '/../Views/issues/edit.php';
    }

    public function update($id) {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'];
            $type = $_POST['type'];
            $priority = $_POST['priority'];
            $description = $_POST['description'];
            $assignedTo = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
            $status = $_POST['status'];
            
            // Check for auto-assign (if user manually changed status but didn't explicitly change assignment, 
            // or we just override? Requirements say "When the status is changed... value should be set".
            // Ideally we only override if the user didn't pick a new assignee manually? 
            // But the form submits the current assignee.
            // Let's check if status changed.
            $db = Database::connect();
            $stmt = $db->prepare("SELECT status, assigned_to_id FROM issues WHERE id = ?");
            $stmt->execute([$id]);
            $currentIssue = $stmt->fetch();
            
            if ($currentIssue && $currentIssue['status'] !== $status) {
                // Status changed, apply auto-assign logic if applicable
                $newAssignee = $this->getAutoAssignUser($status);
                if ($newAssignee) {
                    $assignedTo = $newAssignee;
                }
            }

            $stmt = $db->prepare("UPDATE issues SET title = ?, type = ?, priority = ?, description = ?, assigned_to_id = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $type, $priority, $description, $assignedTo, $status, $id]);
            
            // Fetch project ID for redirection
            $stmt = $db->prepare("SELECT project_id FROM issues WHERE id = ?");
            $stmt->execute([$id]);
            $issue = $stmt->fetch();

            header("Location: /projects/" . $issue['project_id']);
        }
    }

    public function delete($id) {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::connect();
            
            // Get project ID before delete for redirect
            $stmt = $db->prepare("SELECT project_id FROM issues WHERE id = ?");
            $stmt->execute([$id]);
            $issue = $stmt->fetch();
            
            if ($issue) {
                $stmt = $db->prepare("DELETE FROM issues WHERE id = ?");
                $stmt->execute([$id]);
                header("Location: /projects/" . $issue['project_id']);
            } else {
                die("Issue not found");
            }
        }
    }

    public function updateStatus() {
        Auth::requireLogin();
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['issue_id']) && isset($input['status'])) {
            $db = Database::connect();
            
            // Auto-assign logic
            $assignedToUpdate = "";
            $params = [$input['status']];
            
            $newAssignee = $this->getAutoAssignUser($input['status']);
            if ($newAssignee) {
                $assignedToUpdate = ", assigned_to_id = ?";
                $params[] = $newAssignee;
            }
            
            $params[] = $input['issue_id'];

            $stmt = $db->prepare("UPDATE issues SET status = ?, updated_at = CURRENT_TIMESTAMP $assignedToUpdate WHERE id = ?");
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        }
    }

    public function reorder() {
        Auth::requireLogin();
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['order'])) {
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE issues SET sort_order = ? WHERE id = ?");
            foreach ($input['order'] as $index => $id) {
                $stmt->execute([$index, $id]);
            }
            echo json_encode(['success' => true]);
        }
    }

    private function getAllUsers() {
        $db = Database::connect();
        return $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
    }

    private function getAutoAssignUser($status) {
        $settingKey = null;
        if ($status === 'Ready for QA') {
            $settingKey = 'auto_assign_qa';
        } elseif ($status === 'In Progress') {
            $settingKey = 'auto_assign_coding';
        }
        
        if ($settingKey) {
            return Settings::get($settingKey);
        }
        return null;
    }
}
