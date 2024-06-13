<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\ResponseModel;

class RequestBuilder
{
    public array $messages = [];
    public string|array|object $input;
    public ResponseModel $responseModel;
    public string $model;
    public string $prompt;
    public int $maxRetries;
    public array $options;
    /** @var Example[] */
    public array $examples;
    public $retryPrompt;
    public $toolName;
    public $toolDescription;

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

    public static function new() : RequestBuilder {
        return new RequestBuilder();
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
    ) : RequestBuilder {
        $builder = new RequestBuilder();
        $builder->messages = $messages;
        $builder->input = $input;
        $builder->responseModel = $responseModel;
        $builder->model = $model;
        $builder->maxRetries = $maxRetries;
        $builder->options = $options;
        $builder->examples = $examples;
        $builder->toolName = $toolName;
        $builder->toolDescription = $toolDescription;
        $builder->prompt = $prompt;
        $builder->retryPrompt = $retryPrompt;
        return $builder;
    }
}
