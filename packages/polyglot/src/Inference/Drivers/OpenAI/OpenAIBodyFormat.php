<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Utils\Arrays;

class OpenAIBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    #[\Override]
    public function toRequestBody(InferenceRequest $request): array
    {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        $messages = match ($this->supportsAlternatingRoles($request)) {
            false => $request->messages()->toMergedPerRole(),
            true => $request->messages(),
        };

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        // max_tokens is deprecated in OpenAI API, use max_completion_tokens instead.
        // Preserve an explicitly provided max_completion_tokens (from options) if present.
        if (array_key_exists('max_tokens', $requestBody) && ! array_key_exists('max_completion_tokens', $requestBody)) {
            $requestBody['max_completion_tokens'] = $requestBody['max_tokens'];
        }
        unset($requestBody['max_tokens']);
        if ($options['stream'] ?? false) {
            $requestBody['stream_options']['include_usage'] = true;
        }

        $requestBody['response_format'] = match (true) {
            $request->hasTools() && ! $this->supportsNonTextResponseForTools($request) => [],
            $this->supportsStructuredOutput($request) => $this->toResponseFormat($request),
            default => [],
        };

        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request);
        }

        return $this->filterEmptyValues($requestBody);
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsToolSelection(InferenceRequest $request): bool
    {
        return true;
    }

    protected function supportsStructuredOutput(InferenceRequest $request): bool
    {
        return true;
    }

    protected function supportsAlternatingRoles(InferenceRequest $request): bool
    {
        return true;
    }

    protected function supportsNonTextResponseForTools(InferenceRequest $request): bool
    {
        return true;
    }

    // INTERNAL ///////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request): array
    {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        // OpenAI API supports: json_object, json_schema, text
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn () => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn () => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
                    'strict' => $request->responseFormat()->strict(),
                ],
            ]);

        $result = $this->renderResponseFormatForType($responseFormat, $type);

        return $this->filterEmptyValues($result);
    }

    protected function toTools(InferenceRequest $request): array
    {
        return $this->removeDisallowedEntries(
            $request->tools()->toArray()
        );
    }

    protected function toToolChoice(InferenceRequest $request): array|string
    {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();

        $result = match (true) {
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

        if (! $this->supportsToolSelection($request)) {
            $result = is_array($result) ? 'auto' : $result;
        }

        return $result;
    }

    protected function removeDisallowedEntries(array $jsonSchema): array
    {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
            ],
        );
    }

    protected function filterEmptyValues(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    protected function renderResponseFormatForType(ResponseFormat $responseFormat, ?string $type): array
    {
        return match ($type) {
            'json',
            'json_object' => $responseFormat->asJsonObject(),
            'json_schema' => $responseFormat->asJsonSchema(),
            'text' => $responseFormat->asText(),
            default => [],
        };
    }

    protected function toResponseFormatType(InferenceRequest $request): ?string
    {
        if (! $request->hasResponseFormat()) {
            return null;
        }

        return match ($request->responseFormat()->type()) {
            'text' => 'text',
            'json',
            'json_object' => 'json_object',
            'json_schema' => 'json_schema',
            default => null,
        };
    }
}
