<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Events\EventDispatcher;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\HeaderAuthenticator;

class AzureConnector extends ApiConnector
{
    protected string $baseUrl = 'openai.azure.com';
    protected string $resourceName;
    protected string $deploymentId;

    public function __construct(
        string $apiKey,
        string $resourceName,
        string $deploymentId,
        string $baseUrl = '',
        int    $connectTimeout = 3,
        int    $requestTimeout = 30,
        array  $metadata = [],
        string $senderClass = '',
        EventDispatcher $events = null,
    ) {
        parent::__construct($apiKey, $baseUrl, $connectTimeout, $requestTimeout, $metadata, $senderClass, $events);
        $this->resourceName = $resourceName;
        $this->deploymentId = $deploymentId;
    }

    public function resolveBaseUrl(): string {
        return "https://{$this->resourceName}.{$this->baseUrl}/openai/deployments/{$this->deploymentId}";
    }

    protected function defaultAuth() : Authenticator {
        return new HeaderAuthenticator($this->apiKey, 'api-key');
    }
}
