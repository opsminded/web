# Authentication Setup Guide

The Graph API uses stateless authentication with three modes:
1. **Anonymous** - For read-only operations (GET requests)
2. **Basic Auth** - For authenticated users
3. **Bearer Token** - For automation systems

## Anonymous Access (No Authentication Required)

For **read-only operations**, no authentication is required:

```bash
# Get the entire graph - no auth needed
curl http://localhost/api.php/graph

# Check if a node exists - no auth needed
curl http://localhost/api.php/nodes/person1

# View audit history - no auth needed
curl http://localhost/api.php/audit
```

**Important:** Anonymous requests are logged in the audit trail as user `"anonymous"` with the client's IP address.

### When is Authentication Required?

Authentication is **required** for state-changing operations:
- **POST** - Creating resources
- **PUT** - Updating resources
- **PATCH** - Partial updates
- **DELETE** - Deleting resources

Authentication is **NOT required** for:
- **GET** - Reading/viewing data

## Quick Start (For Authenticated Access)

### 1. Generate Credentials

Run the credential generator:

```bash
php generate_auth.php
```

This interactive script helps you:
- Generate password hashes for Basic Auth users
- Generate random bearer tokens for automations
- Batch create multiple users

### 2. Configure api.php

Edit the top of `api.php` to add your credentials:

#### Add Basic Auth Users

```php
$VALID_USERS = [
    'admin' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password: 'password'
    'john' => '$2y$10$...',  // Add your generated hash here
    'jane' => '$2y$10$...',  // Add your generated hash here
];
```

**Important:** Never store plain-text passwords! Always use `password_hash()`.

#### Add Bearer Tokens for Automation

```php
$VALID_BEARER_TOKENS = [
    'automation_token_123456789' => 'automation_bot',
    'ci_cd_token_987654321' => 'ci_cd_system',
    'abc123def456...' => 'my_script_name',  // Add your generated token here
];
```

The value (right side) is used as the `user_id` in audit logs.

## Manual Credential Generation

### Generate Password Hash (PHP)

```php
<?php
$password = 'my_secure_password';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
```

### Generate Bearer Token (Bash)

```bash
# 32-byte (64 character) random token
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Generate Bearer Token (PHP)

```php
<?php
$token = bin2hex(random_bytes(32));
echo $token;
```

## Testing Authentication

### Test with cURL

#### Basic Auth
```bash
curl -u "admin:password" http://localhost/api.php/graph
```

#### Bearer Token
```bash
curl -H "Authorization: Bearer automation_token_123456789" \
  http://localhost/api.php/graph
```

### Test with PHP Script

```bash
# Test with Basic Auth
php test_api.php basic

# Test with Bearer Token
php test_api.php bearer

# Test with Anonymous (read-only)
php test_api.php anonymous
```

## Security Best Practices

### For Anonymous Access:
1. **Understand what's exposed** - Anyone can read your graph data via GET requests
2. **Use firewall rules** - If you need private data, restrict IP access or disable anonymous access
3. **Monitor audit logs** - Track who's accessing your data (IP addresses are logged)
4. **Sensitive data** - If your graph contains sensitive information, disable anonymous access by modifying the API
5. **Consider rate limiting** - Prevent abuse of anonymous endpoints

**To disable anonymous access:**
Edit `api.php` and change:
```php
$requires_auth = !$is_read_only;
```
to:
```php
$requires_auth = true; // Always require authentication
```

### For Basic Auth:
1. **Use strong passwords** - At least 12 characters with mixed case, numbers, and symbols
2. **Use HTTPS in production** - Basic Auth credentials are base64-encoded, not encrypted
3. **Rotate passwords regularly** - Change passwords every 90 days
4. **Don't share accounts** - Each user should have their own credentials

### For Bearer Tokens:
1. **Use long random tokens** - Minimum 32 bytes (64 hex characters)
2. **Store tokens securely** - Use environment variables or secure config files
3. **Rotate tokens regularly** - Especially after suspected compromise
4. **Use HTTPS in production** - Tokens are sent in plain text
5. **One token per automation** - Don't share tokens between systems
6. **Audit token usage** - Check audit logs for suspicious activity

### General:
1. **Enable HTTPS** - Always use SSL/TLS in production
2. **Restrict IP access** - Use firewall rules or `.htaccess` to limit API access
3. **Monitor audit logs** - Regularly review who's accessing what
4. **Set up backups** - Use the backup API before making major changes
5. **Keep PHP updated** - Ensure you're using a supported PHP version

## Example Configurations

### Development Environment

```php
// api.php - Development Config
$VALID_USERS = [
    'dev' => '$2y$10$...',  // Simple password for local testing
];

$VALID_BEARER_TOKENS = [
    'dev_token' => 'local_automation',
];
```

### Production Environment

```php
// api.php - Production Config
$VALID_USERS = [
    'admin' => '$2y$10$...',           // Strong password
    'api_user' => '$2y$10$...',        // Strong password
    'backup_user' => '$2y$10$...',     // Strong password - read-only access
];

$VALID_BEARER_TOKENS = [
    // Long, random tokens from environment variables
    'a3f8d9c2e1b4567890abcdef1234567890abcdef1234567890abcdef123456' => 'backup_automation',
    'b7e6d5c4a3210987654321fedcba0987654321fedcba0987654321fedcba09' => 'monitoring_system',
    'c9a8b7c6d5e4f3210abcdef1234567890fedcba0987654321abcdef123456' => 'ci_cd_pipeline',
];
```

**Tip:** In production, load tokens from environment variables:

```php
$VALID_BEARER_TOKENS = [
    getenv('BACKUP_TOKEN') => 'backup_automation',
    getenv('MONITORING_TOKEN') => 'monitoring_system',
    getenv('CI_CD_TOKEN') => 'ci_cd_pipeline',
];
```

## Audit Logging

All authenticated requests are automatically logged with:
- **user_id**: Username (Basic Auth) or automation name (Bearer Token)
- **ip_address**: Client IP (supports X-Forwarded-For for proxies)
- **action**: create, update, delete, etc.
- **entity_type**: node, edge, system
- **entity_id**: The ID of the affected entity
- **timestamp**: When the action occurred

View audit logs:
```bash
curl -u "admin:password" "http://localhost/api.php/audit"
```

Filter by entity:
```bash
curl -u "admin:password" "http://localhost/api.php/audit?entity_type=node&entity_id=node1"
```

## Troubleshooting

### "Authentication required" error

**Cause:** No authentication header provided or invalid credentials

**Solution:**
- Check you're sending the `Authorization` header
- Verify credentials are correct
- For Basic Auth: Check username and password
- For Bearer Token: Check token is in `$VALID_BEARER_TOKENS`

### "401 Unauthorized" with correct credentials

**Possible causes:**
1. Web server not passing Authorization header
   - Add to `.htaccess`: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`
2. Password hash doesn't match
   - Regenerate hash with `password_hash()`
3. Token typo
   - Copy/paste token carefully, no extra spaces

### Bearer token not working

**Check:**
1. Token is in `$VALID_BEARER_TOKENS` array
2. Header format: `Authorization: Bearer <token>` (with space after "Bearer")
3. No extra whitespace in token string
4. Token is not expired (if you added expiration logic)

## Next Steps

After setting up authentication:
1. Read the [API Reference](API_REFERENCE.md) for endpoint documentation
2. Test the API with `php test_api.php`
3. Set up HTTPS for production
4. Configure regular backups
5. Set up monitoring for audit logs
