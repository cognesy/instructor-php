<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Json;

class Request
{
    private string $defaultFunctionName = 'extract_data';
    private string $defaultFunctionDescription = 'Extract data from provided content';
    private string $defaultRetryPrompt = "JSON generated incorrectly, fix following errors";

    private array $defaultPrompts = [
        Mode::MdJson->value => "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n",
        Mode::Json->value => "\nRespond correctly with JSON object. Response must follow JSONSchema:\n",
        Mode::Tools->value => "\nExtract correct and accurate data from the messages using provided tools. Response must be JSON object following provided schema.\n",
    ];

    public ?CanCallApi $client = null;
    public Mode $mode = Mode::Tools;

    public string|array $messages;
    public string $model = '';
    public array $options = [];

    public int $maxRetries = 0;
    public string $retryPrompt = '';

    public string|object|array $requestedModel;
    public string $functionName = '';
    public string $functionDescription = '';

    private ?ResponseModel $responseModel = null;

    public function __construct(
        string|array $messages,
        string|object|array $responseModel,
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = '',
        string $functionDescription = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
        CanCallApi $client = null,
    ) {
        $this->messages = $messages;
        $this->requestedModel = $responseModel;
        $this->model = $model;
        $this->maxRetries = $maxRetries;
        $this->options = $options;
        $this->functionName = $functionName ?: $this->defaultFunctionName;
        $this->functionDescription = $functionDescription ?: $this->defaultFunctionDescription;
        $this->mode = $mode;
        $this->client = $client;
        $this->retryPrompt = $retryPrompt ?: $this->defaultRetryPrompt;
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
        return $this->responseModel->functionName();
    }

    public function jsonSchema() : array {
        return $this->responseModel->jsonSchema();
    }

    public function toolCallSchema() : array {
        return $this->responseModel->toolCallSchema();
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

    public function prompt() : string {
        return $this->defaultPrompts[$this->mode->value];
    }
}
