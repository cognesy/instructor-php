<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Exception;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Http\PendingRequest;

trait HandlesApiRequestCaching
{
    use HasCaching;

    public function resolveCacheDriver(): Driver {
        if (is_null($this->requestConfig()->cacheConfig())) {
            throw new Exception('Cache is not configured for this request');
        }
        return $this->requestConfig()->cacheConfig()->getDriver();
    }

    public function cacheExpiryInSeconds(): int {
        if (in_null($this->requestConfig()->cacheConfig())) {
            throw new Exception('Cache is not configured for this request');
        }
        return $this->requestConfig()->cacheConfig()->expiryInSeconds();
    }

    protected function getCacheableMethods(): array {
        return $this->requestConfig()->cacheConfig()->cacheableMethods();
    }

    protected function cacheKey(PendingRequest $pendingRequest): ?string {
        return $this->requestConfig()->cacheConfig()->cacheKey($pendingRequest);
    }
}