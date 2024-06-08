<?php

namespace Cognesy\Instructor\ApiClient\RequestConfig;

class ApiRequestConfig
{
    use Traits\HandlesCacheConfig;
    use Traits\HandlesDebugConfig;

    public function __construct(
        CacheConfig $cacheConfig = null,
        DebugConfig $debugConfig = null,
    ) {
        $this->withCacheConfig($cacheConfig);
        $this->withDebugConfig($debugConfig);
    }
}