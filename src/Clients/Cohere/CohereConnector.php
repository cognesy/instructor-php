<?php

namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Override;

class CohereConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.cohere.com/v1/';

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}