<?php

class ProjectController {
    public function dashboard() {
        Auth::requireLogin();

        // Run the recurring/inbox job processor
        require_once __DIR__ . '/../../process_recurring_jobs.php';

        $db = Database::connect();
        $currentUserId = (int)Auth::user()['id'];

        // Get the group_by parameter from the request
        $groupBy = $_GET['group_by'] ?? 'project';

        // Fetch tasks assigned to the current user with project name and comment count
        $sql = "
            SELECT i.*, p.name as project_name, u.username as assigned_to_name, c.username as creator_name,
                (SELECT COUNT(*) FROM comments WHERE task_id = i.id) as comment_count
            FROM tasks i
            JOIN projects p ON i.project_id = p.id
            LEFT JOIN users u ON i.assigned_to_id = u.id
            JOIN users c ON i.creator_id = c.id
            WHERE i.assigned_to_id = ? AND i.status NOT IN ('Completed', 'WND')
            ORDER BY i.updated_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $allTasks = $stmt->fetchAll();

        // Fetch jobs assigned to the current user (from user_inbox)
        $jobSql = "
            SELECT t.*, tl.title as list_title, u.username as assigned_by_name, ui.due_at, ui.id as inbox_id
            FROM jobs t
            JOIN user_inbox ui ON t.id = ui.job_id
            JOIN job_lists tl ON t.list_id = tl.id
            LEFT JOIN users u ON tl.owner_id = u.id
            WHERE ui.user_id = ? AND ui.status = 'incomplete'
            ORDER BY ui.due_at ASC, t.created_at DESC
        ";

        $jobStmt = $db->prepare($jobSql);
        $jobStmt->execute([$currentUserId]);
        $allJobs = $jobStmt->fetchAll();

        // Fetch users for the modal
        $users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

        // Fetch projects for the modal
        $projects = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

        // Group the tasks based on the selected option
        $groupedTasks = $this->groupTasks($allTasks, $groupBy);

        require __DIR__ . '/../Views/projects/dashboard.php';
    }

    private function groupTasks($tasks, $groupBy) {
        $grouped = [];

        switch ($groupBy) {
            case 'project':
                foreach ($tasks as $task) {
                    $projectName = $task['project_name'];
                    if (!isset($grouped[$projectName])) {
                        $grouped[$projectName] = [];
                    }
                    $grouped[$projectName][] = $task;
                }

                uasort($grouped, function($a, $b) {
                    return count($b) - count($a);
                });
                break;

            case 'priority':
                foreach ($tasks as $task) {
                    $priority = $task['priority'];
                    if (!isset($grouped[$priority])) {
                        $grouped[$priority] = [];
                    }
                    $grouped[$priority][] = $task;
                }

                $priorityOrder = ['High' => 3, 'Medium' => 2, 'Low' => 1];

                uasort($grouped, function($a, $b) use ($priorityOrder) {
                    $countA = count($a);
                    $countB = count($b);
                    if ($countA === $countB) {
                        $priorityA = $a[0]['priority'];
                        $priorityB = $b[0]['priority'];
                        return ($priorityOrder[$priorityB] ?? 0) - ($priorityOrder[$priorityA] ?? 0);
                    }
                    return $countB - $countA;
                });
                break;

            case 'type':
                foreach ($tasks as $task) {
                    $type = $task['type'];
                    if (!isset($grouped[$type])) {
                        $grouped[$type] = [];
                    }
                    $grouped[$type][] = $task;
                }

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
                foreach ($tasks as $task) {
                    $status = $task['status'];
                    if (!isset($grouped[$status])) {
                        $grouped[$status] = [];
                    }
                    $grouped[$status][] = $task;
                }

                uasort($grouped, function($a, $b) {
                    return count($b) - count($a);
                });
                break;

            default:
                foreach ($tasks as $task) {
                    $projectName = $task['project_name'];
                    if (!isset($grouped[$projectName])) {
                        $grouped[$projectName] = [];
                    }
                    $grouped[$projectName][] = $task;
                }

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
        } elseif ($sort === 'active_tasks') {
            $orderBy = 'COALESCE(up.is_pinned, 0) DESC, active_tasks DESC';
        } elseif ($sort === 'my_active_tasks') {
            $orderBy = 'COALESCE(up.is_pinned, 0) DESC, my_active_tasks DESC';
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

        // Fetch projects with owner name, task counts, and user-specific pinned status
        $sql = "
            SELECT p.*, u.username as owner_name,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status NOT IN ('Completed', 'WND')) as active_tasks,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND assigned_to_id = $currentUserId AND status NOT IN ('Completed', 'WND')) as my_active_tasks,
                COALESCE((SELECT MAX(updated_at) FROM tasks WHERE project_id = p.id), p.created_at) as last_activity,
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
            FROM tasks i
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
            header('Location: /projects');
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
            header('Location: /projects');
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
            header('Location: /projects');
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

    public function importTasks() {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $projectId = $_POST['project_id'];
            if (!$projectId || !isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                header('Location: /projects');
                exit;
            }

            $db = Database::connect();
            $creatorId = Auth::user()['id'];

            // Fetch all users to map assignee names
            $userRows = $db->query("SELECT id, username FROM users")->fetchAll();
            $usersMap = [];
            foreach ($userRows as $u) {
                $usersMap[strtolower($u['username'])] = $u['id'];
            }

            $filePath = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                // Read header row
                $headers = fgetcsv($handle, 0, ",", "\"", "");
                if ($headers !== FALSE) {
                    // Normalize headers
                    $headers = array_map(function($h) {
                        return trim(strtolower(str_replace('"', '', $h)));
                    }, $headers);

                    $titleIndex = array_search('title', $headers);
                    $descIndex = array_search('description', $headers);
                    $assigneeIndex = array_search('action required', $headers);
                    $priorityIndex = array_search('priority', $headers);
                    $statusIndex = array_search('status', $headers);

                    if ($titleIndex !== FALSE) {
                        $stmt = $db->prepare("
                            INSERT INTO tasks (project_id, title, description, type, creator_id, assigned_to_id, status, priority)
                            VALUES (?, ?, ?, 'Feature', ?, ?, ?, ?)
                        ");

                        while (($row = fgetcsv($handle, 0, ",", "\"", "")) !== FALSE) {
                            $title = $row[$titleIndex] ?? '';
                            if (empty(trim($title))) {
                                continue;
                            }
                            $description = $descIndex !== FALSE ? ($row[$descIndex] ?? '') : '';
                            
                            // Map Assignee
                            $assignedToId = null;
                            if ($assigneeIndex !== FALSE && !empty($row[$assigneeIndex])) {
                                $assigneeName = trim(strtolower($row[$assigneeIndex]));
                                if (isset($usersMap[$assigneeName])) {
                                    $assignedToId = $usersMap[$assigneeName];
                                }
                            }

                            // Map Priority
                            $csvPriority = $priorityIndex !== FALSE ? trim($row[$priorityIndex] ?? '') : '';
                            $priority = 'Medium';
                            if (strcasecmp($csvPriority, 'High') === 0) {
                                $priority = 'High';
                            } elseif (strcasecmp($csvPriority, 'Low') === 0) {
                                $priority = 'Low';
                            }

                            // Map Status
                            $csvStatus = $statusIndex !== FALSE ? trim($row[$statusIndex] ?? '') : '';
                            $status = 'Unassigned';
                            if (strcasecmp($csvStatus, 'Closed') === 0) {
                                $status = 'Completed';
                            } elseif (strcasecmp($csvStatus, 'In progress') === 0) {
                                $status = 'In Progress';
                            }

                            $stmt->execute([
                                $projectId,
                                $title,
                                $description,
                                $creatorId,
                                $assignedToId,
                                $status,
                                $priority
                            ]);
                        }
                        Logger::log('Tasks Imported', "Imported tasks to Project ID: $projectId");
                    }
                }
                fclose($handle);
            }

            header('Location: /projects/' . $projectId);
            exit;
        }
    }
}
