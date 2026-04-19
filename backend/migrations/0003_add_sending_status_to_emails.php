<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

// TICKET-004: Add 'sending' status to ENUM to allow workers to claim emails before processing.
// Note: MySQL ENUM updates can be tricky, we'll redefine the column.
$pdo->exec("
    ALTER TABLE scheduled_emails 
    MODIFY COLUMN status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    ADD COLUMN claimed_by VARCHAR(64) DEFAULT NULL AFTER status
");
