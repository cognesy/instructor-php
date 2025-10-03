<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Arrays;

class GeminiBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    #[\Override]
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

        return empty($system) ? [] : ['parts' => [[ 'text' => $system ]]];
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
            // candidateCount is a top-level param in some API versions; omit here for compatibility
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

        if ($request->hasResponseFormat()) {
            return ["function_calling_config" => ["mode" => "ANY"]];
        }
        if (empty($toolChoice)) {
            return ["function_calling_config" => ["mode" => "ANY"]];
        }
        if (is_string($toolChoice)) {
            return ["function_calling_config" => ["mode" => $this->mapToolChoice($toolChoice)]];
        }
        return [
            "function_calling_config" => array_filter([
                "mode" => $this->mapToolChoice($toolChoice['mode'] ?? "ANY"),
                "allowed_function_names" => isset($toolChoice['function']['name'])
                    ? [ $toolChoice['function']['name'] ]
                    : [],
            ]),
        ];
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
            OutputMode::MdJson => "text/plain", // we prompt-format to JSON within text
            OutputMode::Tools => "text/plain",
            OutputMode::Json => "application/json",
            OutputMode::JsonSchema => "application/json",
            default => "text/plain", // Unrestricted and any other defaults to plain text
        };
    }

    protected function toResponseSchema(ResponseFormat $responseFormat, ?OutputMode $mode) : array {
        $schema = $responseFormat->schemaFilteredWith($this->removeDisallowedEntries(...));
        $responseSchema = match($mode) {
            OutputMode::Json,
            OutputMode::JsonSchema => $schema,
            OutputMode::Unrestricted,
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
