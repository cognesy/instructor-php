<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Http\PendingRequest;

trait HandlesApiCaching
{
    use HasCaching;

    public function resolveCacheDriver(): Driver {
        if (empty($this->context()->cacheConfig())) {
            throw new \Exception('Cache is not configured for this request');
        }
        return $this->context()->cacheConfig()->getDriver();
    }

    public function cacheExpiryInSeconds(): int {
        if (empty($this->context()->cacheConfig())) {
            throw new \Exception('Cache is not configured for this request');
        }
        return $this->context()->cacheConfig()->expiryInSeconds();
    }

    protected function getCacheableMethods(): array {
        return $this->context()->cacheConfig()->cacheableMethods();
    }

    protected function cacheKey(PendingRequest $pendingRequest): ?string {
        return $this->context()->cacheConfig()->cacheKey($pendingRequest);
    }
}