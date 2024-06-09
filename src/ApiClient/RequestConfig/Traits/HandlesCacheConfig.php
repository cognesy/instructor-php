<?php

namespace Cognesy\Instructor\ApiClient\RequestConfig\Traits;

use Cognesy\Instructor\ApiClient\RequestConfig\CacheConfig;

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

    public function isCached() : bool {
        return $this->cacheConfig->isEnabled();
    }

    public function withCache(bool $cache = true) : static {
        $this->cacheConfig->setEnabled($cache);
        return $this;
    }
}