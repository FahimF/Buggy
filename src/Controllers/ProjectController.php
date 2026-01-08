<?php

class ProjectController {
    public function dashboard() {
        Auth::requireLogin();

        // Run the recurring/inbox task processor
        // Using require_once to ensure it runs but functions aren't redefined if included elsewhere
        // Note: The script executes processRecurringTasks() at the end of the file
        require_once __DIR__ . '/../../process_recurring_tasks.php';

        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Get the group_by parameter from the request
        $groupBy = $_GET['group_by'] ?? 'project';

        // Fetch issues assigned to the current user with project name and comment count
        $sql = "
            SELECT i.*, p.name as project_name, u.username as assigned_to_name, c.username as creator_name,
                (SELECT COUNT(*) FROM comments WHERE issue_id = i.id) as comment_count
            FROM issues i
            JOIN projects p ON i.project_id = p.id
            LEFT JOIN users u ON i.assigned_to_id = u.id
            JOIN users c ON i.creator_id = c.id
            WHERE i.assigned_to_id = ? AND i.status NOT IN ('Completed', 'WND')
            ORDER BY i.updated_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $allIssues = $stmt->fetchAll();

        // Fetch tasks assigned to the current user
        $taskSql = "
            SELECT t.*, tl.title as list_title, u.username as assigned_by_name, ui.due_at, ui.id as inbox_id
            FROM tasks t
            JOIN user_inbox ui ON t.id = ui.task_id
            JOIN task_lists tl ON t.list_id = tl.id
            LEFT JOIN users u ON tl.owner_id = u.id
            WHERE ui.user_id = ? AND ui.status = 'incomplete'
            ORDER BY ui.due_at ASC, t.created_at DESC
        ";

        $taskStmt = $db->prepare($taskSql);
        $taskStmt->execute([$currentUserId]);
        $allTasks = $taskStmt->fetchAll();

        // Fetch users for the modal
        $users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

        // Fetch projects for the modal (required if project_id is not set)
        $projects = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

        // Group the issues based on the selected option
        $groupedIssues = $this->groupIssues($allIssues, $groupBy);

        require __DIR__ . '/../Views/projects/dashboard.php';
    }

    private function groupIssues($issues, $groupBy) {
        $grouped = [];

        switch ($groupBy) {
            case 'project':
                // Group by project and count occurrences
                foreach ($issues as $issue) {
                    $projectName = $issue['project_name'];
                    if (!isset($grouped[$projectName])) {
                        $grouped[$projectName] = [];
                    }
                    $grouped[$projectName][] = $issue;
                }

                // Sort by count (highest first)
                uasort($grouped, function($a, $b) {
                    return count($b) - count($a);
                });
                break;

            case 'priority':
                // Group by priority and count occurrences
                foreach ($issues as $issue) {
                    $priority = $issue['priority'];
                    if (!isset($grouped[$priority])) {
                        $grouped[$priority] = [];
                    }
                    $grouped[$priority][] = $issue;
                }

                // Define priority order for sorting
                $priorityOrder = ['High' => 3, 'Medium' => 2, 'Low' => 1];

                // Sort by count (highest first)
                uasort($grouped, function($a, $b) use ($priorityOrder) {
                    $countA = count($a);
                    $countB = count($b);
                    if ($countA === $countB) {
                        // If counts are equal, sort by priority level
                        $priorityA = $a[0]['priority'];
                        $priorityB = $b[0]['priority'];
                        return ($priorityOrder[$priorityB] ?? 0) - ($priorityOrder[$priorityA] ?? 0);
                    }
                    return $countB - $countA;
                });
                break;

            case 'type':
                // Group by type
                foreach ($issues as $issue) {
                    $type = $issue['type'];
                    if (!isset($grouped[$type])) {
                        $grouped[$type] = [];
                    }
                    $grouped[$type][] = $issue;
                }

                // Sort by type: Bugs first, then Features, then others
                $typeOrder = ['Bug' => 3, 'Feature' => 2, 'Task' => 1, 'Improvement' => 0];

                uasort($grouped, function($a, $b) use ($typeOrder) {
                    $typeA = $a[0]['type'];
                    $typeB = $b[0]['type'];
                    $orderA = $typeOrder[$typeA] ?? -1;
                    $orderB = $typeOrder[$typeB] ?? -1;
                    return $orderB - $orderA;
                });
                break;

            case 'status':
                // Group by status and count occurrences
                foreach ($issues as $issue) {
                    $status = $issue['status'];
                    if (!isset($grouped[$status])) {
                        $grouped[$status] = [];
                    }
                    $grouped[$status][] = $issue;
                }

                // Sort by count (highest first)
                uasort($grouped, function($a, $b) {
                    return count($b) - count($a);
                });
                break;

            default:
                // Default to project grouping
                foreach ($issues as $issue) {
                    $projectName = $issue['project_name'];
                    if (!isset($grouped[$projectName])) {
                        $grouped[$projectName] = [];
                    }
                    $grouped[$projectName][] = $issue;
                }

                // Sort by count (highest first)
                uasort($grouped, function($a, $b) {
                    return count($b) - count($a);
                });
                break;
        }

        return $grouped;
    }

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

        $currentUserId = (int)Auth::user()['id'];

        // For pinned projects, they should always appear at the top regardless of sort order
        $orderBy = 'COALESCE(up.is_pinned, 0) DESC, p.created_at DESC';

        if ($sort === 'updated') {
            $orderBy = 'COALESCE(up.is_pinned, 0) DESC, last_activity DESC';
        } elseif ($sort === 'active_issues') {
            $orderBy = 'COALESCE(up.is_pinned, 0) DESC, active_issues DESC';
        } elseif ($sort === 'my_active_issues') {
            $orderBy = 'COALESCE(up.is_pinned, 0) DESC, my_active_issues DESC';
        } elseif ($sort === 'name') {
            $orderBy = 'COALESCE(up.is_pinned, 0) DESC, p.name ASC';
        }

        // Search Logic
        $whereClause = "";
        $params = [];
        if (isset($_GET['q']) && !empty($_GET['q'])) {
            $whereClause = "WHERE p.name LIKE ?";
            $params[] = "%" . $_GET['q'] . "%";
        }

        // Fetch projects with owner name, issue counts, and user-specific pinned status
        $sql = "
            SELECT p.*, u.username as owner_name,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id) as total_issues,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id AND status NOT IN ('Completed', 'WND')) as active_issues,
                (SELECT COUNT(*) FROM issues WHERE project_id = p.id AND assigned_to_id = $currentUserId AND status NOT IN ('Completed', 'WND')) as my_active_issues,
                COALESCE((SELECT MAX(updated_at) FROM issues WHERE project_id = p.id), p.created_at) as last_activity,
                up.is_pinned
            FROM projects p
            JOIN users u ON p.owner_id = u.id
            LEFT JOIN user_projects up ON up.project_id = p.id AND up.user_id = ?
            $whereClause
            ORDER BY $orderBy
        ";

        $stmt = $db->prepare($sql);
        $params = array_merge([$currentUserId], $params);
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

    public function pin() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $currentUserId = (int)Auth::user()['id'];
            $db = Database::connect();

            // Insert or update the user_project record to pin the project
            $stmt = $db->prepare("INSERT OR REPLACE INTO user_projects (user_id, project_id, is_pinned) VALUES (?, ?, 1)");
            $stmt->execute([$currentUserId, $id]);

            Logger::log('Project Pinned', "Project ID: $id");

            // Check if it's an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                // Redirect back to the projects page
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit;
            }
        }
    }

    public function unpin() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $currentUserId = (int)Auth::user()['id'];
            $db = Database::connect();

            // Update the user_project record to unpin the project
            $stmt = $db->prepare("INSERT OR REPLACE INTO user_projects (user_id, project_id, is_pinned) VALUES (?, ?, 0)");
            $stmt->execute([$currentUserId, $id]);

            Logger::log('Project Unpinned', "Project ID: $id");

            // Check if it's an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                // Redirect back to the projects page
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit;
            }
        }
    }
}
