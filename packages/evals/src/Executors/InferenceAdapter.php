<?php

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Executors\Data\InferenceSchema;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\InferenceResponse;

class InferenceAdapter
{
    public function callInferenceFor(
        string          $preset,
        OutputMode      $mode,
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
            OutputMode::Tools => $this->forModeTools($preset, $messages, $evalSchema, $options),
            OutputMode::JsonSchema => $this->forModeJsonSchema($preset, $messages, $evalSchema, $options),
            OutputMode::Json => $this->forModeJson($preset, $messages, $evalSchema, $options),
            OutputMode::MdJson => $this->forModeMdJson($preset, $messages, $evalSchema, $options),
            OutputMode::Text => $this->forModeText($preset, $messages, $options),
            OutputMode::Unrestricted => $this->forModeUnrestricted($preset, $messages, $evalSchema, $options),
        };
        return $inferenceResponse->response();
    }

    public function forModeTools(string $preset, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->using($preset)
            ->create(
                messages: $messages,
                tools: $schema->tools(),
                toolChoice: $schema->toolChoice(),
                options: $options,
                mode: OutputMode::Tools,
            );
    }

    public function forModeJsonSchema(string $preset, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->using($preset)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ]),
                responseFormat: $schema->responseFormatJsonSchema(),
                options: $options,
                mode: OutputMode::JsonSchema,
            );
    }

    public function forModeJson(string $preset, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->using($preset)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ]),
                responseFormat: $schema->responseFormatJson(),
                options: $options,
                mode: OutputMode::Json,
            );
    }

    public function forModeMdJson(string $preset, string|array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->using($preset)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                    ['role' => 'user', 'content' => '```json'],
                ]),
                options: $options,
                mode: OutputMode::MdJson,
            );
    }

    public function forModeText(string $preset, string|array $messages, array $options) : InferenceResponse {
        return (new Inference)
            ->using($preset)
            ->create(
                messages: $messages,
                options: $options,
                mode: OutputMode::Text,
            );
    }

    public function forModeUnrestricted(string $preset, array $messages, InferenceSchema $schema, array $options) : InferenceResponse {
        return (new Inference)
            ->using($preset)
            ->create(
                messages: array_merge($messages, [
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                    ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                    ['role' => 'user', 'content' => '```json'],
                ]),
                tools: $schema->tools(),
                toolChoice: $schema->toolChoice(),
                responseFormat: $schema->responseFormatJson(),
                options: $options,
                mode: OutputMode::Unrestricted,
            );
    }
}