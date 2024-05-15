<?php
namespace Cognesy\Instructor\Clients\Anyscale;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class AnyscaleConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.endpoints.anyscale.com/v1';

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
