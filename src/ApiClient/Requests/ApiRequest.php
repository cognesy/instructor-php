<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Data\Messages\Script;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

abstract class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody;

    use Traits\HandlesApiRequestCaching;
    use Traits\HandlesApiRequestConfig;
    use Traits\HandlesEndpoint;
    use Traits\HandlesMessages;

    protected Method $method = Method::POST;
    protected array $requestBody = [];
    protected array $settings = [];
    protected array $data = [];

    // NEW
    protected Script $script;
    protected array $scriptContext = [];
    protected array $messages = [];

    // TO BE DEPRECATED?
    protected array $tools = [];
    protected string|array $toolChoice = [];
    protected string|array $responseFormat = [];
    protected string $model = '';

    public function __construct(
        array            $body = [],
        string           $endpoint = '',
        Method           $method = Method::POST,
        //
        ApiRequestConfig $requestConfig = null,
        array            $data = [], // to consolidate into $context?
    ) {
        $this->requestConfig = $requestConfig;
        if (!is_null($requestConfig)) {
            $this->cachingEnabled = $requestConfig->cacheConfig()->isEnabled();
            if ($this->cachingEnabled & $this->isStreamed()) {
                throw new \Exception('Instructor does not support caching with streamed requests');
            }
        }

        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->requestBody = $body;
        $this->data = $data;

        // TODO: maybe move pulled var to $data when it is prepared by ApiRequestFactory?
        $this->messages = $this->pullVar('messages', []);
        $this->tools = $this->pullVar('tools', []);
        $this->toolChoice = $this->pullVar('tool_choice', []);
        $this->responseFormat = $this->pullVar('response_format', []);
        $this->model = $this->pullVar('model', '');

        $this->script = $this->data['script'] ?? new Script();
        $this->scriptContext = $this->data['context'] ?? [];

        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    private function pullVar(string $name, mixed $default = null): mixed {
        $value = $this->requestBody[$name] ?? $default;
        unset($this->requestBody[$name]);
        return $value;
    }

    public function isStreamed(): bool {
        return $this->requestBody['stream'] ?? false;
    }

    protected function defaultBody(): array {
        $body = array_filter(
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
        return $body;
    }

    public function toArray() : array {
        return [
            'class' => static::class,
            'endpoint' => $this->resolveEndpoint(),
            'method' => $this->method,
            'body' => $this->defaultBody(),
        ];
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
    abstract protected function tools(): array;
    abstract protected function getToolChoice(): string|array;
    abstract protected function getResponseFormat(): array;
    abstract protected function getResponseSchema(): array;
}