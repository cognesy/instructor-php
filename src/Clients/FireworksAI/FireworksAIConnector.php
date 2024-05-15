<?php
namespace Cognesy\Instructor\Clients\FireworksAI;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class FireworksAIConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.fireworks.ai/inference/v1';

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
