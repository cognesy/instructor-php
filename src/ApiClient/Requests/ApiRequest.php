<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Data\Messages\Script;
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
    protected Script $script;
    protected array $scriptContext = [];

    // BODY FIELDS
    protected string $model = '';
    protected int $maxTokens = 512;
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

    protected function applyData() : void {
        $this->mode = $this->getData('mode', Mode::MdJson);
        $this->script = $this->getData('script', new Script());
        $this->scriptContext = $this->getData('script_context', []);
    }

    protected function initBodyFields() : void {
        $this->model = $this->pullBodyField('model', '');
        $this->maxTokens = $this->pullBodyField('max_tokens', 512);
        $this->messages = $this->pullBodyField('messages', []);

        // get tools and format
        if ($this->mode->is(Mode::Tools)) {
            $this->tools = $this->getData('tools', []);
            $this->toolChoice = $this->getData('tool_choice', []);
        } elseif ($this->mode->is(Mode::Json)) {
            $this->responseFormat = $this->getData('response_format', []);
        }
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

    protected function getData(string $name, mixed $defaultValue) : mixed {
        return $this->data[$name] ?? $defaultValue;
    }

    protected function pullBodyField(string $name, mixed $default = null): mixed {
        $value = $this->requestBody[$name] ?? $default;
        unset($this->requestBody[$name]);
        return $value;
    }

    protected function noScript() : bool {
        return empty($this->script) || $this->script->isEmpty();
    }

    abstract public function toApiResponse(Response $response): ApiResponse;
    abstract public function toPartialApiResponse(string $partialData): PartialApiResponse;
}