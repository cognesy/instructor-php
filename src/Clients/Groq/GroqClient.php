<?php

namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesStreamData;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Override;

class GroqClient extends ApiClient
{
    use HandlesStreamData;

    public string $defaultModel = 'groq:llama3-8b';
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
        $this->withConnector($connector ?? new GroqConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }


    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    #[Override]
    protected function getModeRequestClass(Mode $mode) : string {
        return GroqApiRequest::class;
    }
}
