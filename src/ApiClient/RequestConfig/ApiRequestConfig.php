<?php

namespace Cognesy\Instructor\ApiClient\RequestConfig;

use Cognesy\Instructor\Events\EventDispatcher;

class ApiRequestConfig
{
    use Traits\HandlesCacheConfig;
    use Traits\HandlesDebugConfig;
    use Traits\HandlesEvents;

    public function __construct(
        CacheConfig $cacheConfig = null,
        DebugConfig $debugConfig = null,
        EventDispatcher $events = null,
    ) {
        $this->withCacheConfig($cacheConfig);
        $this->withDebugConfig($debugConfig);
        $this->withEvents($events);
    }
}