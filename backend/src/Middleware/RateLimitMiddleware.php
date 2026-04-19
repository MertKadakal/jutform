<?php

namespace JutForm\Middleware;

use JutForm\Core\Request;
use JutForm\Core\Response;

class RateLimitMiddleware
{
    public function handle(Request $request): void
    {
        $ip = $request->ip();
        $key = "ratelimit:sub:" . $ip;
        
        $redis = new \Redis();
        try {
            $redis->connect(getenv('REDIS_HOST') ?: 'redis', (int)(getenv('REDIS_PORT') ?: 6379));
        } catch (\Exception $e) {
            // If redis is down, fallback to allowing (or logging), but here we allow
            return;
        }

        $now = microtime(true);
        $window = 5.0; // 5 seconds
        $maxRequests = 10;

        // Clean up old timestamps from the sliding window
        $redis->zRemRangeByScore($key, 0, $now - $window);
        
        // Count recent requests
        $requestCount = $redis->zCard($key);

        if ($requestCount >= $maxRequests) {
            // Get the oldest timestamp in the current window to calculate wait time
            $oldest = $redis->zRange($key, 0, 0, true);
            $oldestTime = (float) current($oldest);
            $retryAfter = (int) ceil($window - ($now - $oldestTime));
            
            Response::raw(json_encode(['error' => 'Too Many Requests']), 429, [
                'Content-Type' => 'application/json',
                'Retry-After' => (string)max(1, $retryAfter)
            ]);
        }

        // Add current request to the window
        $redis->zAdd($key, $now, (string)$now);
        $redis->expire($key, (int)$window + 1);
    }
}
