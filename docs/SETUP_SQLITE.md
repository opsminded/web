# SQLite Setup Instructions

## Install SQLite PDO Driver

You need to install the PDO SQLite driver for PHP. Run the following command:

### For Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php-sqlite3
```

### For Fedora/RHEL:
```bash
sudo dnf install php-pdo
```

### For Alpine (Docker):
```bash
apk add php-pdo_sqlite php-sqlite3
```

After installation, restart your PHP service or web server if using one.

## Verify Installation

Check if SQLite is installed:
```bash
php -m | grep -i sqlite
```

You should see:
- `pdo_sqlite`
- `sqlite3`

## Setup Database

Once SQLite is installed, run these commands in order:

1. Initialize the database:
```bash
cd /home/tarcisio/projects/gdmon/web
php init_db.php
```

2. Migrate existing JSON data:
```bash
php migrate_json_to_sqlite.php
```

3. Backup your old JSON file:
```bash
mv database.json database.json.backup
```

## Done!

Your application will now use SQLite instead of the JSON file.

## Benefits

- No risk of file corruption
- ACID transactions
- Better performance
- Thread-safe operations
- Concurrent read access
