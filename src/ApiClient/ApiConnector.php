<?php
namespace Cognesy\Instructor\HttpClient;

use Cognesy\Instructor\Events\EventDispatcher;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;

class LLMConnector extends Connector
{
    use HasTimeout;
    use AlwaysThrowOnErrors;

    protected string $baseUrl;
    protected string $apiKey;
    protected array $metadata;
    protected int $connectTimeout = 3;
    protected int $requestTimeout = 30;
    protected EventDispatcher $events;

    public function __construct(
        string $apiKey,
        string $baseUrl = '',
        int    $connectTimeout = 3,
        int    $requestTimeout = 30,
        array  $metadata = [],
    ) {
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
        $this->metadata = $metadata;
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;
    }

    public function withEventDispatcher(EventDispatcher $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'stream' => false,
        ];
    }
}
