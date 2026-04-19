<?php

namespace JutForm\Services;

use JutForm\Core\RedisClient;

class ExternalApiService
{
    private const CACHE_KEY = 'jutform:analytics:aggregate';
    private const CACHE_TTL = 600; // 10 minutes

    public static function fetchAnalyticsAggregate(): array
    {
        $redis = RedisClient::getInstance();
        $cached = $redis->get(self::CACHE_KEY);
        if ($cached) {
            return json_decode($cached, true);
        }

        // If not cached, fetch it (but this should ideally be handled by worker)
        return self::refreshCache();
    }

    public static function refreshCache(): array
    {
        $base = getenv('EXTERNAL_API_URL') ?: 'http://mock-api:8888';
        $url = rtrim($base, '/') . '/analytics';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => 2.0,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['error' => 'upstream_unavailable'];
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : ['error' => 'invalid_json'];

        if (!isset($data['error'])) {
            RedisClient::getInstance()->setEx(self::CACHE_KEY, self::CACHE_TTL, json_encode($data));
        }

        return $data;
    }
}
