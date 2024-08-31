<?php

namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\LLMConnector;
use Saloon\Http\Auth\QueryAuthenticator;

class GeminiConnector extends LLMConnector
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    protected function defaultAuth() : QueryAuthenticator {
        return new QueryAuthenticator('key', $this->apiKey);
    }
}
