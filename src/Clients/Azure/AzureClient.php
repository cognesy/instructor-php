<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Exception;
use Override;

class AzureClient extends ApiClient
{
    public string $defaultModel = 'azure:gpt-3.5-turbo'; //'gpt-4-turbo-preview';
    public int $defaultMaxTokens = 256;

    public function __construct(
        protected string $apiKey = '',
        protected string $resourceName = '',
        protected string $deploymentId = '',
        protected string $apiVersion = '',
        protected string $baseUri = '',
        protected int $connectTimeout = 3,
        protected int $requestTimeout = 30,
        protected array $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);
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

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    #[Override]
    protected function getModeRequestClass(Mode $mode) : string {
        return match($mode) {
            Mode::MdJson => ApiRequest::class,
            Mode::Json => ApiRequest::class,
            Mode::Tools => ApiRequest::class,
            default => throw new Exception('Unknown mode')
        };
    }

    #[Override]
    protected function isDone(string $data): bool {
        return $data === '[DONE]';
    }

    #[Override]
    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }
}
