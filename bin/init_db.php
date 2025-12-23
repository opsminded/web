<?php declare(strict_types=1);

/**
 * Database initialization script
 * Run this once to create the SQLite database and tables
 */

const DB_FILE = __DIR__ . '/graph.db';

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create nodes table
    $db->exec("
        CREATE TABLE IF NOT EXISTS nodes (
            id TEXT PRIMARY KEY NOT NULL,
            data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create edges table
    $db->exec("
        CREATE TABLE IF NOT EXISTS edges (
            id TEXT PRIMARY KEY NOT NULL,
            source TEXT NOT NULL,
            target TEXT NOT NULL,
            data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (source) REFERENCES nodes(id) ON DELETE CASCADE,
            FOREIGN KEY (target) REFERENCES nodes(id) ON DELETE CASCADE
        )
    ");

    // Create indexes for better query performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_edges_source ON edges(source)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_edges_target ON edges(target)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_edges_source_target ON edges(source, target)");

    // Create audit table to track all changes
    $db->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL CHECK(entity_type IN ('node', 'edge')),
            entity_id TEXT NOT NULL,
            action TEXT NOT NULL CHECK(action IN ('create', 'update', 'delete')),
            old_data TEXT,
            new_data TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_id TEXT,
            ip_address TEXT
        )
    ");

    // Create indexes for audit log queries
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log(entity_type, entity_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_timestamp ON audit_log(timestamp)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action)");

    echo "Database initialized successfully!\n";
    echo "Database location: " . DB_FILE . "\n";

} catch (PDOException $e) {
    echo "Database initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}
