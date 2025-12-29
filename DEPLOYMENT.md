# GDMon Deployment Guide

Quick reference guide for deploying GDMon to production servers.

## Deployment Scenario

- **Development**: Linux machine with internet access
- **Deployment source**: Windows computer with internet access
- **Production**: Linux server without internet access (SSH accessible from Windows)

## Quick Start (Recommended Method)

### On Windows Computer

1. **Clone repository**
   ```powershell
   git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git gdmon
   cd gdmon
   ```

2. **Configure deployment**
   ```powershell
   Copy-Item deploy.config.example deploy.config
   notepad deploy.config
   ```

   Edit these required fields:
   - `DEPLOY_HOST=your-production-server.com`
   - `DEPLOY_USER=your-ssh-username`
   - `DEPLOY_PATH=/var/www/gdmon`

3. **Deploy**
   ```powershell
   .\deploy.ps1
   ```

4. **Post-deployment setup** (on production server via SSH)
   ```bash
   cd /var/www/gdmon

   # Create environment config
   cp .env.example .env
   nano .env

   # Create admin user
   php bin/generate_auth.php

   # Set permissions
   chmod 775 data
   chmod 664 data/*.db
   ```

## Alternative Methods

### Method 1: Package Transfer (No Git on Windows)

**On Linux (dev machine)**:
```bash
./package.sh
# Transfer gdmon-deploy-XXXXXX.tar.gz to Windows (USB/network share)
```

**On Windows**:
```powershell
tar -xzf gdmon-deploy-XXXXXX.tar.gz
cd gdmon-deploy-XXXXXX
Copy-Item deploy.config.example deploy.config
notepad deploy.config
.\deploy.ps1
```

### Method 2: Direct Linux Deployment

If you have SSH access from Linux dev machine:
```bash
cp deploy.config.example deploy.config
nano deploy.config
./deploy.sh
```

### Method 3: Manual Deployment (GUI)

1. Download code as ZIP from GitHub on Windows
2. Extract files
3. Use **WinSCP** to transfer to production server
4. SSH into server and set permissions manually

## Configuration Reference

### deploy.config

```bash
# Required settings
DEPLOY_HOST="production-server.example.com"
DEPLOY_USER="deploy-user"
DEPLOY_PATH="/var/www/gdmon"

# Optional settings
DEPLOY_PORT="22"                    # SSH port
DEPLOY_KEY=""                       # Path to SSH private key
CREATE_BACKUP="true"                # Backup before deployment

# Linux script only (deploy.sh)
DEPLOY_METHOD="rsync"               # or "tarball"
RUN_COMPOSER="true"                 # Install dependencies
GITHUB_REPO="username/repo"         # For tarball method
GITHUB_BRANCH="main"                # For tarball method
```

## Prerequisites

### Windows Computer
- Windows 10 version 1803 or later
- OpenSSH Client installed (Settings > Apps > Optional Features > OpenSSH Client)
- Git for Windows (optional, for cloning repo)

To check if OpenSSH is available:
```powershell
ssh -V
scp -h
```

To install if missing:
```powershell
# Run as Administrator
Add-WindowsCapability -Online -Name OpenSSH.Client~~~~0.0.1.0
```

### Production Server
- PHP 7.4 or higher
- SQLite3 PHP extension
- Apache or Nginx web server
- Writable `data/` directory

## Common Issues

### Windows: PowerShell Script Won't Run

**Error**: "execution of scripts is disabled on this system"

**Solution**:
```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Windows: SSH Connection Fails

**Test connection manually**:
```powershell
ssh user@production-server.com
```

**If using SSH key**:
```powershell
ssh -i C:\path\to\private_key user@production-server.com
```

**Verify key permissions** (should be readable only by you):
- Right-click key file > Properties > Security
- Remove all users except your account
- Your account should have "Read" permission only

### Production: Permission Denied

**Ensure deployment directory is writable**:
```bash
# On production server
mkdir -p /var/www/gdmon
chown your-user:your-group /var/www/gdmon
chmod 755 /var/www/gdmon
```

### Production: Database Errors

**Create data directory**:
```bash
mkdir -p /var/www/gdmon/data
chmod 775 /var/www/gdmon/data
chown www-data:www-data /var/www/gdmon/data  # Apache
# or
chown nginx:nginx /var/www/gdmon/data  # Nginx
```

## Web Server Configuration

### Apache

```apache
<VirtualHost *:80>
    ServerName gdmon.example.com
    DocumentRoot /var/www/gdmon/public

    <Directory /var/www/gdmon/public>
        AllowOverride All
        Require all granted

        # Enable URL rewriting if needed
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/gdmon_error.log
    CustomLog ${APACHE_LOG_DIR}/gdmon_access.log combined
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name gdmon.example.com;
    root /var/www/gdmon/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Deployment Checklist

- [ ] Configure deploy.config with production server details
- [ ] Test SSH connection from Windows to production
- [ ] Run deployment script (deploy.ps1 or deploy.sh)
- [ ] Create .env file on production server
- [ ] Generate authentication credentials
- [ ] Configure web server
- [ ] Set proper file permissions (data/ directory)
- [ ] Test application access via web browser
- [ ] Verify database is created and writable
- [ ] Test API endpoints
- [ ] Check application logs for errors

## Security Notes

- **Never commit** `deploy.config` or `.env` files to version control
- Use SSH keys instead of passwords for authentication
- Restrict SSH key file permissions (read-only for owner)
- Keep production `.env` file secure with database credentials
- Regularly backup the `data/` directory
- Use HTTPS in production (Let's Encrypt recommended)

## Getting Help

For issues or questions:
1. Check DEPLOYMENT.md (this file)
2. See CLAUDE.md for architecture details
3. Review DEPLOY_README.txt in deployment package
4. Check deployment script output for specific errors
