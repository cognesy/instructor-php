<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Enums\Mode;

class RequestInfo
{
    public string|array $messages;
    public string|array|object $input;
    public string|array|object $responseModel;
    public string $model;
    public string $prompt;
    public int $maxRetries;
    public array $options;
    /** @var Example[] */
    public array $examples;
    public string $retryPrompt;
    public string $toolName;
    public string $toolDescription;
    public Mode $mode;

    public function isStream() : bool {
        return $this->options['stream'] ?? false;
    }

    public function withMessages(string|array $messages) : static {
        $this->messages = $messages;
        return $this;
    }

    public function withInput(string|array|object $input) : static {
        $this->input = $input;
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel) : static {
        $this->responseModel = $responseModel;
        return $this;
    }

    public function withModel(string $model) : static {
        $this->model = $model;
        return $this;
    }

    public function withPrompt(string $prompt) : static {
        $this->prompt = $prompt;
        return $this;
    }

    public function withExamples(array $examples) : static {
        $this->examples = $examples;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withOptions(array $options) : static {
        $this->options = $options;
        return $this;
    }

    public function withRetryPrompt($retryPrompt) : static {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withToolName($toolName) : static {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription($toolDescription) : static {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withMode(Mode $mode) : static {
        $this->mode = $mode;
        return $this;
    }

    public static function new() : static {
        return new static();
    }

    public static function with(
        $messages = '',
        $input = '',
        $responseModel = '',
        $prompt = '',
        $examples = [],
        $model = '',
        $maxRetries = 0,
        $options = [],
        $toolName = '',
        $toolDescription = '',
        $retryPrompt = '',
        $mode = Mode::Tools,
    ) : static {
        $data = new static();
        $data->messages = $messages;
        $data->input = $input;
        $data->responseModel = $responseModel;
        $data->prompt = $prompt;
        $data->examples = $examples;
        $data->model = $model;
        $data->maxRetries = $maxRetries;
        $data->options = $options;
        $data->toolName = $toolName;
        $data->toolDescription = $toolDescription;
        $data->retryPrompt = $retryPrompt;
        $data->mode = $mode;
        return $data;
    }
}
