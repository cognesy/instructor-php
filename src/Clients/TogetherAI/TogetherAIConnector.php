<?php
namespace Cognesy\Instructor\Clients\TogetherAI;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class TogetherAIConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.together.xyz/v1';

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
