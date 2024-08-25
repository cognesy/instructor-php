<?php
namespace Cognesy\Instructor\Clients\FireworksAI;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Traits\HandlesStreamData;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;


class FireworksAIClient extends ApiClient
{
    use HandlesStreamData;

    public string $defaultModel = 'accounts/fireworks/models/mixtral-8x7b-instruct';
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
        $this->withConnector($connector ?? new FireworksAIConnector(
            apiKey: $apiKey,
            baseUrl: $baseUri,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            metadata: $metadata,
            senderClass: '',
        ));
    }


    public function getModeRequestClass(Mode $mode = null) : string {
        return FireworksAIApiRequest::class;
    }
}
