<?php

namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Exception;
use Override;

class GroqClient extends ApiClient
{
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
        return ApiRequest::class;
//        return match($mode) {
//            Mode::MdJson => ApiRequest::class,
//            Mode::Json => ApiRequest::class,
//            Mode::Tools => ApiRequest::class,
//            default => throw new Exception('Unknown mode')
//        };
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
