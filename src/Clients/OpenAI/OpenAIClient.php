<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Exception;

class OpenAIClient extends ApiClient
{
    public string $defaultModel = 'openai:gpt-4-turbo';
    public int $defaultMaxTokens = 256;

    public function __construct(
        protected $apiKey = '',
        protected $baseUri = '',
        protected $organization = '',
        protected $connectTimeout = 3,
        protected $requestTimeout = 30,
        protected $metadata = [],
        EventDispatcher $events = null,
        ApiConnector $connector = null,
    ) {
        parent::__construct($events);
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

    /// INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function getModeRequestClass(Mode $mode) : string {
        return match($mode) {
            Mode::MdJson => ChatCompletionRequest::class,
            Mode::Json => JsonCompletionRequest::class,
            Mode::Tools => ToolsCallRequest::class,
            default => throw new Exception('Unknown mode')
        };
    }

    protected function isDone(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }
}
