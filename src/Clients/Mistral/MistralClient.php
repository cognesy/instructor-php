<?php

namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Override;

class MistralClient extends ApiClient
{
    public string $defaultModel = 'mistral:mistral-small';
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
        $this->withConnector($connector ?? new MistralConnector(
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
        return MistralApiRequest::class;
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
