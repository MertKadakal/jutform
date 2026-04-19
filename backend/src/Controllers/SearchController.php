<?php

namespace JutForm\Controllers;

use JutForm\Core\Database;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;

class SearchController
{
    public function search(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            Response::json(['results' => []]);
        }
        $pdo = Database::getInstance();
        $like = '%' . $term . '%';
        $stmt = $pdo->prepare(
            'SELECT id, config_key, value FROM app_config WHERE value LIKE ? LIMIT 200'
        );
        $stmt->execute([$like]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        Response::json(['results' => $rows]);
    }

    public function advancedSearch(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }

        $field = (string) $request->query('field', 'title');
        $term = (string) $request->query('term', '');

        // Whitelist allowed column names to prevent SQL Injection in the field parameter
        $allowedFields = ['id', 'title', 'description', 'status'];
        if (!in_array($field, $allowedFields, true)) {
            $field = 'title';
        }

        $pdo = Database::getInstance();
        
        // Use prepared statements for the term and user_id to prevent injection
        $sql = "SELECT * FROM forms WHERE {$field} LIKE ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['%' . $term . '%', (int) $uid]);
        
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        Response::json(['forms' => $rows]);
    }
}
