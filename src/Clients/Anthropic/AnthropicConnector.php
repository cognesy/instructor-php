<?php

namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\HeaderAuthenticator;

class AnthropicConnector extends ApiConnector
{
    private string $defaultBaseUrl = 'https://api.anthropic.com/v1';

    public function resolveBaseUrl(): string {
        return $this->baseUrl ?: $this->defaultBaseUrl;
    }

    protected function defaultAuth() : Authenticator {
        return new HeaderAuthenticator($this->apiKey, 'x-api-key');
    }

    protected function defaultHeaders(): array {
        return [
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ];
    }
}