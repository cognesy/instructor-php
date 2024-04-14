<?php

namespace Cognesy\Instructor\ApiClient\Data\Requests;

use Cognesy\Instructor\ApiClient\Traits\HandlesApiCaching;
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

    public function __construct() {
        $this->disableCaching();
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint;
    }

    abstract protected function defaultBody(): array;
}