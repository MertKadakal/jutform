<?php

namespace JutForm\Workers;

use JutForm\Services\ExternalApiService;

class AnalyticsWorker
{
    public static function handle(array $data): void
    {
        // Simply trigger a refresh of the cache
        ExternalApiService::refreshCache();
    }
}
