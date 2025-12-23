# Graph API Reference

REST API for the Graph library with full audit logging support.

## Authentication

The API is **stateless** and supports three authentication modes:

### 1. Anonymous Access (for read-only operations)

**GET requests** can be made without authentication. These will be logged in the audit trail as user `anonymous`.

```bash
# No authentication needed for GET requests
curl http://localhost/api.php/graph
curl http://localhost/api.php/nodes/node1
curl http://localhost/api.php/audit
```

### 2. Basic Authentication (for users)

**Required for state-changing operations** (POST, PUT, DELETE, PATCH).

Send your username and password in the Authorization header:

```
Authorization: Basic base64(username:password)
```

### 3. Bearer Token (for automations)

**Required for state-changing operations** (POST, PUT, DELETE, PATCH).

Send a pre-configured token in the Authorization header:

```
Authorization: Bearer your_token_here
```

Bearer tokens are configured in the `$VALID_BEARER_TOKENS` array at the top of `api.php`.

### Authentication Requirements Summary

| HTTP Method | Requires Auth? | Purpose |
|-------------|---------------|---------|
| GET | ❌ No | Read-only operations (can be anonymous) |
| POST | ✅ Yes | Create resources |
| PUT | ✅ Yes | Update resources |
| PATCH | ✅ Yes | Partial update resources |
| DELETE | ✅ Yes | Delete resources |

### Configuration

#### Add a new user (Basic Auth):
1. Generate password hash:
   ```bash
   php generate_auth.php
   ```
2. Add to `$VALID_USERS` array in `api.php`:
   ```php
   $VALID_USERS = [
       'username' => 'password_hash_here',
   ];
   ```

#### Add a new automation token (Bearer):
1. Generate token:
   ```bash
   php generate_auth.php
   ```
2. Add to `$VALID_BEARER_TOKENS` array in `api.php`:
   ```php
   $VALID_BEARER_TOKENS = [
       'token_string_here' => 'automation_name',
   ];
   ```

### Authentication Errors

#### Missing Authentication for State-Changing Operations

If you try to POST/PUT/DELETE without authentication:

```json
{
  "error": "Authentication required",
  "message": "Authentication is required for POST requests. Please provide valid Basic Auth credentials or Bearer token"
}
```
HTTP Status: `401 Unauthorized`

#### Invalid Credentials

If you provide invalid credentials:

```json
{
  "error": "Authentication required",
  "message": "Authentication is required for POST requests. Please provide valid Basic Auth credentials or Bearer token"
}
```
HTTP Status: `401 Unauthorized`

**Note:** GET requests do not require authentication and can be accessed anonymously.

## Base URL

```
http://your-domain/api.php
```

## Endpoints

### Graph Operations

#### Get Entire Graph
```
GET /api.php/graph
```

**Response:**
```json
{
  "nodes": [
    {
      "data": {
        "id": "node1",
        "label": "My Node"
      }
    }
  ],
  "edges": [
    {
      "data": {
        "id": "edge1",
        "source": "node1",
        "target": "node2"
      }
    }
  ]
}
```

---

### Node Operations

#### Create Node
```
POST /api.php/nodes
```

**Body:**
```json
{
  "id": "node1",
  "data": {
    "label": "My Node",
    "type": "person"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Node created successfully",
  "data": {
    "id": "node1"
  }
}
```

#### Check Node Exists
```
GET /api.php/nodes/{id}
```

**Response:**
```json
{
  "exists": true,
  "id": "node1"
}
```

#### Update Node
```
PUT /api.php/nodes/{id}
```

**Body:**
```json
{
  "data": {
    "label": "Updated Node",
    "type": "organization"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Node updated successfully",
  "data": {
    "id": "node1"
  }
}
```

#### Delete Node
```
DELETE /api.php/nodes/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Node removed successfully",
  "data": {
    "id": "node1"
  }
}
```

**Note:** Deleting a node also deletes all connected edges.

---

### Edge Operations

#### Create Edge
```
POST /api.php/edges
```

**Body:**
```json
{
  "id": "edge1",
  "source": "node1",
  "target": "node2",
  "data": {
    "type": "knows",
    "weight": 1.0
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Edge created successfully",
  "data": {
    "id": "edge1"
  }
}
```

#### Check Edge Exists
```
GET /api.php/edges/{id}
```

**Response:**
```json
{
  "exists": true,
  "id": "edge1"
}
```

#### Delete Edge
```
DELETE /api.php/edges/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Edge removed successfully",
  "data": {
    "id": "edge1"
  }
}
```

#### Delete All Edges From Source
```
DELETE /api.php/edges/from/{source}
```

**Response:**
```json
{
  "success": true,
  "message": "Edges removed successfully",
  "data": {
    "source": "node1"
  }
}
```

---

### Backup Operations

#### Create Backup
```
POST /api.php/backup
```

**Body (optional):**
```json
{
  "name": "my_backup"
}
```

If name is not provided, a timestamp-based name will be generated.

**Response:**
```json
{
  "success": true,
  "message": "Backup created successfully",
  "data": {
    "success": true,
    "file": "/path/to/backups/my_backup.db",
    "backup_name": "my_backup",
    "file_size": 12345
  }
}
```

---

### Audit Operations

#### Get Audit History
```
GET /api.php/audit
GET /api.php/audit?entity_type=node
GET /api.php/audit?entity_type=node&entity_id=node1
```

**Query Parameters:**
- `entity_type` (optional): Filter by entity type (node, edge, system)
- `entity_id` (optional): Filter by specific entity ID

**Response:**
```json
{
  "audit_log": [
    {
      "id": 1,
      "entity_type": "node",
      "entity_id": "node1",
      "action": "create",
      "old_data": null,
      "new_data": {
        "id": "node1",
        "label": "My Node"
      },
      "user_id": "user_123",
      "ip_address": "192.168.1.100",
      "created_at": "2025-12-23 10:30:00"
    }
  ]
}
```

---

### Restore Operations

#### Restore Specific Entity
```
POST /api.php/restore/entity
```

**Body:**
```json
{
  "entity_type": "node",
  "entity_id": "node1",
  "audit_log_id": 5
}
```

**Response:**
```json
{
  "success": true,
  "message": "Entity restored successfully"
}
```

#### Restore to Timestamp
```
POST /api.php/restore/timestamp
```

**Body:**
```json
{
  "timestamp": "2025-12-23 10:00:00"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Graph restored to timestamp successfully"
}
```

**Note:** This reverses all operations that occurred after the specified timestamp.

---

## Error Responses

All errors follow this format:

```json
{
  "error": "Error message",
  "details": {
    "additional": "information"
  }
}
```

### Common HTTP Status Codes

- `200 OK` - Success
- `400 Bad Request` - Invalid input
- `404 Not Found` - Resource not found or endpoint doesn't exist
- `409 Conflict` - Resource already exists
- `500 Internal Server Error` - Server error

---

## Complete cURL Examples

### Graph Operations

#### Get Entire Graph (Anonymous - No Auth)

```bash
# GET /api.php/graph
curl http://localhost/api.php/graph

# Alternative endpoint
curl http://localhost/api.php
```

**Response:**
```json
{
  "nodes": [...],
  "edges": [...]
}
```

---

### Node Operations

#### Create Node (Basic Auth)

```bash
# POST /api.php/nodes
curl -X POST http://localhost/api.php/nodes \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "person1",
    "data": {
      "label": "John Doe",
      "type": "person",
      "age": 30
    }
  }'
```

#### Create Node (Bearer Token)

```bash
curl -X POST http://localhost/api.php/nodes \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "person1",
    "data": {
      "label": "John Doe",
      "type": "person"
    }
  }'
```

#### Check if Node Exists (Anonymous)

```bash
# GET /api.php/nodes/{id}
curl http://localhost/api.php/nodes/person1
```

**Response:**
```json
{
  "exists": true,
  "id": "person1"
}
```

#### Update Node (Basic Auth)

```bash
# PUT /api.php/nodes/{id}
curl -X PUT http://localhost/api.php/nodes/person1 \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "label": "John Smith",
      "type": "person",
      "age": 31
    }
  }'
```

#### Update Node (Bearer Token)

```bash
curl -X PUT http://localhost/api.php/nodes/person1 \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "label": "John Smith",
      "type": "person"
    }
  }'
```

#### Delete Node (Basic Auth)

```bash
# DELETE /api.php/nodes/{id}
curl -X DELETE http://localhost/api.php/nodes/person1 \
  -u "admin:password"
```

#### Delete Node (Bearer Token)

```bash
curl -X DELETE http://localhost/api.php/nodes/person1 \
  -H "Authorization: Bearer automation_token_123456789"
```

---

### Edge Operations

#### Create Edge (Basic Auth)

```bash
# POST /api.php/edges
curl -X POST http://localhost/api.php/edges \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "e1",
    "source": "person1",
    "target": "person2",
    "data": {
      "type": "knows",
      "weight": 1.0,
      "since": "2020"
    }
  }'
```

#### Create Edge (Bearer Token)

```bash
curl -X POST http://localhost/api.php/edges \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "e1",
    "source": "person1",
    "target": "person2",
    "data": {
      "type": "knows"
    }
  }'
```

#### Create Edge (Minimal - No data field)

```bash
# The "data" field is optional
curl -X POST http://localhost/api.php/edges \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "e2",
    "source": "person1",
    "target": "person3"
  }'
```

#### Check if Edge Exists (Anonymous)

```bash
# GET /api.php/edges/{id}
curl http://localhost/api.php/edges/e1
```

**Response:**
```json
{
  "exists": true,
  "id": "e1"
}
```

#### Delete Edge (Basic Auth)

```bash
# DELETE /api.php/edges/{id}
curl -X DELETE http://localhost/api.php/edges/e1 \
  -u "admin:password"
```

#### Delete Edge (Bearer Token)

```bash
curl -X DELETE http://localhost/api.php/edges/e1 \
  -H "Authorization: Bearer automation_token_123456789"
```

#### Delete All Edges From Source (Basic Auth)

```bash
# DELETE /api.php/edges/from/{source}
curl -X DELETE http://localhost/api.php/edges/from/person1 \
  -u "admin:password"
```

#### Delete All Edges From Source (Bearer Token)

```bash
curl -X DELETE http://localhost/api.php/edges/from/person1 \
  -H "Authorization: Bearer automation_token_123456789"
```

---

### Backup Operations

#### Create Backup with Auto-Generated Name (Basic Auth)

```bash
# POST /api.php/backup
curl -X POST http://localhost/api.php/backup \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Response:**
```json
{
  "success": true,
  "message": "Backup created successfully",
  "data": {
    "success": true,
    "file": "/path/to/backups/backup_2025-12-23_13-45-30.db",
    "backup_name": "backup_2025-12-23_13-45-30",
    "file_size": 12345
  }
}
```

#### Create Backup with Custom Name (Basic Auth)

```bash
curl -X POST http://localhost/api.php/backup \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "before_migration"
  }'
```

#### Create Backup (Bearer Token)

```bash
curl -X POST http://localhost/api.php/backup \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "automated_backup"
  }'
```

---

### Audit Operations

#### Get All Audit History (Anonymous)

```bash
# GET /api.php/audit
curl http://localhost/api.php/audit
```

#### Get Audit History for Specific Entity Type (Anonymous)

```bash
# Filter by entity_type
curl "http://localhost/api.php/audit?entity_type=node"

# Or for edges
curl "http://localhost/api.php/audit?entity_type=edge"

# Or for system events
curl "http://localhost/api.php/audit?entity_type=system"
```

#### Get Audit History for Specific Entity (Anonymous)

```bash
# Filter by entity_type and entity_id
curl "http://localhost/api.php/audit?entity_type=node&entity_id=person1"
```

**Response:**
```json
{
  "audit_log": [
    {
      "id": 1,
      "entity_type": "node",
      "entity_id": "person1",
      "action": "create",
      "old_data": null,
      "new_data": {
        "id": "person1",
        "label": "John Doe"
      },
      "user_id": "admin",
      "ip_address": "192.168.1.100",
      "created_at": "2025-12-23 10:30:00"
    },
    {
      "id": 2,
      "entity_type": "node",
      "entity_id": "person1",
      "action": "update",
      "old_data": {
        "id": "person1",
        "label": "John Doe"
      },
      "new_data": {
        "id": "person1",
        "label": "John Smith"
      },
      "user_id": "admin",
      "ip_address": "192.168.1.100",
      "created_at": "2025-12-23 10:35:00"
    }
  ]
}
```

---

### Restore Operations

#### Restore Specific Entity (Basic Auth)

```bash
# POST /api.php/restore/entity
# Restores entity to state from audit log entry
curl -X POST http://localhost/api.php/restore/entity \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "node",
    "entity_id": "person1",
    "audit_log_id": 5
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Entity restored successfully"
}
```

#### Restore Specific Entity (Bearer Token)

```bash
curl -X POST http://localhost/api.php/restore/entity \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "edge",
    "entity_id": "e1",
    "audit_log_id": 12
  }'
```

#### Restore Graph to Timestamp (Basic Auth)

```bash
# POST /api.php/restore/timestamp
# Reverses all operations after the specified timestamp
curl -X POST http://localhost/api.php/restore/timestamp \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "timestamp": "2025-12-23 10:00:00"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Graph restored to timestamp successfully"
}
```

#### Restore Graph to Timestamp (Bearer Token)

```bash
curl -X POST http://localhost/api.php/restore/timestamp \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "timestamp": "2025-12-23 10:00:00"
  }'
```

---

### Advanced Examples

#### Bulk Operations Script

```bash
#!/bin/bash

# Set your auth credentials
AUTH="admin:password"
BASE_URL="http://localhost/api.php"

# Create multiple nodes
curl -X POST $BASE_URL/nodes \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"id":"alice","data":{"label":"Alice","type":"person"}}'

curl -X POST $BASE_URL/nodes \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"id":"bob","data":{"label":"Bob","type":"person"}}'

curl -X POST $BASE_URL/nodes \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"id":"charlie","data":{"label":"Charlie","type":"person"}}'

# Create edges between them
curl -X POST $BASE_URL/edges \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"id":"e1","source":"alice","target":"bob","data":{"type":"knows"}}'

curl -X POST $BASE_URL/edges \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"id":"e2","source":"bob","target":"charlie","data":{"type":"knows"}}'

# Create backup
curl -X POST $BASE_URL/backup \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"name":"after_bulk_import"}'

# Get the full graph
curl $BASE_URL/graph
```

#### Using jq for Pretty Output

```bash
# Install jq first: sudo apt-get install jq

# Get graph with pretty formatting
curl -s http://localhost/api.php/graph | jq .

# Get audit log with filtering
curl -s "http://localhost/api.php/audit?entity_type=node" | jq '.audit_log[]'

# Check node exists and extract value
curl -s http://localhost/api.php/nodes/person1 | jq '.exists'

# Get only node IDs from graph
curl -s http://localhost/api.php/graph | jq '.nodes[].data.id'
```

#### Automation with Bearer Token

```bash
#!/bin/bash

# Automation script for CI/CD
TOKEN="automation_token_123456789"
BASE_URL="http://localhost/api.php"

# Create backup before deployment
BACKUP_RESPONSE=$(curl -s -X POST $BASE_URL/backup \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"pre_deploy_$(date +%Y%m%d_%H%M%S)\"}")

echo "Backup created: $BACKUP_RESPONSE"

# Update nodes with new data
curl -X PUT $BASE_URL/nodes/app_config \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "version": "2.0.0",
      "deployed_at": "'$(date -Iseconds)'"
    }
  }'

# Verify changes in audit log
curl -s "http://localhost/api.php/audit?entity_type=node&entity_id=app_config" | jq .
```

---

## JavaScript Fetch Examples

### Anonymous (No Authentication) - Read-Only

```javascript
// Get entire graph (no auth needed)
fetch('/api.php/graph')
  .then(res => res.json())
  .then(graph => console.log(graph));

// Check if node exists (no auth needed)
fetch('/api.php/nodes/person1')
  .then(res => res.json())
  .then(data => console.log(data));

// Get audit history (no auth needed)
fetch('/api.php/audit?entity_type=node&entity_id=person1')
  .then(res => res.json())
  .then(audit => console.log(audit));

// These requests will be logged as user 'anonymous'
```

### With Basic Auth

```javascript
// Helper function to create Basic Auth header
function basicAuth(username, password) {
  return 'Basic ' + btoa(username + ':' + password);
}

// Create a node
fetch('/api.php/nodes', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': basicAuth('admin', 'password')
  },
  body: JSON.stringify({
    id: 'node1',
    data: { label: 'My Node', type: 'person' }
  })
})
  .then(res => res.json())
  .then(data => console.log(data));

// Get graph
fetch('/api.php/graph', {
  headers: {
    'Authorization': basicAuth('admin', 'password')
  }
})
  .then(res => res.json())
  .then(graph => console.log(graph));

// Update node
fetch('/api.php/nodes/node1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': basicAuth('admin', 'password')
  },
  body: JSON.stringify({
    data: { label: 'Updated Label' }
  })
})
  .then(res => res.json())
  .then(data => console.log(data));
```

### With Bearer Token

```javascript
const BEARER_TOKEN = 'automation_token_123456789';

// Create a node
fetch('/api.php/nodes', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + BEARER_TOKEN
  },
  body: JSON.stringify({
    id: 'node1',
    data: { label: 'My Node', type: 'person' }
  })
})
  .then(res => res.json())
  .then(data => console.log(data));

// Get graph
fetch('/api.php/graph', {
  headers: {
    'Authorization': 'Bearer ' + BEARER_TOKEN
  }
})
  .then(res => res.json())
  .then(graph => console.log(graph));
```

---

## Audit Context

The API automatically captures audit information for **every request** (authenticated or anonymous):
- **User ID**:
  - `anonymous` for unauthenticated GET requests
  - Username for Basic Auth
  - Automation name for Bearer token
- **IP Address**: From request headers (supports proxies via `X-Forwarded-For` and `X-Real-IP`)

All operations are automatically logged with this context in the audit log.

### Anonymous User Tracking

Even though GET requests don't require authentication, they are still tracked in the audit log with:
- `user_id`: `"anonymous"`
- `ip_address`: Client's IP address
- All other audit fields (timestamp, action, entity details)

This allows you to monitor who is accessing your graph data, even without authentication.
