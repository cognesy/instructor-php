<?php
namespace Cognesy\Instructor\Clients\OpenRouter;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Traits\HandlesStreamData;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;


class OpenRouterClient extends ApiClient
{
    use HandlesStreamData;

    public string $defaultModel = 'openrouter:gpt-3.5-turbo';
    public int $defaultMaxTokens = 1024;

    public function __construct(
        protected $apiKey = '',
        protected $baseUri = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);
        $this->withConnector($connector ?? new OpenRouterConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }

    public function getModeRequestClass(Mode $mode = null) : string {
        return OpenRouterApiRequest::class;
    }
}
