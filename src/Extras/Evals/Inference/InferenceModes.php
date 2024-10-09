<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Data\EvalSchema;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Features\LLM\InferenceResponse;

class InferenceModes
{
    private EvalSchema $schema;
    private int $maxTokens;

    public function __construct(
        EvalSchema $schema,
        int $maxTokens,
    ) {
        $this->schema = $schema;
        $this->maxTokens = $maxTokens;
    }

    public function schema() : array {
        return $this->schema->schema();
    }

    public function callInferenceFor(
        string|array $messages,
        Mode $mode,
        string $connection,
        array $schema,
        bool $isStreamed
    ) : LLMResponse {
        $messages = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $inferenceResponse = match($mode) {
            Mode::Tools => $this->forModeTools($messages, $connection, $schema, $isStreamed),
            Mode::JsonSchema => $this->forModeJsonSchema($messages, $connection, $schema, $isStreamed),
            Mode::Json => $this->forModeJson($messages, $connection, $schema, $isStreamed),
            Mode::MdJson => $this->forModeMdJson($messages, $connection, $schema, $isStreamed),
            Mode::Text => $this->forModeText($messages, $connection, $isStreamed),
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