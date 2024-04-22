<?php

namespace Cognesy\Instructor\ApiClient\Data\Requests;

use Cognesy\Instructor\ApiClient\CacheConfig;
use Cognesy\Instructor\Traits\HandlesApiCaching;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

abstract class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody;
    use HandlesApiCaching;

    protected string $endpoint;
    protected Method $method = Method::POST;
    protected bool $debug = false;
    protected bool $useCache = false;

    public function __construct(
        public array $options = []
    ) {
        if (isset($this->options['debug'])) {
            $this->debug = $this->options['debug'];
            unset($this->options['debug']);
        }
        if (isset($this->options['cache'])) {
            $this->useCache = $this->options['cache'];
            unset($this->options['cache']);
        }
        if ($this->useCache) {
            $this->withCacheConfig(new CacheConfig(enabled: $this->useCache));
            $this->enableCaching();
        } else {
            $this->disableCaching();
        }
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function isDebug(): bool {
        return $this->debug;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint;
    }

    abstract protected function defaultBody(): array;
}