<?php
namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class GroqConnector extends ApiConnector
{
    private string $defaultBaseUrl = 'https://api.groq.com/openai/v1';

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
        ];
    }
}
