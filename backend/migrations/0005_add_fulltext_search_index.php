<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

// TICKET-006: Add FULLTEXT index to forms.title for high-performance searching.
// Also adds a normal index for user_id lookup if it's missing (though it should exist).
$pdo->exec("
    ALTER TABLE forms
    ADD FULLTEXT INDEX idx_title_fulltext (title)
");
