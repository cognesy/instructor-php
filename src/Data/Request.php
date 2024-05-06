<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\ModelFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Json;

class Request
{
    private string $defaultFunctionName = 'extract_data';
    private string $defaultFunctionDescription = 'Extract data from provided content';
    private string $defaultRetryPrompt = "JSON generated incorrectly, fix following errors: ";

    private array $defaultPrompts = [
        Mode::MdJson->value => "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n",
        Mode::Json->value => "\nRespond correctly with JSON object. Response must follow JSONSchema:\n",
        Mode::Tools->value => "\nExtract correct and accurate data from the messages using provided tools. Response must be JSON object following provided schema.\n",
    ];

    private ?ModelFactory $modelFactory;
    private ?CanCallApi $client;

    private Mode $mode;

    private string|array $messages;
    private string $model;
    private array $options = [];

    private int $maxRetries;
    private string $retryPrompt;

    private string|array|object $requestedSchema;
    private string $prompt;
    private string $functionName;
    private string $functionDescription;

    private ?ResponseModel $responseModel = null;
    private ModelParams $modelParams;

    public function __construct(
        string|array $messages,
        string|object|array $responseModel,
        string|ModelParams $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = '',
        string $functionDescription = '',
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
        CanCallApi $client = null,
        ModelFactory $modelFactory = null,
    ) {
        $this->messages = $messages;
        $this->requestedSchema = $responseModel;
        $this->maxRetries = $maxRetries;
        $this->options = $options;
        $this->functionName = $functionName ?: $this->defaultFunctionName;
        $this->functionDescription = $functionDescription ?: $this->defaultFunctionDescription;
        $this->mode = $mode;
        $this->client = $client;
        $this->prompt = $prompt ?: $this->defaultPrompts[$this->mode->value];
        $this->retryPrompt = $retryPrompt ?: $this->defaultRetryPrompt;
        $this->withModel($model);
        $this->modelFactory = $modelFactory;
    }

    public function client() : ?CanCallApi {
        return $this->client;
    }

    public function withClient(CanCallApi $client) : self {
        $this->client = $client;
        return $this;
    }

    public function responseModel() : ?ResponseModel {
        return $this->responseModel;
    }

    public function withResponseModel(ResponseModel $responseModel) : self {
        $this->responseModel = $responseModel;
        return $this;
    }

    public function functionName() : string {
        return $this->responseModel ? $this->responseModel->functionName() : $this->functionName;
    }

    public function functionDescription() : string {
        return $this->responseModel ? $this->responseModel->functionDescription() : $this->functionDescription;
    }

    public function jsonSchema() : array {
        return $this->responseModel->jsonSchema();
    }

    public function toolCallSchema() : array {
        return $this->responseModel->toolCallSchema();
    }

    public function mode() : Mode {
        return $this->mode;
    }

    public function maxRetries() : int {
        return $this->maxRetries;
    }

    public function messages() : array {
        if (is_string($this->messages)) {
            return [['role' => 'user', 'content' => $this->messages]];
        }
        return $this->messages;
    }

    public function withMessages(array $messages) : self {
        $this->messages = $messages;
        return $this;
    }

    public function prompt() : string {
        return $this->prompt ?: $this->defaultPrompts[$this->mode->value];
    }

    public function model() : string {
        return $this->model;
    }

    public function modelName() : string {
        if (isset($this->modelParams)) {
            return $this->modelParams->name;
        }
        if ($this->modelFactory?->has($this->model)) {
            return $this->modelFactory->get($this->model)->name;
        }
        return $this->model;
    }

    public function withModel(string|ModelParams $model) : self {
        if ($model instanceof ModelParams) {
            $this->modelParams = $model;
            $this->model = $model->name;
        } else {
            $this->model = $model;
        }
        return $this;
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

    public function retryPrompt() : string {
        return $this->retryPrompt;
    }

    public function requestedSchema() : string|array|object {
        return $this->requestedSchema;
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $key, mixed $defaultValue = null) : mixed {
        if (!isset($this->options[$key])) {
            return $defaultValue;
        }
        return $this->options[$key];
    }

    public function setOption(string $name, mixed $value) : self {
        $this->options[$name] = $value;
        return $this;
    }

    public function unsetOption(string $name) : self {
        unset($this->options[$name]);
        return $this;
    }

    public function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt() . Json::encode($jsonSchema);
        return $messages;
    }

    public function copy(array $messages) : self {
        return (clone $this)->withMessages($messages);
    }
}
