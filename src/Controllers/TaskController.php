<?php

class TaskController {
    
    private function getProject($id) {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function getIssues($projectId, $orderBy = 'created_at DESC', $hideCompleted = false, $onlyMyIssues = false, $statusFilter = null, $authorFilter = null, $assigneeFilter = null, $priorityFilter = null, $typeFilter = null) {
        $db = Database::connect();
        // Allow safe column names for sorting
        $allowedSorts = ['created_at', 'updated_at', 'title', 'status', 'type', 'sort_order', 'priority', 'assigned_to_name', 'creator_name'];
        $orderParts = explode(' ', $orderBy);
        $col = $orderParts[0];
        $dir = isset($orderParts[1]) ? $orderParts[1] : 'ASC';
        
        if (!in_array($col, $allowedSorts)) $col = 'created_at';
        if (!in_array(strtoupper($dir), ['ASC', 'DESC'])) $dir = 'DESC';

        $whereClause = "WHERE i.project_id = ? AND i.is_archived = 0";
        $params = [$projectId];

        if ($hideCompleted) {
             $whereClause .= " AND i.status NOT IN ('Completed', 'WND')";
        }

        if ($onlyMyIssues) {
            $whereClause .= " AND i.assigned_to_id = ?";
            $params[] = Auth::user()['id'];
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $whereClause .= " AND i.status = ?";
            $params[] = $statusFilter;
        }

        if ($authorFilter !== null && $authorFilter !== '') {
            $whereClause .= " AND i.creator_id = ?";
            $params[] = $authorFilter;
        }

        if ($assigneeFilter !== null && $assigneeFilter !== '') {
            if ($assigneeFilter === 'unassigned') {
                $whereClause .= " AND i.assigned_to_id IS NULL";
            } else {
                $whereClause .= " AND i.assigned_to_id = ?";
                $params[] = $assigneeFilter;
            }
        }

        if ($priorityFilter !== null && $priorityFilter !== '') {
            $whereClause .= " AND i.priority = ?";
            $params[] = $priorityFilter;
        }

        if ($typeFilter !== null && $typeFilter !== '') {
            $whereClause .= " AND i.type = ?";
            $params[] = $typeFilter;
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
               WHEN status = 'WFR' THEN 3
               WHEN status = 'Ready for QA' THEN 4 
               WHEN status = 'Completed' THEN 5 
               WHEN status = 'WND' THEN 6
               ELSE 7 END $dir";
       }

        if ($col !== 'sort_order') {
            $orderClause .= ", i.sort_order ASC";
        }

        $sql = "SELECT i.*, u.username as assigned_to_name, c.username as creator_name,
                (SELECT COUNT(*) FROM comments WHERE task_id = i.id) as comment_count
                FROM tasks i 
                LEFT JOIN users u ON i.assigned_to_id = u.id 
                JOIN users c ON i.creator_id = c.id
                $whereClause 
                ORDER BY $orderClause";
                
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function index($projectId) {
        Auth::requireLogin();
        $project = $this->getProject($projectId);
        if (!$project) die("Project not found");

        // Handle View Mode Persistence
        if (isset($_GET['view']) && $_GET['view'] === 'list') {
            $_SESSION['task_view_mode'] = 'list';
        } elseif (isset($_GET['view']) && $_GET['view'] === 'status') {
            $_SESSION['task_view_mode'] = 'status';
            header("Location: /projects/$projectId/status");
            exit;
        } elseif (isset($_SESSION['task_view_mode']) && $_SESSION['task_view_mode'] === 'kanban') {
            header("Location: /projects/$projectId/kanban");
            exit;
        } elseif (isset($_SESSION['task_view_mode']) && $_SESSION['task_view_mode'] === 'status') {
            header("Location: /projects/$projectId/status");
            exit;
        } else {
            $_SESSION['task_view_mode'] = 'list';
        }

        // Handle Sort Persistence
        if (isset($_GET['sort'])) {
            $sort = $_GET['sort'];
            $dir = $_GET['dir'] ?? 'DESC';
            $_SESSION['task_sort'] = $sort;
            $_SESSION['task_dir'] = $dir;
        } elseif (isset($_SESSION['task_sort'])) {
            $sort = $_SESSION['task_sort'];
            $dir = $_SESSION['task_dir'] ?? 'DESC';
        } else {
            $sort = 'created_at';
            $dir = 'DESC';
        }

        if (isset($_GET['hide_completed'])) {
            $_SESSION['hide_completed'] = $_GET['hide_completed'];
        }
        
        $hideCompletedVal = $_SESSION['hide_completed'] ?? '1';
        $hideCompleted = $hideCompletedVal == '1';

        $onlyMyIssues = isset($_GET['my_issues']) && $_GET['my_issues'] == '1';

        $statusFilter = $_GET['status_filter'] ?? '';
        $authorFilter = $_GET['author_filter'] ?? '';
        $assigneeFilter = $_GET['assignee_filter'] ?? '';
        $priorityFilter = $_GET['priority_filter'] ?? '';
        $typeFilter = $_GET['type_filter'] ?? '';

        $db = Database::connect();
        
        // Get unique authors present in this project's tasks
        $authorStmt = $db->prepare("
            SELECT DISTINCT u.id, u.username 
            FROM tasks t 
            JOIN users u ON t.creator_id = u.id 
            WHERE t.project_id = ? AND t.is_archived = 0
            ORDER BY u.username
        ");
        $authorStmt->execute([$projectId]);
        $authors = $authorStmt->fetchAll();

        // Get unique assignees present in this project's tasks
        $assigneeStmt = $db->prepare("
            SELECT DISTINCT u.id, u.username 
            FROM tasks t 
            JOIN users u ON t.assigned_to_id = u.id 
            WHERE t.project_id = ? AND t.is_archived = 0
            ORDER BY u.username
        ");
        $assigneeStmt->execute([$projectId]);
        $assignees = $assigneeStmt->fetchAll();

        $tasks = $this->getIssues($projectId, "$sort $dir", $hideCompleted, $onlyMyIssues, $statusFilter, $authorFilter, $assigneeFilter, $priorityFilter, $typeFilter);
        
        $users = $this->getAllUsers(); // For assignment in modals if needed

        require __DIR__ . '/../Views/tasks/index.php';
    }

    public function kanban($projectId) {
        Auth::requireLogin();
        
        // Persist Kanban View Mode
        $_SESSION['task_view_mode'] = 'kanban';

        $project = $this->getProject($projectId);
        if (!$project) die("Project not found");

        $tasks = $this->getIssues($projectId, 'sort_order ASC');
        $columns = ['Unassigned', 'In Progress', 'WFR', 'Ready for QA', 'Completed', "WND"];
        
        // Group by status
        $kanbanData = array_fill_keys($columns, []);
        foreach ($tasks as $task) {
            $status = $task['status'];
            if (!isset($kanbanData[$status])) $status = 'Unassigned';
            $kanbanData[$status][] = $task;
        }

        $users = $this->getAllUsers();
        
        require __DIR__ . '/../Views/tasks/kanban.php';
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
            $stmt = $db->prepare("INSERT INTO tasks (project_id, title, type, priority, description, creator_id, assigned_to_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$projectId, $title, $type, $priority, $description, $creatorId, $assignedTo, $status]);
            
            $taskId = $db->lastInsertId();
            Logger::log('Task Created', "Task: $title ($type) in Project ID: $projectId");
            
            // Notification
            if ($assignedTo) {
                try {
                    (new NotificationService())->sendAssignmentNotification($taskId, $assignedTo, $creatorId);
                } catch (Exception $e) {
                    Logger::log('Notification Error', $e->getMessage());
                }
            }

            header("Location: /projects/$projectId");
        }
    }

    public function show($id) {
        Auth::requireLogin();
        $db = Database::connect();
        
        // Get Task
        $stmt = $db->prepare("
            SELECT i.*, u.username as assigned_to_name, c.username as creator_name, p.name as project_name
            FROM tasks i 
            LEFT JOIN users u ON i.assigned_to_id = u.id 
            JOIN users c ON i.creator_id = c.id
            JOIN projects p ON i.project_id = p.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        if (!$task || ($task['is_archived'] == 1 && !Auth::user()['is_admin'])) die("Task not found");

        // Get Comments
        $stmt = $db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.task_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$id]);
        $comments = $stmt->fetchAll();

        require __DIR__ . '/../Views/tasks/show.php';
    }

    public function details() {
        Auth::requireLogin();
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task ID']);
            exit;
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT i.*, u.username as assigned_to_name, c.username as creator_name, p.name as project_name
            FROM tasks i 
            LEFT JOIN users u ON i.assigned_to_id = u.id 
            JOIN users c ON i.creator_id = c.id
            JOIN projects p ON i.project_id = p.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        // Get Comments
        $stmt = $db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.task_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$id]);
        $comments = $stmt->fetchAll();

        // Get Sub-tasks
        $stmt = $db->prepare("SELECT * FROM sub_tasks WHERE task_id = ? ORDER BY created_at ASC");
        $stmt->execute([$id]);
        $subtasks = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode([
            'task' => $task,
            'comments' => $comments,
            'subtasks' => $subtasks,
            'users' => $this->getAllUsers(),
            'current_user_id' => Auth::user()['id'],
            'is_admin' => Auth::user()['is_admin']
        ]);
        exit;
        exit;
    }

    public function edit($id) {
        Auth::requireLogin();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        if (!$task) die("Task not found");
        
        $project = $this->getProject($task['project_id']);
        $users = $this->getAllUsers();

        // Get Comments
        $stmt = $db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.task_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$id]);
        $comments = $stmt->fetchAll();

        $incompleteSubtasksStmt = $db->prepare("SELECT COUNT(*) FROM sub_tasks WHERE task_id = ? AND is_completed = 0");
        $incompleteSubtasksStmt->execute([$id]);
        $incompleteSubtasksCount = (int)$incompleteSubtasksStmt->fetchColumn();

        require __DIR__ . '/../Views/tasks/edit.php';
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

            $db = Database::connect();
            $stmt = $db->prepare("SELECT status, assigned_to_id FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $currentTask = $stmt->fetch();

            if ($currentTask && $currentTask['status'] !== $status) {
                // Status changed, apply auto-assign logic if applicable
                $newAssignee = $this->getAutoAssignUser($status);
                if ($newAssignee) {
                    $assignedTo = $newAssignee;
                }
            }

            if ($status === 'Completed') {
                $incompleteSubtasksStmt = $db->prepare("SELECT COUNT(*) FROM sub_tasks WHERE task_id = ? AND is_completed = 0");
                $incompleteSubtasksStmt->execute([$id]);
                $incompleteCount = (int)$incompleteSubtasksStmt->fetchColumn();
                if ($incompleteCount > 0) {
                    if (isset($_POST['force_complete_subtasks']) && $_POST['force_complete_subtasks'] == 1) {
                        $db->prepare("UPDATE sub_tasks SET is_completed = 1 WHERE task_id = ?")->execute([$id]);
                    } else {
                        header('Location: /tasks/' . $id . '/edit');
                        exit;
                    }
                }
            }

            $stmt = $db->prepare("UPDATE tasks SET title = ?, type = ?, priority = ?, description = ?, assigned_to_id = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $type, $priority, $description, $assignedTo, $status, $id]);

            // Notification
            if ($assignedTo && $assignedTo != $currentTask['assigned_to_id']) {
                try {
                    (new NotificationService())->sendAssignmentNotification($id, $assignedTo, Auth::user()['id']);
                } catch (Exception $e) {
                    Logger::log('Notification Error', $e->getMessage());
                }
            }

            // Check if the user came from the dashboard to determine redirect location
            $referrer = $_POST['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referrer, '/dashboard') !== false) {
                header("Location: /dashboard");
            } else {
                // Fetch project ID for redirection to project page
                $stmt = $db->prepare("SELECT project_id FROM tasks WHERE id = ?");
                $stmt->execute([$id]);
                $task = $stmt->fetch();

                header("Location: /projects/" . $task['project_id']);
            }
        }
    }

    public function delete($id) {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::connect();
            
            // Get project ID before delete for redirect
            $stmt = $db->prepare("SELECT project_id FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();
            
            if ($task) {
                $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$id]);
                header("Location: /projects/" . $task['project_id']);
            } else {
                die("Task not found");
            }
        }
    }

    public function updateStatus() {
        Auth::requireLogin();
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['issue_id']) && isset($input['status'])) {
            $db = Database::connect();
            $taskId = $input['issue_id']; // For JS compatibility, handle issue_id parameter
            $status = $input['status'];

            if ($status === 'Completed') {
                $incompleteSubtasksStmt = $db->prepare("SELECT COUNT(*) FROM sub_tasks WHERE task_id = ? AND is_completed = 0");
                $incompleteSubtasksStmt->execute([$taskId]);
                $incompleteCount = (int)$incompleteSubtasksStmt->fetchColumn();
                if ($incompleteCount > 0) {
                    if (isset($input['force_complete_subtasks']) && $input['force_complete_subtasks'] == true) {
                        $db->prepare("UPDATE sub_tasks SET is_completed = 1 WHERE task_id = ?")->execute([$taskId]);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'error' => 'incomplete_subtasks',
                            'count' => $incompleteCount
                        ]);
                        exit;
                    }
                }
            }
            
            // Auto-assign logic
            $assignedToUpdate = "";
            $params = [$status];
            
            $newAssignee = $this->getAutoAssignUser($status);
            if ($newAssignee) {
                $assignedToUpdate = ", assigned_to_id = ?";
                $params[] = $newAssignee;
            }
            
            $params[] = $taskId;

            $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP $assignedToUpdate WHERE id = ?");
            $stmt->execute($params);

            if (isset($input['order']) && is_array($input['order'])) {
                $reorderStmt = $db->prepare("UPDATE tasks SET sort_order = ? WHERE id = ?");
                foreach ($input['order'] as $index => $id) {
                    $reorderStmt->execute([$index, $id]);
                }
            }

            if ($newAssignee) {
                try {
                    (new NotificationService())->sendAssignmentNotification($taskId, $newAssignee, Auth::user()['id']);
                } catch (Exception $e) {
                    Logger::log('Notification Error', $e->getMessage());
                }
            }

            echo json_encode(['success' => true]);
        }
    }

    public function reorder() {
        Auth::requireLogin();
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['order'])) {
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE tasks SET sort_order = ? WHERE id = ?");
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

    public function getSubTasks() {
        Auth::requireLogin();
        $taskId = $_GET['issue_id'] ?? $_GET['task_id'] ?? null;
        if (!$taskId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task_id']);
            exit;
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM sub_tasks WHERE task_id = ? ORDER BY created_at ASC");
        $stmt->execute([$taskId]);
        $subTasks = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode($subTasks);
        exit;
    }

    public function createSubTask() {
        Auth::requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $taskId = $data['issue_id'] ?? $data['task_id'] ?? null;
        $description = trim($data['description'] ?? '');

        if (!$taskId || !$description) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        $insertStmt = $db->prepare("INSERT INTO sub_tasks (task_id, description, is_completed) VALUES (?, ?, 0)");
        $insertStmt->execute([$taskId, $description]);
        $newId = $db->lastInsertId();

        header('Content-Type: application/json');
        echo json_encode([
            'id' => $newId,
            'task_id' => $taskId,
            'description' => $description,
            'is_completed' => 0
        ]);
        exit;
    }

    public function toggleSubTask() {
        Auth::requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $isCompleted = isset($data['is_completed']) ? (int)$data['is_completed'] : null;

        if (!$id || $isCompleted === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM sub_tasks WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Sub-task not found']);
            exit;
        }

        $updateStmt = $db->prepare("UPDATE sub_tasks SET is_completed = ? WHERE id = ?");
        $updateStmt->execute([$isCompleted, $id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    public function deleteSubTask() {
        Auth::requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM sub_tasks WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Sub-task not found']);
            exit;
        }

        $deleteStmt = $db->prepare("DELETE FROM sub_tasks WHERE id = ?");
        $deleteStmt->execute([$id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    public function nestTask() {
        Auth::requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $sourceId = $data['source_id'] ?? null;
        $destId = $data['dest_id'] ?? null;

        if (!$sourceId || !$destId || $sourceId == $destId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid source or destination task']);
            exit;
        }

        $db = Database::connect();
        
        // Fetch source task
        $stmt = $db->prepare("SELECT title, description FROM tasks WHERE id = ?");
        $stmt->execute([$sourceId]);
        $sourceTask = $stmt->fetch();

        // Fetch destination task
        $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ?");
        $stmt->execute([$destId]);
        $destTask = $stmt->fetch();

        if (!$sourceTask || !$destTask) {
            http_response_code(404);
            echo json_encode(['error' => 'Source or destination task not found']);
            exit;
        }

        $title = $sourceTask['title'];
        $desc = strip_tags($sourceTask['description'] ?? '');
        
        // Merge title and description for sub-task description
        $subTaskDescription = $title;
        if (!empty($desc)) {
            $subTaskDescription .= ": " . $desc;
        }

        $db->beginTransaction();
        try {
            // Insert sub_task
            $insertStmt = $db->prepare("INSERT INTO sub_tasks (task_id, description, is_completed) VALUES (?, ?, 0)");
            $insertStmt->execute([$destId, $subTaskDescription]);

            // Delete source task
            $deleteStmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $deleteStmt->execute([$sourceId]);

            $db->commit();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to nest task: ' . $e->getMessage()]);
            exit;
        }
    }

    public function archive($id) {
        Auth::requireLogin();
        $db = Database::connect();
        $stmt = $db->prepare("SELECT project_id, title FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if ($task) {
            $stmt = $db->prepare("UPDATE tasks SET is_archived = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            Logger::log('Task Archived', "Task: {$task['title']}");
            
            $redirect = $_POST['redirect_to'] ?? "/projects/{$task['project_id']}";
            header("Location: " . $redirect);
            exit;
        }
        die("Task not found");
    }

    public function archiveStatus($projectId) {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $status = $_POST['status'] ?? '';
            if (in_array($status, ['Completed', 'WND'])) {
                $db = Database::connect();
                $stmt = $db->prepare("UPDATE tasks SET is_archived = 1, updated_at = CURRENT_TIMESTAMP WHERE project_id = ? AND status = ? AND is_archived = 0");
                $stmt->execute([$projectId, $status]);
                Logger::log('Tasks Column Archived', "Archived all '$status' tasks in Project ID: $projectId");
            }
            header("Location: /projects/$projectId/kanban");
            exit;
        }
    }

    public function batchAction() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_ids']) && is_array($_POST['task_ids'])) {
            $taskIds = array_map('intval', $_POST['task_ids']);
            $action = $_POST['batch_action'] ?? '';
            $projectId = $_POST['project_id'] ?? '';
            
            if (!empty($taskIds)) {
                $db = Database::connect();
                $placeholders = str_repeat('?,', count($taskIds) - 1) . '?';
                
                if ($action === 'archive') {
                    $stmt = $db->prepare("UPDATE tasks SET is_archived = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
                    $stmt->execute($taskIds);
                    Logger::log('Tasks Archived (Batch)', "Archived tasks count: " . count($taskIds));
                } elseif ($action === 'delete') {
                    $stmt = $db->prepare("DELETE FROM tasks WHERE id IN ($placeholders)");
                    $stmt->execute($taskIds);
                    Logger::log('Tasks Deleted (Batch)', "Deleted tasks count: " . count($taskIds));
                } elseif ($action === 'status') {
                    $newStatus = $_POST['status_value'] ?? 'Unassigned';
                    $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
                    $params = array_merge([$newStatus], $taskIds);
                    $stmt->execute($params);
                    Logger::log('Tasks Status Updated (Batch)', "Changed status to $newStatus for tasks count: " . count($taskIds));
                }
            }
            if ($projectId) {
                header("Location: /projects/$projectId");
            } else {
                header("Location: /");
            }
            exit;
        }
    }
}
