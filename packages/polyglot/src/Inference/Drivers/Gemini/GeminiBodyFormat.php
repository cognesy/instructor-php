<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Messages\Messages;

class GeminiBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function toRequestBody(InferenceRequest $request) : array {
        $request = $request->withCacheApplied();

        $requestBody = $this->filterEmptyValues([
            'systemInstruction' => $this->toSystem($request),
            'contents' => $this->toMessages($request),
            'generationConfig' => $this->toOptions($request),
        ]);

        if (!$this->supportsNonTextResponseForTools($request)) {
            if ($request->hasTools()) {
                unset($requestBody['generationConfig']['responseSchema']);
                unset($requestBody['generationConfig']['responseMimeType']);
            }
        }

        if ($request->hasTools() && !empty($request->tools())) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_config'] = $this->toToolChoice($request);
        }

        return $requestBody;
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL //////////////////////////////////////////////

    protected function toSystem(InferenceRequest $request) : array {
        $messages = $request->messages();
        $system = Messages::fromArray($messages)
            ->forRoles(['system'])
            ->toString();

        return empty($system) ? [] : ['parts' => ['text' => $system]];
    }

    protected function toMessages(InferenceRequest $request): array {
        $messages = Messages::fromArray($request->messages())
            ->exceptRoles(['system'])
            ->toArray();

        return $this->messageFormat->map($messages);
    }

    protected function toOptions(
        InferenceRequest $request,
    ) : array {
        $options = array_merge($this->config->options, $request->options());
        $responseFormat = $request->responseFormat();
        $mode = $request->outputMode() ?? OutputMode::Unrestricted;

        return $this->filterEmptyValues([
            "responseMimeType" => $this->toResponseMimeType($mode),
            "responseSchema" => $this->toResponseSchema($responseFormat, $mode),
            "candidateCount" => 1,
            "maxOutputTokens" => $options['max_tokens'] ?? $this->config->maxTokens,
            "temperature" => $options['temperature'] ?? 1.0,
        ]);
    }

    protected function toTools(InferenceRequest $request) : array {
        $tools = $request->tools();

        return ['function_declarations' => array_map(
            callback: fn($tool) => $this->removeDisallowedEntries($tool['function']),
            array: $tools
        )];
    }

    protected function toToolChoice(InferenceRequest $request): string|array {
        $toolChoice = $request->toolChoice();

        return match(true) {
            $request->hasResponseFormat() => ["function_calling_config" => ["mode" => "ANY"]],
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

    protected function toResponseMimeType(?OutputMode $mode): string {
        return match($mode) {
            OutputMode::Text => "text/plain",
            OutputMode::MdJson => "text/plain",
            OutputMode::Tools => "text/plain",
            OutputMode::Json => "application/json",
            OutputMode::JsonSchema => "application/json",
            default => "application/json",
        };
    }

    protected function toResponseSchema(array $responseFormat, ?OutputMode $mode) : array {
        $schema = $responseFormat['schema'] ?? $responseFormat['json_schema']['schema'] ?? [];

        $responseSchema = match($mode) {
            OutputMode::Json,
            OutputMode::JsonSchema,
            OutputMode::Unrestricted => $this->removeDisallowedEntries($schema),
            OutputMode::Text,
            OutputMode::MdJson,
            OutputMode::Tools => [],
            default  => [],
        };

        return $responseSchema;
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
                'additionalProperties',
            ],
        );
    }

    protected function filterEmptyValues(array $data) : array {
        return array_filter($data, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}