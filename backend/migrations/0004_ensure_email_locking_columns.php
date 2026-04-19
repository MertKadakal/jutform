<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

// TICKET-004: Ensure claimed_by column exists for worker locking.
$pdo->exec("
    ALTER TABLE scheduled_emails 
    ADD COLUMN IF NOT EXISTS claimed_by VARCHAR(64) DEFAULT NULL AFTER status
");

$pdo->exec("
    ALTER TABLE scheduled_emails 
    MODIFY COLUMN status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending'
");
