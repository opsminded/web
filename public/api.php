<?php declare(strict_types=1);

/**
 * REST API for Graph library - Public Entry Point
 *
 * Authentication Modes:
 * 1. Anonymous - GET requests can be made without authentication
 *                Logged in audit trail as user 'anonymous'
 *
 * 2. Basic Auth - Username/password authentication for all operations
 *                 Configure users in $valid_users array below
 *
 * 3. Bearer Token - Token-based authentication for automations
 *                   Configure tokens in $valid_bearer_tokens array below
 *
 * State-changing operations (POST, PUT, DELETE, PATCH) REQUIRE authentication.
 * Read-only operations (GET) can be performed anonymously.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Internet\Graph\Graph;
use Internet\Graph\AuditContext;
use Internet\Graph\ApiHandler;
use Internet\Graph\Authenticator;

// Configuration: Valid Bearer Tokens for automation
// Format: 'token' => 'user_identifier'
$valid_bearer_tokens = [
    'automation_token_123456789' => 'automation_bot',
    'ci_cd_token_987654321' => 'ci_cd_system',
    // Add more automation tokens here
];

// Configuration: Valid Basic Auth credentials
// Format: 'username' => 'password_hash'
// Generate hash with: password_hash('your_password', PASSWORD_DEFAULT)
$valid_users = [
    'admin' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password: 'password'
    // Add more users here
];

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize authenticator
$authenticator = new Authenticator($valid_bearer_tokens, $valid_users);

// Authenticate the request
$user_id = $authenticator->authenticate();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Allow anonymous access for read-only operations (GET)
// Require authentication for state-changing operations (POST, PUT, DELETE, PATCH)
$is_read_only = ($method === 'GET');
$requires_auth = !$is_read_only;

if ($requires_auth && $user_id === null) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Authentication required',
        'message' => 'Authentication is required for ' . $method . ' requests. Please provide valid Basic Auth credentials or Bearer token'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// If no authentication provided for read-only request, use 'anonymous'
if ($user_id === null) {
    $user_id = 'anonymous';
}

// Initialize audit context (stateless - no session)
$ip_address = null;
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_address = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $ip_address = $_SERVER['HTTP_X_REAL_IP'];
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
}

AuditContext::set($user_id, $ip_address);

// Database file path
$db_file = __DIR__ . '/../graph.db';
$graph = new Graph($db_file);
$api = new ApiHandler($graph);

// Get request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove base path if needed (adjust based on your setup)
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path !== '/') {
    $path = substr($path, strlen($base_path));
}

// Parse path segments
$segments = array_values(array_filter(explode('/', $path)));

// Get JSON input for POST/PUT requests
$input = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    if ($input === null && $json !== '') {
        send_error(400, 'Invalid JSON input');
    }
}

// Helper functions
function send_response(int $code, mixed $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function send_error(int $code, string $message, ?array $details = null): void {
    $response = ['error' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    send_response($code, $response);
}

function send_success(mixed $data = null, string $message = 'Success'): void {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    send_response(200, $response);
}

function handle_api_result(array $result): void {
    if (isset($result['success']) && $result['success']) {
        $code = 200;
        if (isset($result['data'])) {
            send_success($result['data'], $result['message'] ?? 'Success');
        } else {
            send_success(null, $result['message'] ?? 'Success');
        }
    } else {
        $code = $result['code'] ?? 500;
        $details = $result['details'] ?? null;
        send_error($code, $result['error'] ?? 'Operation failed', $details);
    }
}

// Route handling
try {
    // GET /api.php/graph - Get entire graph
    if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'api.php') {
        send_response(200, $api->getGraph());
    }

    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'api.php' && $segments[1] === 'graph') {
        send_response(200, $api->getGraph());
    }

    // Node operations
    if (count($segments) >= 2 && $segments[1] === 'nodes') {

        // POST /api.php/nodes - Create node
        if ($method === 'POST' && count($segments) === 2) {
            if (!isset($input['id']) || !isset($input['data'])) {
                send_error(400, 'Missing required fields: id, data');
            }

            $result = $api->createNode($input['id'], $input['data']);
            handle_api_result($result);
        }

        // GET /api.php/nodes/{id} - Check if node exists
        if ($method === 'GET' && count($segments) === 3) {
            $id = urldecode($segments[2]);
            send_response(200, $api->nodeExists($id));
        }

        // PUT /api.php/nodes/{id} - Update node
        if ($method === 'PUT' && count($segments) === 3) {
            $id = urldecode($segments[2]);

            if (!isset($input['data'])) {
                send_error(400, 'Missing required field: data');
            }

            $result = $api->updateNode($id, $input['data']);
            handle_api_result($result);
        }

        // DELETE /api.php/nodes/{id} - Remove node
        if ($method === 'DELETE' && count($segments) === 3) {
            $id = urldecode($segments[2]);
            $result = $api->removeNode($id);
            handle_api_result($result);
        }
    }

    // Edge operations
    if (count($segments) >= 2 && $segments[1] === 'edges') {

        // POST /api.php/edges - Create edge
        if ($method === 'POST' && count($segments) === 2) {
            if (!isset($input['id']) || !isset($input['source']) || !isset($input['target'])) {
                send_error(400, 'Missing required fields: id, source, target');
            }

            $data = $input['data'] ?? [];
            $result = $api->createEdge($input['id'], $input['source'], $input['target'], $data);
            handle_api_result($result);
        }

        // GET /api.php/edges/{id} - Check if edge exists
        if ($method === 'GET' && count($segments) === 3) {
            $id = urldecode($segments[2]);
            send_response(200, $api->edgeExists($id));
        }

        // DELETE /api.php/edges/{id} - Remove edge
        if ($method === 'DELETE' && count($segments) === 3) {
            $id = urldecode($segments[2]);
            $result = $api->removeEdge($id);
            handle_api_result($result);
        }

        // DELETE /api.php/edges/from/{source} - Remove all edges from source
        if ($method === 'DELETE' && count($segments) === 4 && $segments[2] === 'from') {
            $source = urldecode($segments[3]);
            $result = $api->removeEdgesFrom($source);
            handle_api_result($result);
        }
    }

    // Backup operations
    if (count($segments) >= 2 && $segments[1] === 'backup') {

        // POST /api.php/backup - Create backup
        if ($method === 'POST' && count($segments) === 2) {
            $backup_name = $input['name'] ?? null;
            $result = $api->createBackup($backup_name);
            handle_api_result($result);
        }
    }

    // Audit operations
    if (count($segments) >= 2 && $segments[1] === 'audit') {

        // GET /api.php/audit - Get audit history
        // Optional query params: entity_type, entity_id
        if ($method === 'GET' && count($segments) === 2) {
            $entity_type = $_GET['entity_type'] ?? null;
            $entity_id = $_GET['entity_id'] ?? null;

            send_response(200, $api->getAuditHistory($entity_type, $entity_id));
        }
    }

    // Restore operations
    if (count($segments) >= 2 && $segments[1] === 'restore') {

        // POST /api.php/restore/entity - Restore specific entity
        if ($method === 'POST' && count($segments) === 3 && $segments[2] === 'entity') {
            if (!isset($input['entity_type']) || !isset($input['entity_id']) || !isset($input['audit_log_id'])) {
                send_error(400, 'Missing required fields: entity_type, entity_id, audit_log_id');
            }

            $result = $api->restoreEntity(
                $input['entity_type'],
                $input['entity_id'],
                (int)$input['audit_log_id']
            );
            handle_api_result($result);
        }

        // POST /api.php/restore/timestamp - Restore to timestamp
        if ($method === 'POST' && count($segments) === 3 && $segments[2] === 'timestamp') {
            if (!isset($input['timestamp'])) {
                send_error(400, 'Missing required field: timestamp');
            }

            $result = $api->restoreToTimestamp($input['timestamp']);
            handle_api_result($result);
        }
    }

    // Status operations
    if (count($segments) >= 2 && $segments[1] === 'status') {

        // GET /api.php/status - Get all node statuses
        if ($method === 'GET' && count($segments) === 2) {
            send_response(200, $api->getAllNodeStatuses());
        }

        // GET /api.php/status/allowed - Get allowed status values
        if ($method === 'GET' && count($segments) === 3 && $segments[2] === 'allowed') {
            send_response(200, $api->getAllowedStatuses());
        }
    }

    // Node status operations (within nodes routes)
    if (count($segments) >= 2 && $segments[1] === 'nodes') {

        // GET /api.php/nodes/{id}/status - Get node status
        if ($method === 'GET' && count($segments) === 4 && $segments[3] === 'status') {
            $id = urldecode($segments[2]);
            $result = $api->getNodeStatus($id);

            if (isset($result['success']) && $result['success']) {
                send_response(200, $result['data']);
            } else {
                handle_api_result($result);
            }
        }

        // GET /api.php/nodes/{id}/status/history - Get node status history
        if ($method === 'GET' && count($segments) === 5 && $segments[3] === 'status' && $segments[4] === 'history') {
            $id = urldecode($segments[2]);
            send_response(200, $api->getNodeStatusHistory($id));
        }

        // POST /api.php/nodes/{id}/status - Set node status
        if ($method === 'POST' && count($segments) === 4 && $segments[3] === 'status') {
            $id = urldecode($segments[2]);

            if (!isset($input['status'])) {
                send_error(400, 'Missing required field: status');
            }

            $result = $api->setNodeStatus($id, $input['status']);
            handle_api_result($result);
        }
    }

    // If no route matched
    send_error(404, 'Endpoint not found', ['path' => $path, 'method' => $method]);

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    send_error(500, 'Internal server error', ['message' => $e->getMessage()]);
}
