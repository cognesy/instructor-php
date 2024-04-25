<?php

namespace Cognesy\Instructor\ApiClient\Context;

use Cognesy\Instructor\ApiClient\CacheConfig;
use Cognesy\Instructor\ApiClient\Traits\HandlesCacheConfig;

class ApiRequestContext
{
    use HandlesCacheConfig;

    public function __construct(
        CacheConfig $cacheConfig = null,
    ) {
        $this->withCacheConfig($cacheConfig);
    }
}