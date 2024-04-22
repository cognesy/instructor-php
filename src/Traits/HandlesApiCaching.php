<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\ApiClient\Traits\HandlesCacheConfig;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Http\PendingRequest;

trait HandlesApiCaching
{
    use HasCaching;
    use HandlesCacheConfig;

    public function resolveCacheDriver(): Driver {
        if (!isset($this->cacheConfig)) {
            throw new \Exception('Cache is not configured for this request');
        }
        return $this->cacheConfig->getDriver();
    }

    public function cacheExpiryInSeconds(): int {
        if (!isset($this->cacheConfig)) {
            throw new \Exception('Cache is not configured for this request');
        }
        return $this->cacheConfig->expiryInSeconds();
    }

    protected function getCacheableMethods(): array {
        return $this->cacheConfig->cacheableMethods();
    }

    protected function cacheKey(PendingRequest $pendingRequest): ?string {
        return $this->cacheConfig->cacheKey($pendingRequest);
    }
}