<?php declare(strict_types=1);

/**
 * Utility script to generate authentication credentials
 * Run from command line: php generate_auth.php
 */

echo "==============================================\n";
echo "Graph API Authentication Generator\n";
echo "==============================================\n\n";

// Generate password hash for Basic Auth
echo "--- Basic Auth Password Hash Generator ---\n";
echo "Enter password (or press Enter to skip): ";
$password = trim(fgets(STDIN));

if ($password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "\nPassword Hash:\n";
    echo $hash . "\n\n";
    echo "Add to \$VALID_USERS array in api.php:\n";
    echo "'username' => '$hash',\n\n";
}

// Generate Bearer Token
echo "--- Bearer Token Generator ---\n";
echo "Generate random bearer token? (y/n): ";
$generate = trim(fgets(STDIN));

if (strtolower($generate) === 'y') {
    $token = bin2hex(random_bytes(32)); // 64 character token
    echo "\nBearer Token:\n";
    echo $token . "\n\n";
    echo "Add to \$VALID_BEARER_TOKENS array in api.php:\n";
    echo "'$token' => 'automation_name',\n\n";
}

// Generate multiple users
echo "--- Batch User Generator ---\n";
echo "Generate multiple users? (y/n): ";
$batch = trim(fgets(STDIN));

if (strtolower($batch) === 'y') {
    $users = [];

    echo "Enter users (format: username:password, one per line, empty line to finish):\n";

    while (true) {
        echo "> ";
        $line = trim(fgets(STDIN));

        if ($line === '') {
            break;
        }

        if (strpos($line, ':') === false) {
            echo "Invalid format. Use: username:password\n";
            continue;
        }

        list($username, $pwd) = explode(':', $line, 2);
        $users[$username] = password_hash($pwd, PASSWORD_DEFAULT);
    }

    if (count($users) > 0) {
        echo "\n\$VALID_USERS = [\n";
        foreach ($users as $username => $hash) {
            echo "    '$username' => '$hash',\n";
        }
        echo "];\n\n";
    }
}

echo "==============================================\n";
echo "Done!\n";
echo "==============================================\n";
