<?php
namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Enums\Mode;

trait HandlesApiRequestParams
{
    protected array $data = [];
    protected string $endpoint = '';
    protected string $method = 'POST';
    protected Mode $mode;
    protected string $model;
    protected array $options = [];
    protected ApiRequestConfig $requestConfig;

    public function data() : array {
        return array_filter(array_merge(
            $this->data,
            [
                'mode' => $this->mode(),
                'client_type' => ClientType::fromClientClass($this->client()),
                'tools' => $this->toolCallSchema() ?? [],
                'tool_choice' => $this->toolChoice() ?? [],
                'json_schema' => $this->jsonSchema() ?? [],
                'schema_name' => $this->schemaName() ?? '',
                'cached_context' => $this->cachedContext ?? [],
            ]
        ));
    }

    public function endpoint() : string {
        return $this->endpoint;
    }

    public function isStream() : bool {
        return $this->option('stream', false);
    }

    public function method() : string {
        return $this->method;
    }

    public function mode() : Mode {
        return $this->mode;
    }

    public function model() : string {
        return $this->model ?: $this->client->defaultModel();
    }

    public function option(string $key, mixed $defaultValue = null) : mixed {
        return $this->options[$key] ?? $defaultValue;
    }

    public function options() : array {
        return $this->options;
    }

    public function requestConfig() : ApiRequestConfig {
        return $this->requestConfig;
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function withModel(string $model) : self {
        $this->model = $model;
        return $this;
    }

    protected function setOption(string $name, mixed $value) : self {
        $this->options[$name] = $value;
        return $this;
    }

    protected function unsetOption(string $name) : self {
        unset($this->options[$name]);
        return $this;
    }
}