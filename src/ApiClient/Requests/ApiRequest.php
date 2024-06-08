<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;
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
    use Traits\HandlesRequestData;
    use Traits\HandlesTransformation;

    protected Method $method = Method::POST;
    protected array $requestBody = [];
    protected array $settings = [];
    protected array $data = [];

    // NEW
    protected Script $script;
    protected array $scriptContext = [];

    // TO BE DEPRECATED?
    protected array $messages = [];
    protected array $tools = [];
    protected string|array $toolChoice = [];
    protected string|array $responseFormat = [];
    protected string $model = '';

    public function __construct(
        array $body = [],
        string $endpoint = '',
        Method $method = Method::POST,
        ApiRequestConfig $requestConfig = null,
        array $data = [],
    ) {
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->requestBody = $body;
        $this->data = $data;

        $this->requestConfig = $requestConfig;
        if (!is_null($requestConfig)) {
            $this->cachingEnabled = $requestConfig->cacheConfig()->isEnabled();
            if ($this->cachingEnabled & $this->isStreamed()) {
                throw new \Exception('Instructor does not support caching with streamed requests');
            }
        }

        // pull fields from request body
        $this->messages = $this->pullBodyField('messages', []);
        $this->model = $this->pullBodyField('model', '');

        // get parameter values
        $this->tools = $this->getData('tools', []);
        $this->toolChoice = $this->getData('tool_choice', []);
        $this->responseFormat = $this->getData('response_format', []);

        $this->script = $this->getData('script', new Script());
        $this->scriptContext = $this->getData('context', []);

        // set flags
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'messages' => $this->messages(),
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                    'response_format' => $this->getResponseFormat(),
                ]
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
    abstract protected function tools(): array;
    abstract protected function getToolChoice(): string|array;
    abstract protected function getResponseFormat(): array;
    abstract protected function getResponseSchema(): array;
}