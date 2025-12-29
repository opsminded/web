#!/bin/bash

# GDMon Deployment Script
# Deploys the application to a production server without git clone

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${SCRIPT_DIR}/deploy.config"

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if config file exists
if [ ! -f "$CONFIG_FILE" ]; then
    log_error "Configuration file not found: $CONFIG_FILE"
    log_info "Please copy deploy.config.example to deploy.config and customize it"
    log_info "  cp deploy.config.example deploy.config"
    exit 1
fi

# Load configuration
log_info "Loading configuration from $CONFIG_FILE"
source "$CONFIG_FILE"

# Validate required configuration
if [ -z "$DEPLOY_HOST" ] || [ -z "$DEPLOY_USER" ] || [ -z "$DEPLOY_PATH" ]; then
    log_error "Missing required configuration: DEPLOY_HOST, DEPLOY_USER, or DEPLOY_PATH"
    exit 1
fi

# Build SSH connection string
SSH_OPTS="-p ${DEPLOY_PORT:-22}"
if [ -n "$DEPLOY_KEY" ]; then
    SSH_OPTS="$SSH_OPTS -i $DEPLOY_KEY"
fi
SSH_CONN="${DEPLOY_USER}@${DEPLOY_HOST}"

# Test SSH connection
log_info "Testing SSH connection to $SSH_CONN..."
if ! ssh $SSH_OPTS "$SSH_CONN" "echo 'Connection successful'" >/dev/null 2>&1; then
    log_error "Cannot connect to $SSH_CONN"
    log_info "Please check your SSH configuration and credentials"
    exit 1
fi
log_success "SSH connection successful"

# Create temporary directory for deployment package
TEMP_DIR=$(mktemp -d)
trap "rm -rf $TEMP_DIR" EXIT

log_info "Preparing deployment package in $TEMP_DIR"

# Install Composer dependencies if requested
if [ "$RUN_COMPOSER" = "true" ]; then
    log_info "Installing Composer dependencies..."

    if [ -f "${SCRIPT_DIR}/composer.phar" ]; then
        COMPOSER="${SCRIPT_DIR}/composer.phar"
    elif command -v composer &> /dev/null; then
        COMPOSER="composer"
    else
        log_warning "Composer not found. Skipping dependency installation."
        log_warning "Make sure vendor/ directory is already present or install composer"
    fi

    if [ -n "$COMPOSER" ]; then
        cd "$SCRIPT_DIR"
        php "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
        log_success "Composer dependencies installed"
    fi
fi

# Prepare deployment based on method
if [ "$DEPLOY_METHOD" = "tarball" ]; then
    log_info "Downloading code from GitHub repository: $GITHUB_REPO"

    if [ -z "$GITHUB_REPO" ] || [ -z "$GITHUB_BRANCH" ]; then
        log_error "GITHUB_REPO and GITHUB_BRANCH must be set for tarball deployment"
        exit 1
    fi

    TARBALL_URL="https://github.com/${GITHUB_REPO}/archive/refs/heads/${GITHUB_BRANCH}.tar.gz"
    TARBALL_FILE="${TEMP_DIR}/repo.tar.gz"

    if ! curl -L "$TARBALL_URL" -o "$TARBALL_FILE"; then
        log_error "Failed to download tarball from $TARBALL_URL"
        exit 1
    fi

    # Extract tarball
    tar -xzf "$TARBALL_FILE" -C "$TEMP_DIR" --strip-components=1
    rm "$TARBALL_FILE"

    # Copy vendor if it exists (from local composer install)
    if [ "$RUN_COMPOSER" = "true" ] && [ -d "${SCRIPT_DIR}/vendor" ]; then
        log_info "Copying vendor directory from local installation..."
        cp -r "${SCRIPT_DIR}/vendor" "${TEMP_DIR}/"
    fi

    DEPLOY_SOURCE="$TEMP_DIR/"
else
    # rsync method - deploy from current directory
    DEPLOY_SOURCE="${SCRIPT_DIR}/"
fi

# Build exclude patterns for rsync
EXCLUDE_ARGS=""
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude='$pattern'"
done

# Create backup on remote server if requested
if [ "$CREATE_BACKUP" = "true" ]; then
    log_info "Creating backup on remote server..."
    BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
    ssh $SSH_OPTS "$SSH_CONN" "
        if [ -d '$DEPLOY_PATH' ]; then
            BACKUP_DIR='${DEPLOY_PATH}_backups'
            mkdir -p \$BACKUP_DIR
            cp -r '$DEPLOY_PATH' \$BACKUP_DIR/$BACKUP_NAME
            echo 'Backup created: \$BACKUP_DIR/$BACKUP_NAME'

            # Keep only last 5 backups
            cd \$BACKUP_DIR
            ls -t | tail -n +6 | xargs -r rm -rf
        fi
    "
    log_success "Backup created"
fi

# Create deployment directory on remote server
log_info "Creating deployment directory on remote server..."
ssh $SSH_OPTS "$SSH_CONN" "mkdir -p '$DEPLOY_PATH'"

# Deploy files
log_info "Deploying files to $SSH_CONN:$DEPLOY_PATH..."
eval rsync -avz --delete $SSH_OPTS $EXCLUDE_ARGS \
    -e \"ssh $SSH_OPTS\" \
    "$DEPLOY_SOURCE" \
    "${SSH_CONN}:${DEPLOY_PATH}/"

log_success "Files deployed successfully"

# Post-deployment tasks
log_info "Running post-deployment tasks..."
ssh $SSH_OPTS "$SSH_CONN" "
    cd '$DEPLOY_PATH'

    # Set proper permissions
    find . -type f -exec chmod 644 {} \;
    find . -type d -exec chmod 755 {} \;

    # Ensure database directory is writable
    if [ -d 'data' ]; then
        chmod 775 data
        chmod 664 data/*.db 2>/dev/null || true
    fi

    echo 'Post-deployment tasks completed'
"

log_success "Post-deployment tasks completed"

# Display deployment summary
echo ""
echo "=========================================="
log_success "Deployment completed successfully!"
echo "=========================================="
log_info "Host: $SSH_CONN"
log_info "Path: $DEPLOY_PATH"
log_info "Method: $DEPLOY_METHOD"
echo ""
log_warning "Don't forget to:"
log_warning "  1. Create/update .env file on production server"
log_warning "  2. Initialize the database if needed"
log_warning "  3. Set up authentication credentials"
log_warning "  4. Configure web server (Apache/Nginx) to serve public/ directory"
echo ""
