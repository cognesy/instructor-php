<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class OpenAIConnector extends ApiConnector
{
    private string $defaultBaseUrl = 'https://api.openai.com/v1';
    private string $organization;

    public function __construct(
        string $apiKey,
        string $baseUrl = '',
        string $organization = '',
        int    $connectTimeout = 3,
        int    $requestTimeout = 30,
        array  $metadata = [],
    ) {
        parent::__construct($apiKey, $baseUrl, $connectTimeout, $requestTimeout, $metadata);
        $this->organization = $organization;
    }

    public function resolveBaseUrl(): string {
        return $this->baseUrl ?: $this->defaultBaseUrl;
    }

    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }

    protected function defaultHeaders(): array {
        $headers = [
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'OpenAI-Organization' => $this->organization,
        ];
        return $headers;
    }
}
