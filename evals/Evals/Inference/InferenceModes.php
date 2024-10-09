<?php

namespace Cognesy\Evals\Evals\Inference;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Features\LLM\InferenceResponse;

class InferenceModes
{
    private TaskSchema $schema;
    private int $maxTokens = 512;

    public function __construct(
        array $schema = [],
    ) {
        $this->schema = new TaskSchema(schema: $schema);
    }

    public function schema() : array {
        return $this->schema->schema();
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
        return $inferenceResponse->response();
    }

    public function forModeTools(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: $query,
                tools: $this->schema->tools(),
                toolChoice: $this->schema->toolChoice(),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Tools,
            );
    }

    public function forModeJsonSchema(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($query, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ]),
                responseFormat: $this->schema->responseFormatJsonSchema(),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::JsonSchema,
            );
    }

    public function forModeJson(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($query, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ]),
                responseFormat: $this->schema->responseFormatJson(),
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Json,
            );
    }

    public function forModeMdJson(string|array $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
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
            ->create(
                messages: $query,
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Text,
            );
    }
}