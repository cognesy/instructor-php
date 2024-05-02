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
    private string $prompt = "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n";

    public function __construct(
        public string|array $messages,
        public string|object|array $responseModel,
        public string $model = '',
        public int $maxRetries = 0,
        public array $options = [],
        public string $functionName = '',
        public string $functionDescription = '',
        public string $retryPrompt = '',
        public Mode $mode = Mode::Tools,
        public ?CanCallApi $client = null,
    ) {
        $this->functionName = $this->functionName ?: $this->defaultFunctionName;
        $this->functionDescription = $this->functionDescription ?: $this->defaultFunctionDescription;
        $this->retryPrompt = $this->retryPrompt ?: $this->defaultRetryPrompt;
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

    public function client() : CanCallApi {
        return $this->client;
    }

    public function withClient(CanCallApi $client) : self {
        $this->client = $client;
        return $this;
    }

    public function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt . Json::encode($jsonSchema);
        return $messages;
    }

    public function copy(array $messages) : self {
        return (clone $this)->withMessages($messages);
    }
}
