<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

// TICKET-002: Change value type from VARCHAR(255) to TEXT to allow longer string values like HTML templates.
$pdo->exec("
    ALTER TABLE form_settings
    MODIFY COLUMN value TEXT
");

// Also noticed app_config is small (200), increasing it to TEXT as well for consistency and to prevent future issues.
$pdo->exec("
    ALTER TABLE app_config
    MODIFY COLUMN value TEXT
");
