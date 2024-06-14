<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;

class RequestData
{
    public string|array $messages = [];
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

    public function withMessages(string|array $messages) : static {
        $this->messages = $messages;
        return $this;
    }

    public function withInput(string|array|object $input) : static {
        $this->input = $input;
        return $this;
    }

    public function withResponseModel(ResponseModel $responseModel) : static {
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
        $messages = null,
        $input = null,
        $responseModel = null,
        $model = null,
        $maxRetries = null,
        $options = null,
        $examples = null,
        $toolName = null,
        $toolDescription = null,
        $prompt = null,
        $retryPrompt = null,
        $mode = null,
    ) : static {
        $data = new static();
        $data->messages = $messages;
        $data->input = $input;
        $data->responseModel = $responseModel;
        $data->model = $model;
        $data->maxRetries = $maxRetries;
        $data->options = $options;
        $data->examples = $examples;
        $data->toolName = $toolName;
        $data->toolDescription = $toolDescription;
        $data->prompt = $prompt;
        $data->retryPrompt = $retryPrompt;
        $data->mode = $mode;
        return $data;
    }
}
