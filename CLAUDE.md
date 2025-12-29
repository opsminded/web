# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development Server
```bash
# Start development server
php -S localhost:8000 -t public

# Access points:
# - Web UI: http://localhost:8000
# - API: http://localhost:8000/api.php
```

### Testing
```bash
# Run all tests
php phpunit.phar

# Run specific test file
php phpunit.phar tests/GraphTest.php

# Run with coverage report
php phpunit.phar --coverage-html coverrep

# Run specific test method
php phpunit.phar --filter testMethodName
```

### Dependencies
```bash
# Install dependencies
php composer.phar install
# or
composer install
```

### Authentication Setup
```bash
# Generate password hash for Basic Auth users
php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"

# Use bin/generate_auth.php for user management
php bin/generate_auth.php
```

### Deployment

The repository includes deployment scripts for pushing code to production servers without git access. Supports both Linux and Windows deployment environments.

#### Deployment Workflow

**Scenario**: Development on Linux → Deploy from Windows → Production Linux (no internet)

**Method 1: Direct from GitHub (Recommended)**

On Windows computer (with internet access):

```powershell
# 1. Download code from GitHub
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git
# or download ZIP and extract

# 2. Install dependencies (if PHP/Composer available on Windows)
composer install --no-dev --optimize-autoloader

# 3. Configure deployment
Copy-Item deploy.config.example deploy.config
notepad deploy.config  # Edit with production server details

# 4. Deploy to production
.\deploy.ps1
```

**Method 2: Package on Dev Machine**

On development machine (Linux):

```bash
# 1. Create deployment package (includes all dependencies)
./package.sh

# 2. Transfer gdmon-deploy-XXXXXX.tar.gz to Windows computer
#    - USB drive, network share, email, cloud storage
```

On Windows computer:

```powershell
# 3. Extract package
tar -xzf gdmon-deploy-XXXXXX.tar.gz
cd gdmon-deploy-XXXXXX

# 4. Configure deployment
Copy-Item deploy.config.example deploy.config
notepad deploy.config

# 5. Deploy to production
.\deploy.ps1
```

**Method 3: Direct from Linux (if SSH access)**

If you have direct SSH access from your Linux dev machine:

```bash
# 1. Copy and configure deployment settings
cp deploy.config.example deploy.config
nano deploy.config  # Edit with your server details

# 2. Run deployment
./deploy.sh
```

#### Configuration Options

**deploy.config** (works with both Linux and Windows scripts):
- `DEPLOY_HOST`: Production server hostname/IP
- `DEPLOY_USER`: SSH username
- `DEPLOY_PATH`: Deployment directory on server (e.g., `/var/www/gdmon`)
- `DEPLOY_PORT`: SSH port (default: 22)
- `DEPLOY_KEY`: Path to SSH private key (optional)
- `CREATE_BACKUP`: Automatically backup existing deployment (true/false)

Linux-only options (deploy.sh):
- `DEPLOY_METHOD`: Choose `rsync` (faster, incremental) or `tarball` (GitHub download)
- `RUN_COMPOSER`: Install dependencies before deployment
- `GITHUB_REPO`: Your GitHub repository (for tarball method)
- `GITHUB_BRANCH`: Branch to deploy (default: main)

#### Requirements

**Windows deployment**:
- Windows 10+ with OpenSSH client (Settings > Apps > Optional Features)
- SSH access configured to production server
- tar command available (built-in on Windows 10 1803+)

**Linux deployment**:
- SSH access to production server
- rsync installed
- Composer (for dependency installation)

**Production server**:
- PHP 7.4 or higher
- SQLite3 extension
- Writable `data/` directory
- No internet access required

#### Post-Deployment Checklist

After deployment completes, SSH into production server and:

1. **Create `.env` file** (copy from `.env.example`)
   ```bash
   cd /var/www/gdmon
   cp .env.example .env
   nano .env  # Configure database path and settings
   ```

2. **Initialize database** (auto-creates schema on first access)
   ```bash
   php public/api.php  # Makes initial request to create DB
   ```

3. **Set up authentication**
   ```bash
   php bin/generate_auth.php  # Create admin user
   ```

4. **Configure web server** to serve `public/` directory
   - Apache: Point DocumentRoot to `/var/www/gdmon/public`
   - Nginx: Set root to `/var/www/gdmon/public`

5. **Set proper permissions**
   ```bash
   chmod 775 data
   chmod 664 data/*.db
   ```

#### Troubleshooting

**Windows**: If PowerShell script doesn't run:
```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```

**Windows**: If OpenSSH not available, use alternative tools:
- WinSCP (GUI file transfer)
- PuTTY/pscp (command-line SSH)
- Git Bash (run Linux deploy.sh script)

**Linux**: If deployment fails, check SSH key permissions:
```bash
chmod 600 ~/.ssh/id_rsa
```

## Architecture

### Core Design Principles

**Stateless API**: No sessions, all operations are atomic. Authentication uses Bearer tokens or Basic Auth, tracked via `AuditContext` singleton for request-scoped user/IP tracking.

**Layered Architecture**:
- `public/api.php`: Entry point, routing, authentication, HTTP handling
- `ApiHandler`: Business logic layer, validation, response formatting
- `Graph`: Domain logic layer, audit logging, transaction coordination
- `GraphDatabase`: Data access layer, SQL operations, schema management

**Separation of Concerns**: Recently refactored to split database operations into `GraphDatabase` class (see commit 35f0afa). All SQL operations now live in `GraphDatabase`, while `Graph` handles business logic and audit logging.

### Key Classes

**Graph** (`src/Graph.php`):
- Orchestrates operations across nodes, edges, and status
- Handles audit logging for all state changes via `audit_log()` method
- Uses `AuditContext` singleton to track user/IP per request
- Delegates all database operations to `GraphDatabase`
- Transaction coordinator for multi-step operations (e.g., `remove_node` deletes edges first, then node)

**GraphDatabase** (`src/GraphDatabase.php`):
- Pure data access layer with no business logic
- All methods are public and prefixed by operation type (fetch*, insert*, update*, delete*)
- Returns raw data structures (arrays with 'data' as JSON strings)
- Manages schema initialization and database connection
- Used only by `Graph` class, never directly by `ApiHandler`

**ApiHandler** (`src/ApiHandler.php`):
- Validates input (e.g., checks category/type against allowed constants)
- Returns structured arrays with 'success', 'error', 'code' keys
- Never calls `GraphDatabase` directly - always through `Graph`
- Contains validation constants: `ALLOWED_STATUSES`, `ALLOWED_CATEGORIES`, `ALLOWED_TYPES`

**AuditContext** (`src/AuditContext.php`):
- Thread-safe singleton for request-scoped user/IP tracking
- Must be initialized via `set()` at request start in `public/api.php`
- Automatically used by `Graph::audit_log()` if not overridden

**Config** (`src/Config.php`):
- Loads `.env` file and provides configuration access
- Authentication credentials are stored in `.env`, not hardcoded
- Use `.env.example` as template

### Data Flow

1. **Request arrives** at `public/api.php`
2. **Authentication** via `Authenticator` (Bearer token or Basic Auth)
3. **AuditContext initialized** with authenticated user and IP
4. **Route parsing** and dispatch to `ApiHandler` method
5. **ApiHandler validates** input and calls `Graph` method
6. **Graph coordinates** business logic, calls `GraphDatabase` operations
7. **Graph logs** operation to audit trail (if state-changing)
8. **ApiHandler formats** response with success/error structure
9. **Response sent** as JSON with appropriate HTTP status code

### Database Schema

All tables use SQLite with the following structure:
- **nodes**: `id` (TEXT PK), `data` (TEXT/JSON), `created_at`, `updated_at`
- **edges**: `id` (TEXT PK), `source`, `target`, `data` (TEXT/JSON), timestamps
  - Foreign keys to nodes with CASCADE delete
- **audit_log**: Tracks all operations with `entity_type`, `entity_id`, `action`, `old_data`, `new_data`, `user_id`, `ip_address`, `created_at`
- **node_status**: Status history with `node_id`, `status`, `created_at`

Indexes exist on:
- `audit_log(entity_type, entity_id)` and `audit_log(created_at)`
- `node_status(node_id)` and `node_status(created_at)`

### Critical Patterns

**Node Data Structure**: Nodes store arbitrary JSON in the `data` column. The `id` field is duplicated in both the table column and inside the JSON data for convenience.

**Edge Operations**: When a node is deleted, all associated edges are automatically deleted via CASCADE, but `Graph.remove_node()` explicitly fetches and logs each edge deletion before removing the node for audit completeness.

**Audit Logging**: Every state-changing operation calls `Graph::audit_log()` with:
- `old_data`: State before operation (null for creates)
- `new_data`: State after operation (null for deletes)
- User/IP automatically pulled from `AuditContext` if not provided

**Transaction Handling**: Multi-step operations use `GraphDatabase::beginTransaction()`, `commit()`, and `rollBack()`. Always wrapped in try-catch in `Graph` class.

**Backup Before Restore**: Both `restore_entity()` and `restore_to_timestamp()` automatically create a backup before performing the restore operation.

**Status Tracking**: Node status is append-only history table. Use `get_node_status()` for current status, `get_node_status_history()` for full timeline.

### Testing Approach

- Each class has corresponding test file: `GraphTest`, `ApiHandlerTest`, `GraphDatabaseTest`, `AuthenticatorTest`, `AuditContextTest`
- Tests use temporary SQLite databases (`graph_test_*.db`) created in `setUp()`, deleted in `tearDown()`
- Use `@codeCoverageIgnore` for exception paths that are difficult to trigger (PDO errors, filesystem failures)
- Transaction rollback paths are typically covered in happy path tests by checking final state
- Test naming: `test_<operation>_<scenario>` (e.g., `test_add_node_success`, `test_add_node_duplicate`)

### Common Gotchas

1. **Don't call GraphDatabase directly from ApiHandler**: Always use `Graph` as intermediary
2. **Initialize AuditContext**: Must call `AuditContext::set($user, $ip)` before any Graph operations that log
3. **JSON encoding**: `GraphDatabase` stores/returns JSON strings in 'data' fields; `Graph` and `ApiHandler` work with decoded arrays
4. **Node ID duplication**: The node ID exists both as table primary key AND inside the JSON data field (by design)
5. **Foreign key constraints**: Edges cannot reference non-existent nodes; will throw constraint violation
6. **Status values**: Only use values from `NodeStatus::ALLOWED_VALUES`: unknown, healthy, unhealthy, maintenance
