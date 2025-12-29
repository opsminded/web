# GDMon Deployment Script for Windows
# Deploys the application to a production server via SSH

param(
    [switch]$Help
)

# Colors for output
function Write-Info {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Blue
}

function Write-Success {
    param([string]$Message)
    Write-Host "[SUCCESS] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

if ($Help) {
    Write-Host @"
GDMon Deployment Script for Windows

USAGE:
    .\deploy.ps1

PREREQUISITES:
    1. OpenSSH client installed (built-in on Windows 10+)
    2. SSH access configured to production server
    3. deploy.config file configured with server details

SETUP:
    1. Copy deploy.config.example to deploy.config
    2. Edit deploy.config with your server settings
    3. Run this script

CONFIGURATION (deploy.config):
    DEPLOY_HOST=your-server.com
    DEPLOY_USER=your-username
    DEPLOY_PATH=/var/www/gdmon
    DEPLOY_PORT=22
    DEPLOY_KEY=C:\path\to\ssh\key (optional)
    CREATE_BACKUP=true

For more information, see DEPLOY_README.txt
"@
    exit 0
}

# Script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $ScriptDir "deploy.config"

Write-Info "GDMon Deployment Script"
Write-Host ""

# Check if config file exists
if (-not (Test-Path $ConfigFile)) {
    Write-Error "Configuration file not found: $ConfigFile"
    Write-Info "Please copy deploy.config.example to deploy.config and customize it"
    Write-Info "  Copy-Item deploy.config.example deploy.config"
    exit 1
}

# Load configuration
Write-Info "Loading configuration from $ConfigFile"
$Config = @{}
Get-Content $ConfigFile | ForEach-Object {
    $line = $_.Trim()
    # Skip comments and empty lines
    if ($line -and -not $line.StartsWith("#")) {
        # Remove quotes and split on =
        $parts = $line -replace '"', '' -replace "'", '' -split '=', 2
        if ($parts.Count -eq 2) {
            $key = $parts[0].Trim()
            $value = $parts[1].Trim()
            $Config[$key] = $value
        }
    }
}

# Validate required configuration
$RequiredKeys = @("DEPLOY_HOST", "DEPLOY_USER", "DEPLOY_PATH")
$MissingKeys = $RequiredKeys | Where-Object { -not $Config.ContainsKey($_) -or -not $Config[$_] }

if ($MissingKeys) {
    Write-Error "Missing required configuration: $($MissingKeys -join ', ')"
    exit 1
}

$DeployHost = $Config["DEPLOY_HOST"]
$DeployUser = $Config["DEPLOY_USER"]
$DeployPath = $Config["DEPLOY_PATH"]
$DeployPort = if ($Config["DEPLOY_PORT"]) { $Config["DEPLOY_PORT"] } else { "22" }
$DeployKey = $Config["DEPLOY_KEY"]
$CreateBackup = $Config["CREATE_BACKUP"] -eq "true"

# Build SSH connection string
$SshConn = "$DeployUser@$DeployHost"
$SshOpts = @("-p", $DeployPort)
if ($DeployKey -and (Test-Path $DeployKey)) {
    $SshOpts += @("-i", $DeployKey)
}

# Check if OpenSSH is available
try {
    $null = Get-Command ssh -ErrorAction Stop
    $null = Get-Command scp -ErrorAction Stop
} catch {
    Write-Error "OpenSSH client not found. Please install OpenSSH:"
    Write-Info "  Settings > Apps > Optional Features > Add OpenSSH Client"
    Write-Info "Or use WinSCP/PuTTY for manual deployment"
    exit 1
}

# Test SSH connection
Write-Info "Testing SSH connection to $SshConn..."
$TestCmd = @("ssh") + $SshOpts + @($SshConn, "echo 'Connection successful'")
$TestResult = & $TestCmd[0] $TestCmd[1..($TestCmd.Length-1)] 2>&1

if ($LASTEXITCODE -ne 0) {
    Write-Error "Cannot connect to $SshConn"
    Write-Info "Please check your SSH configuration and credentials"
    Write-Info "Test manually: ssh $($SshOpts -join ' ') $SshConn"
    exit 1
}
Write-Success "SSH connection successful"

# Create backup on remote server if requested
if ($CreateBackup) {
    Write-Info "Creating backup on remote server..."
    $BackupName = "backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    $BackupScript = @"
if [ -d '$DeployPath' ]; then
    BACKUP_DIR='${DeployPath}_backups'
    mkdir -p \`$BACKUP_DIR
    cp -r '$DeployPath' \`$BACKUP_DIR/$BackupName
    echo 'Backup created: \`$BACKUP_DIR/$BackupName'

    # Keep only last 5 backups
    cd \`$BACKUP_DIR
    ls -t | tail -n +6 | xargs -r rm -rf
else
    echo 'No existing deployment to backup'
fi
"@
    $BackupCmd = @("ssh") + $SshOpts + @($SshConn, $BackupScript)
    & $BackupCmd[0] $BackupCmd[1..($BackupCmd.Length-1)]
    Write-Success "Backup completed"
}

# Create deployment directory on remote server
Write-Info "Creating deployment directory on remote server..."
$MkdirCmd = @("ssh") + $SshOpts + @($SshConn, "mkdir -p '$DeployPath'")
& $MkdirCmd[0] $MkdirCmd[1..($MkdirCmd.Length-1)]

# Deploy files using SCP
Write-Info "Deploying files to $SshConn`:$DeployPath..."
Write-Info "This may take a few moments..."

# Use scp to copy all files
# First, create a tar archive to transfer (faster than individual files)
$TempArchive = Join-Path $env:TEMP "gdmon-deploy.tar.gz"
$ExcludePatterns = @(
    ".git",
    "tests",
    "*.md",
    ".env",
    ".env.*",
    "deploy.sh",
    "deploy.ps1",
    "package.sh",
    "deploy.config",
    "phpunit.phar",
    "composer.phar",
    "composer.json",
    "composer.lock",
    "graph_test_*.db",
    "*.log",
    "coverrep",
    ".phpunit.cache",
    "backups",
    "*_backups",
    "graph.db",
    "DEPLOY_README.txt"
)

# Check if tar is available (Windows 10 1803+)
try {
    $null = Get-Command tar -ErrorAction Stop

    # Create tar archive
    Write-Info "Creating archive..."
    $ExcludeArgs = $ExcludePatterns | ForEach-Object { "--exclude=$_" }
    Push-Location $ScriptDir
    $TarCmd = @("tar", "-czf", $TempArchive) + $ExcludeArgs + @(".")
    & $TarCmd[0] $TarCmd[1..($TarCmd.Length-1)]
    Pop-Location

    # Transfer archive
    Write-Info "Transferring archive..."
    $ScpCmd = @("scp") + $SshOpts + @($TempArchive, "${SshConn}:/tmp/gdmon-deploy.tar.gz")
    & $ScpCmd[0] $ScpCmd[1..($ScpCmd.Length-1)]

    # Extract on remote server
    Write-Info "Extracting on remote server..."
    $ExtractScript = @"
cd '$DeployPath'
tar -xzf /tmp/gdmon-deploy.tar.gz
rm /tmp/gdmon-deploy.tar.gz
"@
    $ExtractCmd = @("ssh") + $SshOpts + @($SshConn, $ExtractScript)
    & $ExtractCmd[0] $ExtractCmd[1..($ExtractCmd.Length-1)]

    # Cleanup local archive
    Remove-Item $TempArchive -ErrorAction SilentlyContinue

} catch {
    Write-Warning "tar command not available, falling back to directory copy"
    Write-Info "This may take longer..."

    # Fallback: use scp -r (slower but works)
    $ScpCmd = @("scp", "-r") + $SshOpts + @("$ScriptDir\*", "${SshConn}:${DeployPath}/")
    & $ScpCmd[0] $ScpCmd[1..($ScpCmd.Length-1)]
}

if ($LASTEXITCODE -ne 0) {
    Write-Error "File transfer failed"
    exit 1
}

Write-Success "Files deployed successfully"

# Post-deployment tasks
Write-Info "Running post-deployment tasks..."
$PostDeployScript = @"
cd '$DeployPath'

# Set proper permissions
find . -type f -exec chmod 644 {} \; 2>/dev/null || true
find . -type d -exec chmod 755 {} \; 2>/dev/null || true

# Ensure database directory is writable
if [ -d 'data' ]; then
    chmod 775 data
    chmod 664 data/*.db 2>/dev/null || true
fi

echo 'Post-deployment tasks completed'
"@

$PostCmd = @("ssh") + $SshOpts + @($SshConn, $PostDeployScript)
& $PostCmd[0] $PostCmd[1..($PostCmd.Length-1)]

Write-Success "Post-deployment tasks completed"

# Display deployment summary
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Success "Deployment completed successfully!"
Write-Host "==========================================" -ForegroundColor Cyan
Write-Info "Host: $SshConn"
Write-Info "Path: $DeployPath"
Write-Host ""
Write-Warning "Don't forget to:"
Write-Warning "  1. Create/update .env file on production server"
Write-Warning "  2. Initialize the database if needed"
Write-Warning "  3. Set up authentication credentials"
Write-Warning "  4. Configure web server (Apache/Nginx) to serve public/ directory"
Write-Host ""
Write-Info "To verify deployment, SSH to the server:"
Write-Info "  ssh $($SshOpts -join ' ') $SshConn"
Write-Info "  cd $DeployPath"
Write-Host ""
