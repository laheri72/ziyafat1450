<?php

// Load environment variables.
// .env.local is for machine-specific XAMPP/dev settings and is ignored by git.
// .env keeps the existing hosted/prod workflow, then server env vars are the fallback.
$env = [];

$envFiles = [
    __DIR__ . '/../.env',
    __DIR__ . '/../.env.local',
];

foreach ($envFiles as $envFile) {
    if (file_exists($envFile)) {
        $values = parse_ini_file($envFile);
        if ($values !== false) {
            $env = array_merge($env, $values);
        }
    }
}

// Use environment variables if .env values not set (production)
$db_host = $env['DB_HOST'] ?? ($_ENV['DB_HOST'] ?? getenv('DB_HOST'));
$db_user = $env['DB_USER'] ?? ($_ENV['DB_USER'] ?? getenv('DB_USER'));
$db_pass = $env['DB_PASS'] ?? ($_ENV['DB_PASS'] ?? getenv('DB_PASS'));
$db_name = $env['DB_NAME'] ?? ($_ENV['DB_NAME'] ?? getenv('DB_NAME'));

// Validate that all required variables are set
if (!$db_host || !$db_user || !$db_name) {
    die("Database configuration error: Missing required environment variables. Check .env file or Railway environment variables.");
}

// Create connection
$connectError = null;

try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
} catch (mysqli_sql_exception $e) {
    $conn = false;
    $connectError = $e->getMessage();
}

if (!$conn) {
    $error = $connectError ?: mysqli_connect_error();
    die("Connection failed: " . $error . "\nHost: $db_host\nUser: $db_user\nDB: $db_name");
}
