<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\ApiClient\Traits\HandlesApiRequestContext;
use Cognesy\Instructor\Traits\HandlesApiCaching;
use Cognesy\Instructor\Utils\Json;
use Exception;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

abstract class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody;
    use HandlesApiCaching;
    use HandlesApiRequestContext;

    protected Method $method = Method::POST;
    protected bool $debug = false;
    protected string $prompt = '';

    public function __construct(
        public array $options = [],
        public string $endpoint = '',
    ) {
        $this->debug = $this->options['debug'] ?? false;
        unset($this->options['debug']);

        $this->cachingEnabled = $this->options['cache'] ?? false;
        unset($this->options['cache']);

        if ($this->cachingEnabled) {
            if ($this->isStreamed()) {
                throw new \Exception('Cannot use cache with streamed requests');
            }
        }
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function isDebug(): bool {
        return $this->debug;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function withPrompt(string $prompt) : static {
        $this->prompt = $prompt;
        return $this;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint ?: $this->defaultEndpoint;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    protected function appendInstructions(array $messages, string $prompt, array $jsonSchema) : array {
        if (empty($messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }
        $lastIndex = count($messages) - 1;
        if (!empty($prompt)) {
            $messages[$lastIndex]['content'] .= $prompt;
        }
        if (!empty($jsonSchema)) {
            $messages[$lastIndex]['content'] .= Json::encode($jsonSchema);
        }
        return $messages;
    }

    abstract protected function defaultBody(): array;
    abstract public function toApiResponse(Response $response) : ApiResponse;
    abstract public function toPartialApiResponse(string $partialData) : PartialApiResponse;
}