<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesApiRequestContext;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Traits\HandlesApiCaching;
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

    protected string $defaultEndpoint = '/chat/completions';
    protected Method $method = Method::POST;
    protected bool $debug = false;

    public function __construct(
        public string|array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public string|array $responseFormat = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        $this->messages = $this->normalizeMessages($messages);

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

    public function resolveEndpoint() : string {
        return $this->endpoint ?: $this->defaultEndpoint;
    }

    protected function defaultBody(): array {
        return array_filter(array_merge([
            'messages' => $this->messages(),
            'model' => $this->model,
            'tools' => $this->tools(),
            'tool_choice' => $this->getToolChoice(),
            'response_format' => $this->getResponseFormat(),
        ], $this->options));
    }

    public function messages(): array {
        return $this->messages;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
    abstract public function tools(): array;
    abstract protected function getToolChoice(): string|array;
    abstract protected function getResponseFormat(): array;
    abstract protected function getResponseSchema(): array;
}