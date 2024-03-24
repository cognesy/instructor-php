<?php
namespace Cognesy\Instructor\HttpClient\Mistral;

use Cognesy\Instructor\HttpClient\LLMConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class OpenAIConnector extends LLMConnector
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
