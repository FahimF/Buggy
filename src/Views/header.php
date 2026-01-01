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
            <a class="navbar-brand" href="/">Buggy</a>
            <?php if (Auth::user()): ?>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Projects</a>
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
