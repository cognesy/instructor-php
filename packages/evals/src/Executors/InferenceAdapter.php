<?php

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Executors\Data\InferenceSchema;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\InferenceResponse;

class InferenceAdapter
{
    public function callInferenceFor(
        string          $connection,
        Mode            $mode,
        bool            $isStreamed,
        string|array    $messages,
        InferenceSchema $evalSchema,
        int             $maxTokens,
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

    public function forModeTools(string $connection, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
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

    public function forModeJsonSchema(string $connection, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ]),
                responseFormat: $schema->responseFormatJsonSchema(),
                options: $options,
                mode: Mode::JsonSchema,
            );
    }

    public function forModeJson(string $connection, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ]),
                responseFormat: $schema->responseFormatJson(),
                options: $options,
                mode: Mode::Json,
            );
    }

    public function forModeMdJson(string $connection, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
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