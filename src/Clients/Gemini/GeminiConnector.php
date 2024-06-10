<?php

namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Http\Auth\QueryAuthenticator;

class GeminiConnector extends ApiConnector
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    protected function defaultAuth() : QueryAuthenticator {
        return new QueryAuthenticator('key', $this->apiKey);
    }
}
