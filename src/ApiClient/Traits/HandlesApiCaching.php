<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\CacheConfig;
use Cognesy\Instructor\Utils\Json;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;

trait HandlesApiCaching
{
    use HasCaching;

    protected CacheConfig $cacheConfig;

    public function withCacheConfig(CacheConfig $cacheConfig): static {
        $this->cacheConfig = $cacheConfig;
        if ($cacheConfig->isEnabled()) {
            $this->enableCaching();
        } else {
            $this->disableCaching();
            $this->invalidateCache();
        }
        return $this;
    }

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
        return [Method::GET, Method::OPTIONS, Method::POST];
    }

    protected function cacheKey(PendingRequest $pendingRequest): string {
        $keyBase = implode('|', [
            get_class($this),
            $pendingRequest->getUrl(),
            Json::encode($this->defaultBody()),
        ]);
        return md5($keyBase);
    }
}