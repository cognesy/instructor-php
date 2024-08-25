<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Enums\Mode;
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
    use Traits\HandlesRequestBody;
    use Traits\HandlesTransformation;

    protected Method $method = Method::POST;
    protected array $requestBody = [];
    protected array $settings = [];
    protected array $data = [];

    // NEW
    protected Mode $mode;
    protected ClientType $clientType;
    protected array $jsonSchema = [];
    protected string $schemaName = '';
    protected array $cachedContext = [];

    // BODY FIELDS
    protected string $model = '';
    protected int $maxTokens = 1024;
    protected array $messages = [];
    protected array $tools = [];
    protected string|array $toolChoice = [];
    protected string|array $responseFormat = [];

    public function __construct(
        array $body = [],
        string $endpoint = '',
        Method $method = Method::POST,
        ApiRequestConfig $requestConfig = null,
        array $data = [],
    ) {
        // set properties
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->requestBody = $body;
        $this->requestConfig = $requestConfig;
        $this->data = $data;
        // finish request setup
        $this->applyRequestConfig();
        $this->applyData();
        $this->initBodyFields();
        // set flags
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->requestBody['stream'] ?? false;
    }

    protected function applyData() : void {
        $this->mode = $this->getData('mode', Mode::MdJson);
        $this->clientType = $this->getData('client_type', ClientType::fromRequestClass(static::class));
        $this->jsonSchema = $this->getData('json_schema', []);
        $this->schemaName = $this->getData('schema_name', '');
        $this->cachedContext = $this->getData('cached_context', []);
    }

    protected function initBodyFields() : void {
        $this->model = $this->pullBodyField('model', $this->model());
        $this->maxTokens = $this->pullBodyField('max_tokens', 1024);
        $this->messages = $this->pullBodyField('messages', []);

        // get tools and format
        if ($this->mode->is(Mode::Tools)) {
            $this->tools = $this->getData('tools', []);
            $this->toolChoice = $this->getData('tool_choice', []);
        } elseif ($this->mode->is(Mode::Json)) {
            $this->responseFormat = [
                'type' => 'json_object',
                'schema' => $this->jsonSchema,
            ];
        } elseif ($this->mode->is(Mode::JsonSchema)) {
            $this->responseFormat = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $this->schemaName,
                    'schema' => $this->jsonSchema,
                ],
            ];
            $this->responseFormat['json_schema']['strict'] = true;
        }
    }

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'max_tokens' => $this->maxTokens,
                    'messages' => $this->messages(),
                ],
            )
        );
        switch($this->mode) {
            case Mode::Tools:
                $body['tools'] = $this->tools();
                $body['tool_choice'] = $this->getToolChoice();
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $body['response_format'] = $this->getResponseFormat();
                break;
        }
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    protected function getData(string $name, mixed $defaultValue) : mixed {
        return $this->data[$name] ?? $defaultValue;
    }

    protected function pullBodyField(string $name, mixed $default = null): mixed {
        $value = $this->requestBody[$name] ?? $default;
        unset($this->requestBody[$name]);
        return $value;
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
}