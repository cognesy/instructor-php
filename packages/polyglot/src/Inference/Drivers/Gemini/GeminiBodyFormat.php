<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestMessages;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;
use Cognesy\Utils\Arrays;

class GeminiBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    #[\Override]
    public function toRequestBody(InferenceRequest $request): array
    {
        $request = $request->withCacheApplied();

        $requestBody = RequestPayload::filterEmptyValues([
            'systemInstruction' => $this->toSystem($request),
            'contents' => $this->toMessages($request),
            'generationConfig' => $this->toOptions($request),
        ]);

        if (! $this->supportsNonTextResponseForTools($request)) {
            if ($request->hasTools()) {
                unset($requestBody['generationConfig']['responseSchema']);
                unset($requestBody['generationConfig']['responseMimeType']);
            }
        }

        if ($request->hasTools() && ! $request->tools()->isEmpty()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_config'] = $this->toToolChoice($request);
        }

        return $requestBody;
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request): bool
    {
        return false;
    }

    // INTERNAL //////////////////////////////////////////////

    protected function toSystem(InferenceRequest $request): array
    {
        $system = RequestMessages::textForRoles($request->messages(), ['system']);

        return empty($system) ? [] : ['parts' => [['text' => $system]]];
    }

    protected function toMessages(InferenceRequest $request): array
    {
        $messages = RequestMessages::exceptRoles($request->messages(), ['system']);

        return $this->messageFormat->map($messages);
    }

    protected function toOptions(
        InferenceRequest $request,
    ): array {
        $options = array_merge($this->config->options, $request->options());
        $responseFormat = $request->responseFormat();
        $type = $this->toResponseFormatType($request);

        return RequestPayload::filterEmptyValues([
            'responseMimeType' => $this->toResponseMimeType($type),
            'responseSchema' => $this->toResponseSchema($responseFormat, $type),
            // candidateCount is a top-level param in some API versions; omit here for compatibility
            'maxOutputTokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'temperature' => $options['temperature'] ?? 1.0,
        ]);
    }

    protected function toTools(InferenceRequest $request): array
    {
        $tools = $request->tools();

        return ['function_declarations' => array_map(
            callback: fn ($tool) => $this->removeDisallowedEntries([
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ]),
            array: $tools->all()
        )];
    }

    protected function toToolChoice(InferenceRequest $request): string|array
    {
        $toolChoice = $request->toolChoice();

        if ($request->hasResponseFormat()) {
            return ['function_calling_config' => ['mode' => 'ANY']];
        }
        if ($toolChoice->isEmpty()) {
            return ['function_calling_config' => ['mode' => 'ANY']];
        }
        if (! $toolChoice->isSpecific()) {
            return ['function_calling_config' => ['mode' => $this->mapToolChoice($toolChoice->mode())]];
        }

        return [
            'function_calling_config' => array_filter([
                'mode' => 'ANY',
                'allowed_function_names' => [$toolChoice->functionName()],
            ]),
        ];
    }

    protected function mapToolChoice(string $choice): string
    {
        return match ($choice) {
            'auto' => 'AUTO',
            'required' => 'ANY',
            'none' => 'NONE',
            default => 'ANY',
        };
    }

    protected function toResponseMimeType(?string $type): string
    {
        return match ($type) {
            'json',
            'json_object',
            'json_schema' => 'application/json',
            default => 'text/plain',
        };
    }

    protected function toResponseSchema(ResponseFormat $responseFormat, ?string $type): array
    {
        $schema = $responseFormat->schemaFilteredWith($this->removeDisallowedEntries(...));

        return match ($type) {
            'json',
            'json_object',
            'json_schema' => $schema,
            default => [],
        };
    }

    protected function removeDisallowedEntries(array $jsonSchema): array
    {
        return RequestPayload::removeSchemaKeys($jsonSchema, [
            'x-title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    protected function toResponseFormatType(InferenceRequest $request): ?string
    {
        return RequestPayload::responseFormatType($request);
    }
}
