<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Override;

class AnthropicClient extends ApiClient
{
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

    /// INTERNAL //////////////////////////////////////////////////////////////////////////////////

    #[Override]
    protected function getModeRequestClass(Mode $mode) : string {
        return AnthropicApiRequest::class;
//        return match($mode) {
//            Mode::MdJson => ChatCompletionRequest::class,
//            Mode::Json => JsonCompletionRequest::class,
//            Mode::Tools => ToolsCallRequest::class,
//            default => throw new Exception('Unknown mode')
//        };
    }

    #[Override]
    protected function isDone(string $data): bool {
        return $data === 'event: message_stop';
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