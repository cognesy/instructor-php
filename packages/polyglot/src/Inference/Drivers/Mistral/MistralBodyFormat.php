<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Mistral;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;

class MistralBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat
    ) {}

    #[\Override]
    public function toRequestBody(InferenceRequest $request): array
    {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        unset($options['parallel_tool_calls']);

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($request->messages()),
        ]), $options);

        $requestBody['response_format'] = match (true) {
            $request->hasTools() && ! $this->supportsNonTextResponseForTools($request) => [],
            $this->supportsStructuredOutput($request) => $this->toResponseFormat($request),
            default => [],
        };

        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request);
        }

        return RequestPayload::filterEmptyValues($requestBody);
    }

    // CAPABILITIES /////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request): bool
    {
        return false;
    }

    protected function supportsStructuredOutput(InferenceRequest $request): bool
    {
        return true;
    }

    // INTERNAL /////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request): array
    {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        // Mistral API supports: json_object, json_schema, text
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn () => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn () => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $request->responseFormat()->schemaFilteredWith($this->removeDisallowedEntries(...)),
                    'strict' => $request->responseFormat()->strict(),
                ],
            ]);

        $result = match ($type) {
            'json',
            'json_object' => $responseFormat->asJsonObject(),
            'json_schema' => $responseFormat->asJsonSchema(),
            'text' => $responseFormat->asText(),
            default => [],
        };

        return RequestPayload::filterEmptyValues($result);
    }

    private function toTools(InferenceRequest $request): array
    {
        return $this->removeDisallowedEntries($request->tools()->toArray());
    }

    private function toToolChoice(InferenceRequest $request): array|string
    {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();

        return match (true) {
            $tools->isEmpty() => '',
            $toolChoice->isEmpty() => 'auto',
            $toolChoice->isSpecific() => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice->functionName(),
                ],
            ],
            default => $toolChoice->mode(),
        };
    }

    private function removeDisallowedEntries(array $jsonSchema): array
    {
        return RequestPayload::removeSchemaKeys($jsonSchema, [
            'x-title',
            'x-php-class',
        ]);
    }

    protected function toResponseFormatType(InferenceRequest $request): ?string
    {
        return RequestPayload::responseFormatType($request);
    }
}
