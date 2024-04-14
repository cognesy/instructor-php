<?php

namespace Cognesy\Instructor\ApiClient\Data\Requests;

use Cognesy\Instructor\ApiClient\CacheConfig;
use Cognesy\Instructor\Utils\Json;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

abstract class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody, HasCaching;

    protected string $endpoint;
    protected Method $method = Method::POST;
    protected CacheConfig $cacheConfig;

    public function __construct() {
        $this->disableCaching();
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

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

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint;
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

    abstract protected function defaultBody(): array;
}