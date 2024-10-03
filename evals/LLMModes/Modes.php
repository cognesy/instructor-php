<?php

namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\LLM\InferenceResponse;

class Modes
{
    private Model $model;
    private int $maxTokens = 512;
    private bool $debug;

    public function __construct(
        array $schema = [],
        bool $debug = false,
    ) {
        $this->model = new Model(schema: $schema);
        $this->debug = $debug;
    }

    public function schema() : array {
        return $this->model->schema();
    }

    public function callInferenceFor(string|array $query, Mode $mode, string $connection, array $schema, bool $isStreamed) : LLMResponse {
        $query = is_array($query) ? $query : [['role' => 'user', 'content' => $query]];
        $inferenceResponse = match($mode) {
            Mode::Tools => $this->forModeTools($query, $connection, $schema, $isStreamed),
            Mode::JsonSchema => $this->forModeJsonSchema($query, $connection, $schema, $isStreamed),
            Mode::Json => $this->forModeJson($query, $connection, $schema, $isStreamed),
            Mode::MdJson => $this->forModeMdJson($query, $connection, $schema, $isStreamed),
            Mode::Text => $this->forModeText($query, $connection, $isStreamed),
        };
        return $inferenceResponse->toLLMResponse();
    }

    public function forModeTools(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: $query,
                tools: $this->model->tools(),
                toolChoice: $this->model->toolChoice(),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Tools,
            );
    }

    public function forModeJsonSchema(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: array_merge($query, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ]),
                responseFormat: $this->model->responseFormatJsonSchema(),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::JsonSchema,
            );
    }

    public function forModeJson(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: array_merge($query, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ]),
                responseFormat: $this->model->responseFormatJson(),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Json,
            );
    }

    public function forModeMdJson(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: array_merge($query, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON'],
                    ['role' => 'user', 'content' => '```json'],
                ]),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::MdJson,
            );
    }

    public function forModeText(string|array $query, string $connection, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: $query,
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Text,
            );
    }
}