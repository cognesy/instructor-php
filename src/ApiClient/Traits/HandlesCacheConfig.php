<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\CacheConfig;

trait HandlesCacheConfig
{
    protected CacheConfig $cacheConfig;

    public function withCacheConfig(CacheConfig $cacheConfig = null): static {
        $cacheConfig = $cacheConfig ?? new CacheConfig();
        $this->cacheConfig = $cacheConfig;
        return $this;
    }

    public function cacheConfig(): CacheConfig {
        return $this->cacheConfig;
    }
}