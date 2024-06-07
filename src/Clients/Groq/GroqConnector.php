<?php
namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\ApiConnector;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class GroqConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.groq.com/openai/v1';


    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }
}
