<?php

namespace Cognesy\Instructor\ApiClient\Context;

use Cognesy\Instructor\ApiClient\CacheConfig;

class ApiRequestContext
{
    use Traits\HandlesCacheConfig;

    public function __construct(
        CacheConfig $cacheConfig = null,
    ) {
        $this->withCacheConfig($cacheConfig);
    }
}