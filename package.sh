#!/bin/bash

# GDMon Package Script
# Creates a deployment package to transfer to Windows machine

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_NAME="gdmon-deploy-$(date +%Y%m%d_%H%M%S)"
PACKAGE_DIR="${SCRIPT_DIR}/${PACKAGE_NAME}"
OUTPUT_FILE="${SCRIPT_DIR}/${PACKAGE_NAME}.tar.gz"

log_info "Creating deployment package: $PACKAGE_NAME"

# Install Composer dependencies
log_info "Installing Composer dependencies..."
cd "$SCRIPT_DIR"

if [ -f "composer.phar" ]; then
    COMPOSER="php composer.phar"
elif command -v composer &> /dev/null; then
    COMPOSER="composer"
else
    log_error "Composer not found. Please install composer first."
    exit 1
fi

$COMPOSER install --no-dev --optimize-autoloader --no-interaction
log_success "Dependencies installed"

# Create temporary package directory
log_info "Preparing package directory..."
mkdir -p "$PACKAGE_DIR"

# Files and directories to exclude
EXCLUDE_PATTERNS=(
    ".git"
    ".gitignore"
    "tests"
    "*.md"
    ".env"
    ".env.*"
    "deploy.sh"
    "package.sh"
    "deploy.config"
    "deploy.config.example"
    "phpunit.phar"
    "composer.phar"
    "composer.json"
    "composer.lock"
    "graph_test_*.db"
    "*.log"
    "coverrep"
    ".phpunit.cache"
    "backups"
    "*_backups"
    "graph.db"
)

# Build rsync exclude arguments
EXCLUDE_ARGS=""
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude=$pattern"
done

# Copy files to package directory
log_info "Copying files..."
eval rsync -a $EXCLUDE_ARGS "$SCRIPT_DIR/" "$PACKAGE_DIR/"

# Copy deployment scripts and config
log_info "Adding deployment scripts..."
cp "${SCRIPT_DIR}/deploy.ps1" "$PACKAGE_DIR/" 2>/dev/null || log_warning "deploy.ps1 not found, skipping"
cp "${SCRIPT_DIR}/deploy.config.example" "$PACKAGE_DIR/deploy.config.example"

# Create README for the package
cat > "${PACKAGE_DIR}/DEPLOY_README.txt" << 'EOF'
GDMon Deployment Package
========================

This package contains everything needed to deploy GDMon to production,
including all Composer dependencies pre-installed.

DEPLOYMENT STEPS:
-----------------

1. Transfer this package to your Windows computer
   - USB drive, network share, email, cloud storage, etc.

2. Extract the package on Windows:
   - Right-click the .tar.gz file and use 7-Zip/WinRAR
   - Or use PowerShell: tar -xzf gdmon-deploy-XXXXXX.tar.gz

3. Configure deployment:
   - Copy-Item deploy.config.example deploy.config
   - Edit deploy.config with your production server details
   - Required settings: DEPLOY_HOST, DEPLOY_USER, DEPLOY_PATH

4. Deploy using PowerShell:
   - Open PowerShell (no need for Administrator)
   - Navigate to extracted folder: cd path\to\gdmon-deploy-XXXXXX
   - Run: .\deploy.ps1

5. Post-deployment (SSH into production server):
   - Create .env file (copy from .env.example)
   - Initialize database: php public/api.php
   - Set up authentication: php bin/generate_auth.php
   - Configure web server to serve public/ directory
   - Ensure data/ directory is writable: chmod 775 data

REQUIREMENTS:
-------------
- Windows 10+ with OpenSSH client (Settings > Apps > Optional Features)
- SSH access to production server
- tar command (built-in on Windows 10 1803+)
- Production server running PHP 7.4+ with SQLite3

NOTE: If your Windows computer has internet access, you can also clone
the repository directly from GitHub instead of using this package:

  git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git
  cd YOUR_REPO
  Copy-Item deploy.config.example deploy.config
  # Edit deploy.config
  .\deploy.ps1

ALTERNATIVE DEPLOYMENT METHODS:
-------------------------------

If PowerShell doesn't work, you can use:

1. WinSCP (GUI):
   - Install WinSCP (free)
   - Connect to production server
   - Drag and drop all files to deployment directory
   - SSH in and set permissions manually

2. Git Bash:
   - If you have Git for Windows installed
   - Open Git Bash in the extracted folder
   - You can use the Linux deploy.sh script

3. Manual SCP:
   - Use PuTTY's pscp command
   - pscp -r * user@server:/path/to/deployment/

TROUBLESHOOTING:
---------------

PowerShell execution policy error:
  Set-ExecutionPolicy RemoteSigned -Scope CurrentUser

OpenSSH not available:
  Settings > Apps > Optional Features > Add OpenSSH Client

SSH connection fails:
  - Check firewall settings
  - Verify SSH key: ssh -i path\to\key user@server
  - Test connection: ssh user@server "echo test"

Permission denied on production:
  - Ensure SSH user has write access to DEPLOY_PATH
  - Check: ssh user@server "mkdir -p /path/test && rmdir /path/test"

WHAT'S INCLUDED:
---------------
- Complete application source code
- All Composer dependencies (vendor/)
- Deployment script (deploy.ps1)
- Configuration template (deploy.config.example)
- Excludes: tests, .git, development files

EOF

# Create package archive
log_info "Creating archive..."
cd "$SCRIPT_DIR"
tar -czf "$OUTPUT_FILE" -C "$SCRIPT_DIR" "$PACKAGE_NAME"

# Cleanup
rm -rf "$PACKAGE_DIR"

# Calculate package size
PACKAGE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)

# Display summary
echo ""
echo "=========================================="
log_success "Deployment package created!"
echo "=========================================="
log_info "Package: $OUTPUT_FILE"
log_info "Size: $PACKAGE_SIZE"
echo ""
log_info "Next steps:"
echo "  1. Transfer $OUTPUT_FILE to your Windows computer"
echo "  2. Extract the package"
echo "  3. Follow instructions in DEPLOY_README.txt"
echo ""
log_warning "Transfer methods:"
echo "  - USB drive"
echo "  - Network share"
echo "  - Email (if size permits)"
echo "  - Cloud storage (download on Windows)"
echo ""
