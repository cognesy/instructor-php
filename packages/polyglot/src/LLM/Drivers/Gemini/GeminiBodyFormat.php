<?php

namespace Cognesy\Polyglot\LLM\Drivers\Gemini;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Messages\Messages;

class GeminiBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        OutputMode $mode
    ): array {
        $request = array_filter([
            'systemInstruction' => $this->toSystem($messages),
            'contents' => $this->messageFormat->map(Messages::fromArray($messages)->exceptRoles(['system'])->toArray()),
            'generationConfig' => $this->toOptions($this->config, $options, $responseFormat, $mode),
        ]);

        if (!empty($tools)) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_config'] = $this->toToolChoice($toolChoice);
        }

        return $request;
    }

    // INTERNAL //////////////////////////////////////////////

    private function toSystem(array $messages) : array {
        $system = Messages::fromArray($messages)
            ->forRoles(['system'])
            ->toString();

        return empty($system) ? [] : ['parts' => ['text' => $system]];
    }

    protected function toOptions(
        LLMConfig  $config,
        array      $options,
        array      $responseFormat,
        OutputMode $mode,
    ) : array {
        return array_filter([
            "responseMimeType" => $this->toResponseMimeType($mode),
            "responseSchema" => $this->toResponseSchema($responseFormat, $mode),
            "candidateCount" => 1,
            "maxOutputTokens" => $options['max_tokens'] ?? $config->maxTokens,
            "temperature" => $options['temperature'] ?? 1.0,
        ]);
    }

    protected function toTools(array $tools) : array {
        return ['function_declarations' => array_map(
            callback: fn($tool) => $this->removeDisallowedEntries($tool['function']),
            array: $tools
        )];
    }

    protected function toToolChoice(string|array $toolChoice): string|array {
        return match(true) {
            empty($toolChoice) => ["function_calling_config" => ["mode" => "ANY"]],
            is_string($toolChoice) => ["function_calling_config" => ["mode" => $this->mapToolChoice($toolChoice)]],
            is_array($toolChoice) => [
                "function_calling_config" => array_filter([
                    "mode" => $this->mapToolChoice($toolChoice['mode'] ?? "ANY"),
                    "allowed_function_names" => $toolChoice['function']['name'] ?? [],
                ]),
            ],
            default => ["function_calling_config" => ["mode" => "ANY"]],
        };
    }

    protected function mapToolChoice(string $choice) : string {
        return match($choice) {
            'auto' => 'AUTO',
            'required' => 'ANY',
            'none' => 'NONE',
            default => 'ANY',
        };
    }

    protected function toResponseMimeType(OutputMode $mode): string {
        return match($mode) {
            OutputMode::Text => "text/plain",
            OutputMode::MdJson => "text/plain",
            OutputMode::Tools => "text/plain",
            OutputMode::Json => "application/json",
            OutputMode::JsonSchema => "application/json",
            default => "application/json",
        };
    }

    protected function toResponseSchema(array $responseFormat, OutputMode $mode) : array {
        return $this->removeDisallowedEntries($responseFormat['schema'] ?? []);
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'x-title',
            'x-php-class',
            'additionalProperties',
        ]);
    }
}