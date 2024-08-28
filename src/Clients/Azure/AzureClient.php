<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;

class AzureClient extends ApiClient
{
    public string $defaultModel = 'gpt-3.5-turbo';
    public int $defaultMaxTokens = 1024;

    public function __construct(
        protected string $apiKey = '',
        protected string $baseUri = '',
        protected int $connectTimeout = 3,
        protected int $requestTimeout = 30,
        protected array $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);

        $resourceName = $metadata['resourceName'] ?? '';
        $deploymentId = $metadata['deploymentId'] ?? '';
        $apiVersion = $metadata['apiVersion'] ?? '';

        $this->withConnector($connector ?? new AzureConnector(
            apiKey: $apiKey,
            resourceName: $resourceName,
            deploymentId: $deploymentId,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
        $this->queryParams = ['api-version' => $apiVersion];
    }


    public function getModeRequestClass(Mode $mode = null) : string {
        return AzureApiRequest::class;
    }
}
