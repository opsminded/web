<?php declare(strict_types=1);

/**
 * Simple API test script
 * Run this from command line: php test_api.php [auth_type]
 *
 * Examples:
 *   php test_api.php basic          # Use Basic Auth (default)
 *   php test_api.php bearer         # Use Bearer Token
 *   php test_api.php anonymous      # Use no authentication (read-only)
 */

// Configuration
$AUTH_TYPE = $argv[1] ?? 'basic'; // 'basic', 'bearer', or 'anonymous'
$BASIC_AUTH_USER = 'admin';
$BASIC_AUTH_PASS = 'password';
$BEARER_TOKEN = 'automation_token_123456789';

function api_request(string $method, string $endpoint, ?array $data = null): array {
    global $AUTH_TYPE, $BASIC_AUTH_USER, $BASIC_AUTH_PASS, $BEARER_TOKEN;

    $base_url = 'http://localhost/api.php';
    $url = $base_url . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // Set authentication header
    $headers = [];
    if ($AUTH_TYPE === 'bearer') {
        $headers[] = 'Authorization: Bearer ' . $BEARER_TOKEN;
    } elseif ($AUTH_TYPE === 'basic') {
        // Basic auth
        curl_setopt($ch, CURLOPT_USERPWD, "$BASIC_AUTH_USER:$BASIC_AUTH_PASS");
    }
    // else: anonymous - no auth header

    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    ];
}

function test(string $name, callable $fn): void {
    echo "\n🧪 Testing: $name\n";
    try {
        $fn();
        echo "✅ Passed\n";
    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }
}

echo "Starting API Tests...\n";
echo "===================\n";
echo "Auth Type: " . strtoupper($AUTH_TYPE) . "\n";
if ($AUTH_TYPE === 'bearer') {
    echo "Using Bearer Token: " . substr($BEARER_TOKEN, 0, 20) . "...\n";
} elseif ($AUTH_TYPE === 'basic') {
    echo "Using Basic Auth: $BASIC_AUTH_USER\n";
} else {
    echo "No authentication (anonymous) - Read-only operations only\n";
}
echo "===================\n";

// Skip state-changing tests in anonymous mode
if ($AUTH_TYPE === 'anonymous') {
    echo "\n⚠️  Skipping state-changing tests (POST/PUT/DELETE) in anonymous mode\n";
    echo "Run with: php test_api.php basic (or bearer) to test all operations\n\n";
}

// Test 1: Create a node
if ($AUTH_TYPE !== 'anonymous') test("Create Node", function() {
    $result = api_request('POST', '/nodes', [
        'id' => 'test_node_1',
        'data' => ['label' => 'Test Node 1', 'type' => 'test']
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['success']) {
        throw new Exception("Response indicated failure");
    }

    echo "   Created node: test_node_1\n";
});

// Test 2: Check node exists
test("Check Node Exists", function() {
    $result = api_request('GET', '/nodes/test_node_1');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['exists']) {
        throw new Exception("Node should exist");
    }

    echo "   Node exists: test_node_1\n";
});

// Test 3: Update node
if ($AUTH_TYPE !== 'anonymous') test("Update Node", function() {
    $result = api_request('PUT', '/nodes/test_node_1', [
        'data' => ['label' => 'Updated Test Node', 'type' => 'test_updated']
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['success']) {
        throw new Exception("Response indicated failure");
    }

    echo "   Updated node: test_node_1\n";
});

// Test 4: Create second node
if ($AUTH_TYPE !== 'anonymous') test("Create Second Node", function() {
    $result = api_request('POST', '/nodes', [
        'id' => 'test_node_2',
        'data' => ['label' => 'Test Node 2', 'type' => 'test']
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    echo "   Created node: test_node_2\n";
});

// Test 5: Create an edge
if ($AUTH_TYPE !== 'anonymous') test("Create Edge", function() {
    $result = api_request('POST', '/edges', [
        'id' => 'test_edge_1',
        'source' => 'test_node_1',
        'target' => 'test_node_2',
        'data' => ['type' => 'test_relation']
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['success']) {
        throw new Exception("Response indicated failure");
    }

    echo "   Created edge: test_edge_1\n";
});

// Test 6: Check edge exists
test("Check Edge Exists", function() {
    $result = api_request('GET', '/edges/test_edge_1');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['exists']) {
        throw new Exception("Edge should exist");
    }

    echo "   Edge exists: test_edge_1\n";
});

// Test 7: Get entire graph
test("Get Graph", function() {
    $result = api_request('GET', '/graph');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!isset($result['response']['nodes']) || !isset($result['response']['edges'])) {
        throw new Exception("Graph should have nodes and edges");
    }

    $node_count = count($result['response']['nodes']);
    $edge_count = count($result['response']['edges']);

    echo "   Graph has {$node_count} nodes and {$edge_count} edges\n";
});

// Test 8: Get audit history
test("Get Audit History", function() {
    $result = api_request('GET', '/audit?entity_type=node&entity_id=test_node_1');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!isset($result['response']['audit_log'])) {
        throw new Exception("Response should have audit_log");
    }

    $log_count = count($result['response']['audit_log']);
    echo "   Found {$log_count} audit log entries\n";
});

// Test 9: Create backup
if ($AUTH_TYPE !== 'anonymous') test("Create Backup", function() {
    $result = api_request('POST', '/backup', [
        'name' => 'test_backup_' . time()
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['success']) {
        throw new Exception("Backup should succeed");
    }

    echo "   Backup created: {$result['response']['data']['backup_name']}\n";
});

// Test 10: Delete edge
if ($AUTH_TYPE !== 'anonymous') test("Delete Edge", function() {
    $result = api_request('DELETE', '/edges/test_edge_1');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['success']) {
        throw new Exception("Response indicated failure");
    }

    echo "   Deleted edge: test_edge_1\n";
});

// Test 11: Delete node
if ($AUTH_TYPE !== 'anonymous') test("Delete Node", function() {
    $result = api_request('DELETE', '/nodes/test_node_1');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    if (!$result['response']['success']) {
        throw new Exception("Response indicated failure");
    }

    echo "   Deleted node: test_node_1\n";
});

// Test 12: Delete second node
if ($AUTH_TYPE !== 'anonymous') test("Delete Second Node", function() {
    $result = api_request('DELETE', '/nodes/test_node_2');

    if ($result['http_code'] !== 200) {
        throw new Exception("Expected 200, got {$result['http_code']}");
    }

    echo "   Deleted node: test_node_2\n";
});

// Anonymous-specific tests
if ($AUTH_TYPE === 'anonymous') {
    echo "\n--- Anonymous Access Tests ---\n";

    // Test: GET should work without auth
    test("Anonymous GET Graph", function() {
        $result = api_request('GET', '/graph');

        if ($result['http_code'] !== 200) {
            throw new Exception("Expected 200, got {$result['http_code']}");
        }

        if (!isset($result['response']['nodes']) || !isset($result['response']['edges'])) {
            throw new Exception("Graph should have nodes and edges");
        }

        echo "   Anonymous GET request successful\n";
    });

    // Test: POST should fail without auth
    test("Anonymous POST Should Fail", function() {
        $result = api_request('POST', '/nodes', [
            'id' => 'should_fail',
            'data' => ['label' => 'This should fail']
        ]);

        if ($result['http_code'] !== 401) {
            throw new Exception("Expected 401, got {$result['http_code']}");
        }

        if (!isset($result['response']['error'])) {
            throw new Exception("Should return error");
        }

        echo "   Correctly rejected POST without authentication\n";
    });

    // Test: DELETE should fail without auth
    test("Anonymous DELETE Should Fail", function() {
        $result = api_request('DELETE', '/nodes/test_node_1');

        if ($result['http_code'] !== 401) {
            throw new Exception("Expected 401, got {$result['http_code']}");
        }

        echo "   Correctly rejected DELETE without authentication\n";
    });
}

echo "\n===================\n";
echo "Tests completed!\n";
