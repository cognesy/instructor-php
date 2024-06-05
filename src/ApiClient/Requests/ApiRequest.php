<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Override;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

abstract class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody;
    use Traits\HandlesApiCaching;
    use Traits\HandlesApiRequestContext;
    use Traits\HandlesMessages;
    use Traits\HandlesEndpoint;
    use Traits\HandlesDebug;

    protected Method $method = Method::POST;

    public function __construct(
        public array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public string|array $responseFormat = [],
        public string $model = '',
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

    protected function defaultBody(): array {
        return array_filter(
            array_merge([
                'messages' => $this->messages(),
                'model' => $this->model,
                'tools' => $this->tools(),
                'tool_choice' => $this->getToolChoice(),
                'response_format' => $this->getResponseFormat(),
            ], $this->options)
        );
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
    abstract public function tools(): array;
    abstract protected function getToolChoice(): string|array;
    abstract protected function getResponseFormat(): array;
    abstract protected function getResponseSchema(): array;
}