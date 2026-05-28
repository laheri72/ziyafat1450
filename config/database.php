<?php

// Load environment variables
// First try .env file (local development), then fall back to $_ENV (production/Railway)
$env = [];

// Try to load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
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
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\nHost: $db_host\nUser: $db_user\nDB: $db_name");
}
