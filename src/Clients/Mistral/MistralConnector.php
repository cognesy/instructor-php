<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class MistralConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.mistral.ai/v1';

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
