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
- `public/api.php`: Entry point, Slim Framework app initialization, routing, middleware
- `Slim Framework 4`: HTTP routing, PSR-7 request/response handling, middleware pipeline
- `Action Classes` (`src/Action/`): Single-responsibility request handlers with validation
- `Graph`: Domain logic layer, audit logging, transaction coordination
- `GraphDatabase`: Data access layer, SQL operations, schema management
- `PHP-DI Container`: Dependency injection for services and actions

**Separation of Concerns**:
- Routing uses **Slim Framework 4**: Industry-standard micro-framework for REST APIs with PSR-7 HTTP messages
- **Single-Action Controllers**: Each endpoint has dedicated action class following Slim 4 best practices
- **PHP-DI Container**: Autowiring and dependency injection for all services
- Database operations in `GraphDatabase` class: All SQL operations separated from business logic

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
- Used only by `Graph` class, never directly by Action classes

**Action Classes** (`src/Action/**/*.php`):
- Single-responsibility controllers: one class per endpoint (Slim 4 best practice)
- Extend `AbstractAction` base class with helper methods
- Receive dependencies via constructor injection (PHP-DI autowiring)
- Validate input using constants (`ALLOWED_STATUSES`, `ALLOWED_CATEGORIES`, `ALLOWED_TYPES`)
- Call `Graph` for business logic (never `GraphDatabase` directly)
- Return PSR-7 Response objects
- Organized by resource: `Node/`, `Edge/`, `Auth/`, `Backup/`, `Audit/`, `Restore/`, `Status/`

**Container** (`src/Container/ContainerFactory.php`):
- Configures PHP-DI dependency injection container
- Registers services: `Graph`, `Authenticator`
- Enables autowiring for automatic dependency resolution
- Actions receive `Graph` instance via constructor injection

**AuditContext** (`src/AuditContext.php`):
- Thread-safe singleton for request-scoped user/IP tracking
- Must be initialized via `set()` at request start in `public/api.php`
- Automatically used by `Graph::audit_log()` if not overridden

**Config** (`src/Config.php`):
- Loads `.env` file and provides configuration access
- Authentication credentials are stored in `.env`, not hardcoded
- Use `.env.example` as template

**Slim Framework 4**:
- Industry-standard micro-framework for building REST APIs
- PSR-7 HTTP message interfaces (Request/Response)
- FastRoute for efficient URL routing
- Middleware pipeline for cross-cutting concerns
- Dependency: `slim/slim:^4.0` and `slim/psr7:^1.6`
- Documentation: https://www.slimframework.com/docs/v4/

### Adding New Routes

To add a new API endpoint, follow the **Action-based pattern** (Slim 4 best practice):

**Step 1: Create an Action Class**

Create a new file in `src/Action/{Resource}/YourAction.php`:

```php
<?php declare(strict_types=1);

namespace Internet\Graph\Action\{Resource};

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class YourAction extends AbstractAction
{
    // Dependencies injected via constructor (autowired by PHP-DI)
    public function __construct(
        private Graph $graph
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // Get route parameters
        $id = urldecode($args['id'] ?? '');

        // Get request body
        $body = $this->getParsedBody($request);

        // Get query parameters
        $queryParams = $this->getQueryParams($request);

        // Validation
        if (!isset($body['field'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required field: field'
            ], 400);
        }

        // Business logic via Graph
        $result = $this->graph->someMethod($id, $body['field']);

        // Return response (middleware adds metadata automatically)
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $result
        ]);
    }
}
```

**Step 2: Add Route in `public/api.php`**

```php
use Internet\Graph\Action\{Resource}\YourAction;

// Simple route
$app->get('/api.php/my/route', YourAction::class);

// Route with parameters
$app->post('/api.php/my/{id}/action', YourAction::class);

// Grouped routes
$app->group('/api.php/my', function (RouteCollectorProxy $group) {
    $group->get('', GetMyResourceAction::class);
    $group->post('', CreateMyResourceAction::class);
    $group->put('/{id}', UpdateMyResourceAction::class);
    $group->delete('/{id}', DeleteMyResourceAction::class);
});
```

**Step 3: Create Tests** (optional but recommended)

Create `tests/Action/{Resource}/YourActionTest.php`:

```php
<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\{Resource}\YourAction;
use Internet\Graph\Graph;
use Slim\Psr7\Response;

class YourActionTest extends TestCase
{
    public function test_your_action_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('someMethod')
            ->willReturn(['result' => 'data']);

        $action = new YourAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['field' => 'value']);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'test']);

        $this->assertEquals(200, $result->getStatusCode());
    }
}
```

**Response Format:**
All responses are automatically wrapped by `ResponseTransformerMiddleware` with metadata:
```json
{
  "success": true,
  "data": {...},
  "meta": {
    "timestamp": "2025-12-30T12:00:00Z",
    "version": "1.0"
  }
}
```

**Error Format:**
Errors handled by `ExceptionHandlerMiddleware`:
```json
{
  "success": false,
  "error": "Error message",
  "meta": {
    "timestamp": "2025-12-30T12:00:00Z",
    "version": "1.0"
  }
}
```

### Data Flow

1. **Request arrives** at `public/api.php`
2. **Slim app initializes**: Config loaded, session started, DI container created
3. **Routing middleware** parses request and matches route (FastRoute)
4. **Error middleware** configured for exception handling
5. **Middleware pipeline executes** (in reverse order of `add()` calls):
   - **ExceptionHandlerMiddleware** catches and formats exceptions as JSON responses
   - **ResponseTransformerMiddleware** wraps successful responses with metadata
   - **CSRF middleware** validates tokens for session-authenticated state-changing requests
   - **Auth middleware** checks authentication, initializes AuditContext
   - **CORS middleware** applies headers to response
   - **OPTIONS handler** returns early for preflight requests
6. **Action class instantiated**: Container autowires dependencies (Graph service)
7. **Action validates** input and calls `Graph` methods
8. **Graph coordinates** business logic, calls `GraphDatabase` operations
9. **Graph logs** operation to audit trail (if state-changing)
10. **Action returns** PSR-7 Response with data
11. **ResponseTransformerMiddleware** wraps response with standard format and metadata
12. **Middleware pipeline unwinds**: CORS headers applied to final response
13. **Slim app emits** response with HTTP status code, headers, and JSON body

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

- Each class has corresponding test file: `GraphTest`, `GraphDatabaseTest`, `AuthenticatorTest`, `AuditContextTest`
- **Action tests**: Each action class tested in isolation with mocked `Graph` dependency (44 tests in `tests/Action/`)
- **Middleware tests**: `ResponseTransformerMiddleware` tested with various response scenarios
- Tests focus on business logic and data layer (Graph/GraphDatabase) and action validation
- Tests use temporary SQLite databases (`graph_test_*.db`) created in `setUp()`, deleted in `tearDown()`
- Use `@codeCoverageIgnore` for exception paths that are difficult to trigger (PDO errors, filesystem failures)
- Transaction rollback paths are typically covered in happy path tests by checking final state
- Test naming: `test_<operation>_<scenario>` (e.g., `test_create_node_success`, `test_create_node_invalid_category`)
- Run all tests: `php phpunit.phar` (164 tests, 401 assertions as of Dec 2025)
- Integration testing done via manual `curl` commands or Slim test harness

### Common Gotchas

1. **Don't call GraphDatabase directly from Actions**: Always use `Graph` as intermediary for business logic and audit logging
2. **Initialize AuditContext**: Auth middleware initializes this automatically, but ensure it's called before Graph operations
3. **JSON encoding**: `GraphDatabase` stores/returns JSON strings in 'data' fields; `Graph` and Action classes work with decoded arrays
4. **Node ID duplication**: The node ID exists both as table primary key AND inside the JSON data field (by design)
5. **Foreign key constraints**: Edges cannot reference non-existent nodes; will throw constraint violation
6. **Status values**: Only use values from `NodeStatus::ALLOWED_VALUES`: unknown, healthy, unhealthy, maintenance
7. **Slim route ordering**: Slim matches routes in definition order. Define more specific routes before generic ones (e.g., `/nodes/{id}/status/history` before `/nodes/{id}/status`)
8. **URL decode parameters**: Always `urldecode($args['id'])` when extracting route parameters that might contain special characters
9. **Response format**: All responses automatically wrapped by `ResponseTransformerMiddleware` with `{"success": true/false, "data": {...}, "meta": {...}}` format
10. **Exception handling**: Throw `GraphException` subclasses (NodeNotFoundException, ValidationException, etc.) for proper error responses with correct HTTP status codes
11. **Middleware order in Slim**: Middleware executes in REVERSE order of `$app->add()` calls. Last added = first executed. ExceptionHandlerMiddleware should be added last (executes first).
12. **PSR-7 responses are immutable**: Always return the modified response: `return $response->withHeader(...)`, not just `$response->withHeader(...)`
13. **Container autowiring**: Action classes receive dependencies via constructor injection. Ensure all dependencies are registered in `ContainerFactory`.
