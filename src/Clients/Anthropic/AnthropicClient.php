<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Override;

class AnthropicClient extends ApiClient
{
    use Traits\HandlesStreamData;

    public string $defaultModel = 'anthropic:claude-3-haiku';
    public int $defaultMaxTokens = 256;

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
        $this->withConnector($connector ?? new AnthropicConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }

    #[Override]
    public function getModeRequestClass(Mode $mode) : string {
        return AnthropicApiRequest::class;
    }
}