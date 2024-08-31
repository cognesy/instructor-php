<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\LLMClient;
use Cognesy\Instructor\ApiClient\LLMConnector;
use Cognesy\Instructor\Events\EventDispatcher;


class OpenAIClient extends LLMClient
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

        $organization = $metadata['organization'] ?? '';

        $this->withConnector($connector ?? new OpenAIConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            organization: $organization,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }
}
