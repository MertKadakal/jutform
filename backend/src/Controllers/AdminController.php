<?php

namespace JutForm\Controllers;

use JutForm\Core\Database;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;

class AdminController
{
    public function revenue(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $user = \JutForm\Models\User::find($uid);
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::error('Forbidden', 403);
        }
        
        $pdo = Database::getInstance();
        
        // TICKET-008 Fix: We now use the structured 'payments' table instead of 
        // parsing fragile JSON strings. This ensures the totals match finance records.
        $sql = 'SELECT SUM(amount) AS total FROM payments';
        
        $row = $pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
        $total = $row['total'] ?? 0.0;
        
        Response::json(['revenue_total' => (float) $total]);
    }

    public function internalConfig(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $user = \JutForm\Models\User::find($uid);
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::error('Forbidden', 403);
        }
        
        // Safety in depth: still check if it's internal traffic if we want to be strict
        if (!\isInternalRequest()) {
            Response::error('Access restricted to internal network', 403);
        }

        $pdo = Database::getInstance();
        $rows = $pdo->query('SELECT config_key, value FROM app_config ORDER BY id DESC LIMIT 50')
            ->fetchAll(\PDO::FETCH_ASSOC);
        Response::json(['items' => $rows]);
    }

    public function logs(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $user = \JutForm\Models\User::find($uid);
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::error('Forbidden', 403);
        }
        $path = '/var/log/php/error.log';
        $lines = [];
        if (is_readable($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $lines = array_slice(array_filter(explode("\n", $content)), -100);
            }
        }
        Response::html('<pre>' . htmlspecialchars(implode("\n", $lines)) . '</pre>');
    }
}
