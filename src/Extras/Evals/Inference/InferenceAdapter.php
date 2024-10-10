<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Data\EvalSchema;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Features\LLM\InferenceResponse;

class InferenceAdapter
{
    public function callInferenceFor(
        string|array $messages,
        Mode $mode,
        string $connection,
        EvalSchema $evalSchema,
        bool $isStreamed,
        int $maxTokens,
    ) : LLMResponse {
        $messages = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $options = [
            'max_tokens' => $maxTokens,
            'stream' => $isStreamed
        ];
        $inferenceResponse = match($mode) {
            Mode::Tools => $this->forModeTools($connection, $messages, $evalSchema, $options),
            Mode::JsonSchema => $this->forModeJsonSchema($connection, $messages, $evalSchema, $options),
            Mode::Json => $this->forModeJson($connection, $messages, $evalSchema, $options),
            Mode::MdJson => $this->forModeMdJson($connection, $messages, $evalSchema, $options),
            Mode::Text => $this->forModeText($connection, $messages, $options),
        };
        return $inferenceResponse->response();
    }

    public function forModeTools(string $connection, string|array $messages, EvalSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: $messages,
                tools: $schema->tools(),
                toolChoice: $schema->toolChoice(),
                options: $options,
                mode: Mode::Tools,
            );
    }

    public function forModeJsonSchema(string $connection, string|array $messages, EvalSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ]),
                responseFormat: $schema->responseFormatJsonSchema(),
                options: $options,
                mode: Mode::JsonSchema,
            );
    }

    public function forModeJson(string $connection, string|array $messages, EvalSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ]),
                responseFormat: $schema->responseFormatJson(),
                options: $options,
                mode: Mode::Json,
            );
    }

    public function forModeMdJson(string $connection, string|array $messages, EvalSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond with correct JSON'],
                    ['role' => 'user', 'content' => '```json'],
                ]),
                options: $options,
                mode: Mode::MdJson,
            );
    }

    public function forModeText(string $connection, string|array $messages, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: $messages,
                options: $options,
                mode: Mode::Text,
            );
    }
}