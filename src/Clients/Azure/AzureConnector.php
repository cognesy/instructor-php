<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\LLMConnector;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\HeaderAuthenticator;

class AzureConnector extends LLMConnector
{
    protected string $baseUrl = '';
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
    ) {
        parent::__construct($apiKey, $baseUrl, $connectTimeout, $requestTimeout, $metadata, $senderClass);
        $this->resourceName = $resourceName;
        $this->deploymentId = $deploymentId;
    }

    public function resolveBaseUrl(): string {
        return str_replace(
                search: array_map(fn($key) => "{".$key."}", array_keys($this->metadata)),
                replace: array_values($this->metadata),
                subject: "{$this->baseUrl}/chat/completions"
            ) . $this->getUrlParams();
    }

    protected function getUrlParams(): string {
        $params = array_filter([
            'api-version' => $this->metadata['apiVersion'] ?? '',
        ]);
        if (!empty($params)) {
            return '?' . http_build_query($params);
        }
        return '';
    }

    protected function defaultAuth() : Authenticator {
        return new HeaderAuthenticator($this->apiKey, 'api-key');
    }
}
