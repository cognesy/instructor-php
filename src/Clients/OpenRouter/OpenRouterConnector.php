<?php
namespace Cognesy\Instructor\Clients\OpenRouter;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class OpenRouterConnector extends ApiConnector
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
