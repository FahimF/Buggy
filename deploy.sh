#!/bin/bash

# Exit on error
set -e

# Configuration
REMOTE_USER="fahim"
REMOTE_HOST="192.168.1.3"
REMOTE_DIR="/Volume1/www/buggy/"
LOCAL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/"

echo "======================================================"
echo "          Buggy Deployment Script"
echo "======================================================"
echo "Local Project Dir:  $LOCAL_DIR"
echo "Remote Target:      $REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR"
echo "======================================================"

# Ensure rsync is installed
if ! command -v rsync >/dev/null 2>&1; then
    echo "Error: rsync is required but not installed." >&2
    exit 1
fi

# Exclude list to protect databases, user uploads, git history, and OS files.
# Excluding 'data/*.db' and 'data/buggy.db' protects the SQLite database.
# Excluding 'public/uploads/' protects uploaded files on the remote server.
EXCLUDES=(
    --exclude=".git/"
    --exclude=".gitignore"
    --exclude=".DS_Store"
    --exclude=".gemini-clipboard/"
    --exclude="deploy.sh"
    --exclude="data/*.db"
    --exclude="data/buggy.db"
    --exclude="public/uploads/*"
)

# Perform a Dry Run first
echo "Performing a dry-run to show changes..."
echo "------------------------------------------------------"
rsync -avz --dry-run "${EXCLUDES[@]}" "$LOCAL_DIR" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR"
echo "------------------------------------------------------"

# Prompt for confirmation
read -p "Would you like to execute the deployment now? (y/N): " choice
case "$choice" in
    [yY][eE][sS]|[yY])
        echo "Starting deployment..."
        rsync -avz "${EXCLUDES[@]}" "$LOCAL_DIR" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR"
        echo "Deployment complete!"
        ;;
    *)
        echo "Deployment aborted."
        exit 0
        ;;
esac
