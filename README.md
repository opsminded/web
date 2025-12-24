# GDMON - Graph Dependency Monitor

A graph-based dependency management system for companies, designed to visualize and manage relationships between business cases and infrastructure components.

## Overview

GDMON provides an API and web interface to create, manipulate, and visualize dependency graphs within an organization. It uses graph theory to represent complex relationships between different entities (servers, applications, business processes, etc.) and provides comprehensive audit logging, backup/restore capabilities, and real-time status monitoring.

## Features

- **Graph-based Architecture**: Manage nodes (entities) and edges (relationships) in a flexible graph structure
- **REST API**: Full-featured RESTful API for programmatic access
- **Visual Interface**: Interactive graph visualization using Cytoscape.js
- **Comprehensive Audit Trail**: Track all changes with user, timestamp, and IP address logging
- **Backup & Restore**: Create backups and restore to previous states (entity-level or point-in-time)
- **Status Monitoring**: Track node health status (healthy, unhealthy, maintenance, unknown)
- **Authentication**: Support for Bearer tokens, Basic Auth, and anonymous read access
- **Stateless Design**: No sessions, perfect for distributed systems and automation

## Technology Stack

- **Backend**: PHP 8+ with strict types
- **Database**: SQLite (graph.db)
- **Frontend**: HTML5 + JavaScript with Cytoscape.js for graph visualization
- **Testing**: PHPUnit
- **Authentication**: Bearer tokens and HTTP Basic Auth

## Project Structure

```
web/
├── src/                      # Core PHP classes
│   ├── Graph.php            # Main graph database class
│   ├── ApiHandler.php       # API business logic layer
│   ├── Authenticator.php    # Authentication handler
│   ├── AuditContext.php     # Global audit context
│   └── NodeStatus.php       # Node status value object
├── public/                   # Web-accessible files
│   ├── api.php              # REST API endpoint
│   ├── index.html           # Graph visualization interface
│   └── img/                 # Status icons
├── tests/                    # PHPUnit test suite
├── docs/                     # Documentation
│   ├── API_REFERENCE.md     # Complete API documentation
│   ├── AUTH_SETUP.md        # Authentication setup guide
│   └── SETUP_SQLITE.md      # SQLite setup instructions
├── vendor/                   # Composer dependencies
├── composer.json            # PHP dependencies
├── phpunit.xml              # PHPUnit configuration
└── graph.db                 # SQLite database (auto-created)
```

## Quick Start

### Prerequisites

- PHP 8.0 or higher
- SQLite3 extension for PHP
- Composer (for dependency management)
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd web
```

2. Install dependencies:
```bash
php composer.phar install
# or
composer install
```

3. Start the development server:
```bash
php -S localhost:8000 -t public
```

4. Access the application:
   - Web Interface: http://localhost:8000
   - API Endpoint: http://localhost:8000/api.php

### First Steps

1. **Create a node** (requires authentication):
```bash
curl -X POST http://localhost:8000/api.php/nodes \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{"id": "server1", "label": "Web Server", "type": "infrastructure"}'
```

2. **View the graph** (no authentication required):
```bash
curl http://localhost:8000/api.php/graph
```

3. **Open the web interface**: Navigate to http://localhost:8000 in your browser to see the visual representation

## Core Concepts

### Nodes
Nodes represent entities in your system (servers, applications, business processes, etc.). Each node has:
- **id**: Unique identifier
- **data**: Arbitrary JSON data (labels, properties, metadata)
- **timestamps**: Created and updated timestamps
- **status**: Current health status (optional)

### Edges
Edges represent relationships between nodes. Each edge has:
- **id**: Unique identifier
- **source**: ID of the source node
- **target**: ID of the target node
- **data**: Arbitrary JSON data (relationship type, properties)

### Audit Logging
Every operation is logged with:
- Entity type (node/edge/system)
- Entity ID
- Action (create/update/delete/restore)
- Old and new data states
- User ID
- IP address
- Timestamp

### Node Status
Track operational status of nodes with predefined statuses:
- `unknown`: Initial or undefined state
- `healthy`: Operating normally
- `unhealthy`: Experiencing issues
- `maintenance`: Planned maintenance mode

## Authentication

GDMON supports three authentication modes:

### 1. Anonymous Access (Read-Only)
GET requests can be made without authentication:
```bash
curl http://localhost:8000/api.php/graph
```

### 2. Basic Authentication
For user-based access:
```bash
curl -X POST http://localhost:8000/api.php/nodes \
  -u admin:password \
  -H "Content-Type: application/json" \
  -d '{"id": "node1", "label": "Node 1"}'
```

### 3. Bearer Token
For automation and CI/CD:
```bash
curl -X POST http://localhost:8000/api.php/nodes \
  -H "Authorization: Bearer automation_token_123456789" \
  -H "Content-Type: application/json" \
  -d '{"id": "node1", "label": "Node 1"}'
```

**Configuration**: Edit `public/api.php` to add users and tokens.

For detailed authentication setup, see [docs/AUTH_SETUP.md](docs/AUTH_SETUP.md).

## API Endpoints

### Graph Operations
- `GET /api.php/graph` - Get entire graph (nodes and edges)
- `GET /api.php/status` - Get status of all nodes

### Node Operations
- `POST /api.php/nodes` - Create a node
- `GET /api.php/nodes/{id}` - Check if node exists
- `PUT /api.php/nodes/{id}` - Update a node
- `DELETE /api.php/nodes/{id}` - Remove a node

### Edge Operations
- `POST /api.php/edges` - Create an edge
- `GET /api.php/edges/{id}` - Check if edge exists
- `DELETE /api.php/edges/{id}` - Remove an edge
- `DELETE /api.php/edges/from/{source}` - Remove all edges from a source node

### Status Operations
- `GET /api.php/status/allowed` - Get allowed status values
- `GET /api.php/nodes/{id}/status` - Get current status of a node
- `POST /api.php/nodes/{id}/status` - Set node status
- `GET /api.php/nodes/{id}/status/history` - Get status history for a node

### Backup & Restore
- `POST /api.php/backup` - Create a backup
- `GET /api.php/audit` - Get audit history
- `POST /api.php/restore/entity` - Restore a specific entity
- `POST /api.php/restore/timestamp` - Restore graph to a timestamp

For complete API documentation with examples, see [docs/API_REFERENCE.md](docs/API_REFERENCE.md).

## Testing

Run the test suite:

```bash
# Run all tests
php phpunit.phar

# Run with coverage report
php phpunit.phar --coverage-html coverrep
```

The project includes comprehensive unit tests for all components:
- `GraphTest.php` - Graph database operations
- `ApiHandlerTest.php` - API business logic
- `AuthenticatorTest.php` - Authentication mechanisms
- `AuditContextTest.php` - Audit context management

## Use Cases

### Infrastructure Dependency Mapping
Map server dependencies, load balancers, databases, and application relationships:
```json
{
  "nodes": [
    {"id": "lb1", "label": "Load Balancer", "type": "infrastructure"},
    {"id": "web1", "label": "Web Server 1", "type": "infrastructure"},
    {"id": "db1", "label": "Database", "type": "infrastructure"}
  ],
  "edges": [
    {"id": "e1", "source": "lb1", "target": "web1", "type": "routes_to"},
    {"id": "e2", "source": "web1", "target": "db1", "type": "connects_to"}
  ]
}
```

### Business Process Dependencies
Track dependencies between business processes and their supporting systems:
```json
{
  "nodes": [
    {"id": "checkout", "label": "Checkout Process", "type": "business"},
    {"id": "payment_api", "label": "Payment API", "type": "service"},
    {"id": "inventory", "label": "Inventory System", "type": "system"}
  ],
  "edges": [
    {"id": "e1", "source": "checkout", "target": "payment_api", "type": "depends_on"},
    {"id": "e2", "source": "checkout", "target": "inventory", "type": "depends_on"}
  ]
}
```

### Change Impact Analysis
Use the graph to understand impact before making changes:
1. Query all edges from a node to see dependencies
2. Check status history to understand past issues
3. Review audit logs to see recent changes
4. Create backup before making changes
5. Restore if needed

## Database Schema

The SQLite database includes the following tables:

### nodes
- `id` (TEXT PRIMARY KEY)
- `data` (TEXT - JSON)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

### edges
- `id` (TEXT PRIMARY KEY)
- `source` (TEXT - Foreign key to nodes.id)
- `target` (TEXT - Foreign key to nodes.id)
- `data` (TEXT - JSON)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

### audit_log
- `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
- `entity_type` (TEXT)
- `entity_id` (TEXT)
- `action` (TEXT)
- `old_data` (TEXT - JSON)
- `new_data` (TEXT - JSON)
- `user_id` (TEXT)
- `ip_address` (TEXT)
- `created_at` (DATETIME)

### node_status
- `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
- `node_id` (TEXT - Foreign key to nodes.id)
- `status` (TEXT)
- `created_at` (DATETIME)

## Configuration

### Authentication
Edit `public/api.php` to configure:
- `$valid_bearer_tokens`: Bearer tokens for automation
- `$valid_users`: Username/password hashes for users

### Database Location
By default, the database is stored at `graph.db` in the project root. To change:
```php
// In public/api.php
$db_file = __DIR__ . '/../graph.db'; // Modify this path
```

## Development

### Class Structure

**Graph** - Core database operations:
- Node CRUD (add_node, update_node, remove_node, node_exists)
- Edge CRUD (add_edge, remove_edge, edge_exists)
- Audit logging (audit_log, get_audit_history)
- Backup/restore (create_backup, restore_entity, restore_to_timestamp)
- Status management (set_node_status, get_node_status, status)

**ApiHandler** - Business logic wrapper:
- Validates input
- Formats responses
- Handles errors
- Provides consistent API interface

**Authenticator** - Authentication:
- Bearer token validation
- Basic auth validation
- Multi-mode support

**AuditContext** - Global state:
- Thread-safe user context
- IP address tracking
- Request-scoped lifecycle

## Contributing

1. Write tests for new features
2. Follow PSR-12 coding standards
3. Use strict types (`declare(strict_types=1)`)
4. Document public APIs
5. Maintain backward compatibility

## License

[Add your license information here]

## Support

For issues, questions, or contributions:
- Check the [docs/](docs/) directory for detailed documentation
- Review the test suite for usage examples
- Open an issue in the repository

## Roadmap

Potential future enhancements:
- PostgreSQL/MySQL support for larger deployments
- Graph query language (traversal, pattern matching)
- Real-time WebSocket updates for visualization
- Access control lists (ACLs) for nodes
- Scheduled status checks and alerts
- Export/import in various formats (GraphML, DOT, etc.)
- Multi-tenancy support
- Plugin system for custom node types
