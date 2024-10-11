<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

class ExperimentData
{
    public string|array $messages = '';
    public string|array|object $schema = [];
    public string|array|object $responseModel = [];
    public int $maxTokens = 512;
    public string $toolName = '';
    public string $toolDescription = '';

    public string $system = '';
    public string $prompt = '';
    public string|array|object $input = '';
    public array $examples = [];
    public string $model = '';
    public string $retryPrompt = '';
    public int $maxRetries = 0;

    public function withInferenceConfig(
        string|array $messages = '',
        string|array|object $schema = [],
        int $maxTokens = 512,
    ) : self {
        $this->messages = $messages;
        $this->schema = $schema;
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function withInstructorConfig(
        string|array $messages = '',
        string|array|object $responseModel = '',
        int $maxTokens = 512,
        string $toolName = '',
        string $toolDescription = '',
        string $system = '',
        string $prompt = '',
        string|array|object $input = '',
        array $examples = [],
        string $model = '',
        string $retryPrompt = '',
        int $maxRetries = 0,
    ) : self {
        $this->messages = $messages;
        $this->responseModel = $responseModel;
        $this->maxTokens = $maxTokens;
        $this->toolName = $toolName;
        $this->toolDescription = $toolDescription;
        $this->system = $system;
        $this->prompt = $prompt;
        $this->input = $input;
        $this->examples = $examples;
        $this->model = $model;
        $this->retryPrompt = $retryPrompt;
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function responseModel() : string|array|object {
        return $this->responseModel;
    }

    public function inferenceSchema() : InferenceSchema {
        // TODO: make it accept any and use SchemaFactory to generate EvalSchema object
        if (!$this->schema instanceof InferenceSchema) {
            throw new \Exception('Schema is not an instance of EvalSchema.');
        }
        return $this->schema;
    }
}