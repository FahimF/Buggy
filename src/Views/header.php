<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buggy - Issue Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .kanban-column {
            min-height: 500px;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
        }
        .kanban-card {
            cursor: move;
        }
        .description-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;  
            overflow: hidden;
            font-size: 0.85rem;
            color: #6c757d;
        }
        .bg-orange {
            background-color: #fd7e14 !important;
            color: white;
        }
        .ql-editor {
            font-size: <?= Settings::get('quill_base_font_size') ?: '16px' ?> !important;
        }
        .ql-editor h1 { font-size: 2em; }
        .ql-editor h2 { font-size: 1.5em; }
        .ql-editor h3 { font-size: 1.17em; }
        .ql-editor h4 { font-size: 1em; }
        .ql-editor h5 { font-size: 0.83em; }
        .ql-editor h6 { font-size: 0.67em; }
        
        /* Image sizing fix and pointer for lightbox */
        .card-text img, .ql-editor img {
            max-width: 100%;
            height: auto;
            cursor: pointer;
        }
    </style>
</head>
<?php
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Unassigned':
            return 'bg-secondary';
        case 'In Progress':
            return 'bg-warning text-dark';
        case 'Ready for QA':
            return 'bg-orange';
        case 'Completed':
            return 'bg-success';
        case 'WND':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/dashboard">Buggy</a>
            <?php if (Auth::user()): ?>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/projects">Projects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/tasks">
                            Tasks
                            <?php
                            if (Auth::user()) {
                                $db = Database::connect();
                                $userId = (int)Auth::user()['id'];
                                $unreadCount = $db->query("SELECT COUNT(*) as count FROM user_inbox WHERE user_id = $userId AND is_read = 0")->fetch()['count'];
                                if ($unreadCount > 0) {
                                    echo '<span class="badge bg-danger">' . $unreadCount . '</span>';
                                }
                            }
                            ?>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if (Auth::user()['is_admin']): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin">Admin</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?= htmlspecialchars(Auth::user()['username']) ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout">Logout</a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container">
