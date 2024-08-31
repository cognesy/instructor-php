<?php
namespace Cognesy\Instructor\ApiClient;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;

class LLMConnector extends Connector
{
    use HasTimeout;
    use AlwaysThrowOnErrors;

    protected string $baseUrl = '';
    protected string $apiKey;
    protected array $metadata;
    protected int $connectTimeout = 3;
    protected int $requestTimeout = 30;

    public function __construct(
        string $apiKey,
        string $baseUrl = '',
        int    $connectTimeout = 3,
        int    $requestTimeout = 30,
        array  $metadata = [],
        string $senderClass = '',
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl ?: $this->baseUrl;
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;
        $this->metadata = $metadata;
        $this->defaultSender = $senderClass;
    }

    public function resolveBaseUrl(): string {
        return $this->baseUrl;
    }

    protected function defaultHeaders(): array {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function defaultConfig(): array {
        return ['stream' => true];
    }

    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
