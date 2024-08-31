<?php
namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\LLMClient;
use Cognesy\Instructor\ApiClient\LLMConnector;
use Cognesy\Instructor\Events\EventDispatcher;

class GeminiClient extends LLMClient
{
    public function __construct(
        protected string $apiKey = '',
        protected string $baseUri = '',
        protected int    $connectTimeout = 3,
        protected int    $requestTimeout = 30,
        protected array  $metadata = [],
        EventDispatcher  $events = null,
        LLMConnector     $connector = null,
    ) {
        parent::__construct($events);
        $this->withConnector($connector ?? new GeminiConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }
}
