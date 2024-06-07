<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
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
    protected array $options = [];
    protected array $requestBody = [];
    protected array $data = [];

    // TO BE DEPRECATED?
    public array $messages = [];
    public array $tools = [];
    public string|array $toolChoice = [];
    public string|array $responseFormat = [];
    public string $model = '';

    public function __construct(
        array $body = [],
        string $endpoint = '',
        Method $method = Method::POST,
        //
        ApiRequestContext $context = null,
        array $options = [], // to consolidate into $context?
        array $data = [], // to consolidate into $context?
    ) {
        $this->context = $context;
        $this->debug = $this->options['debug'] ?? false;
        $this->cachingEnabled = $this->options['cache'] ?? false;

        if ($this->cachingEnabled) {
            if ($this->isStreamed()) {
                throw new \Exception('Cannot use cache with streamed requests');
            }
        }

        $this->options = $options;
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->requestBody = $body;
        $this->data = $data;

        // maybe replace them with $requestBody
        $this->messages = $body['messages'] ?? [];
        $this->tools = $body['tools'] ?? [];
        $this->toolChoice = $body['tool_choice'] ?? [];
        $this->responseFormat = $body['response_format'] ?? [];
        $this->model = $body['model'] ?? '';

        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->requestBody['stream'] ?? false;
    }

    protected function defaultBody(): array {
        return array_filter(
            array_merge(
                $this->requestBody,
                [
                    'messages' => $this->messages(),
                    'model' => $this->model,
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                    'response_format' => $this->getResponseFormat(),
                ]
            )
        );
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
    abstract public function tools(): array;
    abstract protected function getToolChoice(): string|array;
    abstract protected function getResponseFormat(): array;
    abstract protected function getResponseSchema(): array;
}