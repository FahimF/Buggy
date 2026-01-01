# Buggy - Issue Tracker

A simple multi-user, multi-project issue tracker built with native PHP and SQLite.

## Features
- **Projects**: Create and manage multiple projects.
- **Kanban Board**: Drag-and-drop issues between status columns (Unassigned, In Progress, QA, Completed, Won't Do).
- **Issue Tracking**: Create issues with rich text descriptions and image attachments.
- **Comments**: Discuss issues with comments and attachments.
- **Sorting**: Custom drag-and-drop sorting in list view.

## Setup & Running

1. **Start the PHP built-in server:**
   Run the following command from the project root:
   ```bash
   php -S localhost:8000 -t public
   ```

2. **Open your browser:**
   Go to [http://localhost:8000](http://localhost:8000).

3. **First Run:**
   You will be redirected to a setup page to create the first Admin user.

## Requirements
- PHP 8.0 or higher
- PDO SQLite extension
