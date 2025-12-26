# Security Fixes Applied

This document outlines the critical security improvements made to the GDMON system.

## Critical Issues Fixed

### 1. ✅ CSS File Path Mismatch
**Fixed:** `index.html` now correctly references `/css/style.css`
- **Impact:** Styling now loads properly

### 2. ✅ Environment-Based Configuration
**Fixed:** Credentials moved from hardcoded values to `.env` file

**Changes:**
- Created `src/Config.php` for configuration management
- Created `.env.example` as template
- Created `.env` with default values (MUST BE CHANGED IN PRODUCTION!)
- Updated `.gitignore` to exclude sensitive files

**How to configure:**
1. Edit `/home/tarcisio/projects/gdmon/web/.env`
2. Generate secure password hash:
   ```bash
   php -r "echo password_hash('your_secure_password', PASSWORD_DEFAULT);"
   ```
3. Update `AUTH_USERS` with username:hash
4. Generate secure bearer tokens:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
5. Update `AUTH_BEARER_TOKENS` with token:user_identifier

### 3. ✅ Session-Based Authentication
**Fixed:** Replaced insecure in-memory credential storage with secure sessions

**New Features:**
- Session-based authentication with secure cookies
- Login/logout endpoints
- CSRF token generation and validation
- Session timeout (configurable via `.env`)
- Automatic session regeneration on authentication

**New API Endpoints:**
- `POST /api.php/auth/login` - Login with username/password
- `POST /api.php/auth/logout` - Logout
- `GET /api.php/auth/status` - Check authentication status
- `GET /api.php/auth/csrf` - Get CSRF token

**Frontend Changes:**
- Login modal instead of browser prompts
- User status display in controls panel
- Logout button
- Automatic authentication check on page load

### 4. ✅ CSRF Protection
**Fixed:** Added CSRF token validation for all state-changing operations

**How it works:**
- CSRF token generated on login
- Token stored in session
- Frontend sends token in `X-CSRF-Token` header
- API validates token for POST/PUT/DELETE/PATCH requests
- Bearer token authentication bypasses CSRF (for API automation)

**Implementation:**
- Session-based authentication: CSRF required
- Bearer token authentication: CSRF not required (tokens are secret)
- Anonymous GET requests: CSRF not required (read-only)

## Additional Security Improvements

### Secure Session Configuration
- `httponly` cookies (JavaScript cannot access)
- `SameSite=Strict` (CSRF mitigation)
- Secure cookies in production (HTTPS only)
- Session timeout (default: 1 hour)
- Session ID regeneration on authentication
- Automatic session expiration

### Configuration Security
- Sensitive data in `.env` (not in git)
- Environment-specific settings
- CORS configuration via environment
- Database path configurable

## Migration Guide

### For Existing Deployments

1. **Backup your database:**
   ```bash
   cp graph.db graph.db.backup
   ```

2. **Create `.env` file:**
   ```bash
   cp .env.example .env
   ```

3. **Generate secure credentials:**
   ```bash
   # Generate password hash
   php -r "echo password_hash('YOUR_SECURE_PASSWORD', PASSWORD_DEFAULT) . PHP_EOL;"

   # Generate bearer token
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```

4. **Edit `.env` and update:**
   - `AUTH_USERS` with your username and password hash
   - `AUTH_BEARER_TOKENS` with your secure token
   - `APP_ENV` to `production` for production environments

5. **Set proper file permissions:**
   ```bash
   chmod 600 .env
   chmod 755 public/
   chmod 644 public/*.php
   ```

6. **Clear any existing sessions:**
   ```bash
   # Find PHP session directory
   php -r "echo session_save_path();"
   # Remove old sessions if needed
   ```

7. **Test the changes:**
   - Access the application
   - Try logging in with new credentials
   - Verify CSRF protection works
   - Test Bearer token authentication (if used)

### For New Deployments

1. Copy `.env.example` to `.env`
2. Generate secure credentials (see above)
3. Configure web server (Apache/Nginx)
4. Ensure `public/` is the web root
5. Test authentication

## Default Credentials

**⚠️ IMPORTANT: Change these immediately!**

Default username: `admin`
Default password: `password`

The default password hash in `.env` corresponds to the password "password".
**This MUST be changed before deploying to production!**

## Web Server Configuration

### Apache (.htaccess in parent directory)
```apache
# Deny access to sensitive files
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>

<FilesMatch "^\.git">
    Require all denied
</FilesMatch>

<FilesMatch "^composer\.(json|lock)">
    Require all denied
</FilesMatch>

# Only allow access to public directory
RedirectMatch 404 ^/(?!public/)
```

### Nginx
```nginx
location ~ /\.env {
    deny all;
    return 404;
}

location ~ /\.git {
    deny all;
    return 404;
}

location ~ /composer\.(json|lock) {
    deny all;
    return 404;
}

# Only serve files from public directory
root /path/to/gdmon/web/public;
```

## Testing the Security Fixes

### Test Authentication
```bash
# Test login
curl -X POST http://localhost/api.php/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}' \
  -c cookies.txt

# Test authenticated request with CSRF
curl -X POST http://localhost/api.php/nodes \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_TOKEN_HERE" \
  -b cookies.txt \
  -d '{"id":"test","data":{"category":"infrastructure","type":"server"}}'

# Test logout
curl -X POST http://localhost/api.php/auth/logout \
  -H "X-CSRF-Token: YOUR_TOKEN_HERE" \
  -b cookies.txt
```

### Test Bearer Token (API Automation)
```bash
# Test with bearer token (no CSRF needed)
curl -X POST http://localhost/api.php/nodes \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \
  -d '{"id":"test2","data":{"category":"infrastructure","type":"server"}}'
```

## Security Checklist

- [ ] Change default password in `.env`
- [ ] Generate strong bearer tokens
- [ ] Set `APP_ENV=production` in production
- [ ] Ensure `.env` is not in git (check `.gitignore`)
- [ ] Set proper file permissions (600 for `.env`)
- [ ] Configure web server to block access to sensitive files
- [ ] Enable HTTPS in production
- [ ] Consider adding rate limiting (see README)
- [ ] Review and customize CORS settings
- [ ] Set up database backups
- [ ] Monitor audit logs for suspicious activity

## Backward Compatibility

The system maintains backward compatibility:
- Bearer token authentication still works (for automation)
- Basic Auth header still works (for legacy clients)
- All existing API endpoints remain functional
- New authentication is additive, not breaking

Existing automation scripts using Bearer tokens will continue to work without changes.

## Support

For issues or questions about these security fixes:
1. Check this document first
2. Review the main README.md
3. Check the `.env.example` for configuration options
4. Report issues at: https://github.com/anthropics/gdmon/issues

## Next Steps

Consider implementing these additional security measures:
1. Rate limiting (prevent brute force attacks)
2. IP whitelisting for sensitive operations
3. Two-factor authentication (2FA)
4. API key rotation policies
5. Audit log monitoring and alerts
6. Database encryption at rest
7. Regular security audits
8. Automated security scanning
