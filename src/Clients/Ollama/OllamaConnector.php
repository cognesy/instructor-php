<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class OllamaConnector extends ApiConnector
{
    protected string $baseUrl = 'http://localhost:11434/v1';

    public function __construct(
        string $apiKey,
        string $baseUrl = '',
        int    $connectTimeout = 3,
        int    $requestTimeout = 30,
        array  $metadata = [],
        string $senderClass = '',
    ) {
        parent::__construct($apiKey, $baseUrl, $connectTimeout, $requestTimeout, $metadata, $senderClass);
    }

    #[Override]
    protected function defaultAuth() : Authenticator {
        return new TokenAuthenticator($this->apiKey);
    }

    #[Override]
    protected function defaultHeaders(): array {
        $headers = [
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ];
        return $headers;
    }
}
