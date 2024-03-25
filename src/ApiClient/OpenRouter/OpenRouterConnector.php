<?php
namespace Cognesy\Instructor\ApiClient\OpenRouter;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class OpenRouterConnector extends ApiConnector
{
    private string $defaultBaseUrl = 'https://api.openai.com/v1';

    public function resolveBaseUrl(): string {
        return $this->baseUrl ?: $this->defaultBaseUrl;
    }

    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }

    protected function defaultHeaders(): array {
        return [
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'OpenAI-Organization' => $this->metadata['organization'] ?? '',
        ];
    }
}
