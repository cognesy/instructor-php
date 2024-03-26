<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\HeaderAuthenticator;

class AzureConnector extends ApiConnector
{
    private string $defaultBaseUrl = 'openai.azure.com';
    private string $resourceName;
    private string $deploymentId;

    public function __construct(
        string $apiKey,
        string $resourceName,
        string $deploymentId,
        string $baseUrl = '',
        int    $connectTimeout = 3,
        int    $requestTimeout = 30,
        array  $metadata = [],
    ) {
        $baseUrl = $baseUrl ?: $this->defaultBaseUrl;
        parent::__construct($apiKey, $baseUrl, $connectTimeout, $requestTimeout, $metadata);
        $this->resourceName = $resourceName;
        $this->deploymentId = $deploymentId;
    }

    public function resolveBaseUrl(): string {
        return "https://{$this->resourceName}.{$this->baseUrl}/openai/deployments/{$this->deploymentId}";
    }

    protected function defaultAuth() : Authenticator {
        return new HeaderAuthenticator($this->apiKey, 'api-key');
    }

    protected function defaultHeaders(): array {
        $headers = [
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ];
        return $headers;
    }
}
