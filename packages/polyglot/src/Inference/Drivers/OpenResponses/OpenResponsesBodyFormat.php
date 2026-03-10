<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestMessages;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;
use Cognesy\Utils\Arrays;

/**
 * Formats request body for OpenResponses API.
 *
 * Key differences from Chat Completions:
 * - Uses `input` instead of `messages` (can be string or array of items)
 * - System messages go to `instructions` field
 * - Uses `max_output_tokens` instead of `max_completion_tokens`
 * - Response format uses `text.format` wrapper
 * - Tools are internally-tagged (type inside, not as wrapper)
 */
class OpenResponsesBodyFormat implements CanMapRequestBody
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
        $maxOutputTokens = $this->resolveMaxOutputTokens($options);
        unset($options['max_output_tokens'], $options['max_completion_tokens'], $options['max_tokens']);

        $messages = RequestMessages::forMapping($request, $this->supportsAlternatingRoles($request));

        // Extract system instructions and non-system messages
        $systemInstructions = $this->extractSystemInstructions($messages);
        $inputMessages = $this->filterNonSystemMessages($messages);

        $requestBody = array_filter([
            'model' => $request->model() ?: $this->config->model,
            'instructions' => $systemInstructions ?: null,
            'input' => $this->messageFormat->map($inputMessages),
            'max_output_tokens' => $maxOutputTokens,
            'stream' => ($options['stream'] ?? false) ?: null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        // Add temperature and top_p if specified
        if (isset($options['temperature'])) {
            $requestBody['temperature'] = $options['temperature'];
        }
        if (isset($options['top_p'])) {
            $requestBody['top_p'] = $options['top_p'];
        }

        // Handle response format
        $textFormat = $this->toTextFormat($request);
        if (! empty($textFormat)) {
            $requestBody['text'] = $textFormat;
        }

        // Handle tools (function calling)
        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $toolChoice = $this->toToolChoice($request);
            if ($toolChoice !== null) {
                $requestBody['tool_choice'] = $toolChoice;
            }
        }

        // Add truncation if specified
        if (! empty($options['truncation'])) {
            $requestBody['truncation'] = $options['truncation'];
        }

        // Add metadata if specified
        if (! empty($options['metadata'])) {
            $requestBody['metadata'] = $options['metadata'];
        }

        // Add previous_response_id for conversation chaining
        if (! empty($options['previous_response_id'])) {
            $requestBody['previous_response_id'] = $options['previous_response_id'];
        }

        return $this->addRemainingOptions($requestBody, $options);
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsToolSelection(InferenceRequest $request): bool
    {
        return true;
    }

    protected function supportsAlternatingRoles(InferenceRequest $request): bool
    {
        return true;
    }

    // INTERNAL ///////////////////////////////////////////////

    /**
     * Extract system instructions from messages.
     */
    protected function extractSystemInstructions(Messages $messages): string
    {
        return RequestMessages::textForRoles($messages, ['system', 'developer']);
    }

    /**
     * Filter out system/developer messages from the messages array.
     */
    protected function filterNonSystemMessages(Messages $messages): Messages
    {
        return RequestMessages::exceptRoles($messages, ['system', 'developer']);
    }

    /**
     * Convert response format to OpenResponses text.format structure.
     */
    protected function toTextFormat(InferenceRequest $request): array
    {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        $responseFormat = $request->responseFormat();

        return match ($type) {
            'text' => ['format' => ['type' => 'text']],
            'json',
            'json_object' => ['format' => ['type' => 'json_object']],
            'json_schema' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $responseFormat->schemaName(),
                    'schema' => $this->normalizeSchemaForResponses(
                        $this->removeDisallowedEntries($responseFormat->schema()),
                    ),
                    'strict' => $responseFormat->strict(),
                ],
            ],
            default => [],
        };
    }

    /**
     * Convert tools to OpenResponses format.
     * Tools in OpenResponses use the same externally-tagged format as Chat Completions.
     */
    protected function toTools(InferenceRequest $request): array
    {
        return $this->removeDisallowedEntries($request->tools()->toArray());
    }

    /**
     * Convert tool choice to OpenResponses format.
     */
    protected function toToolChoice(InferenceRequest $request): array|string|null
    {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();

        $result = match (true) {
            $tools->isEmpty() => null,
            $toolChoice->isEmpty() => 'auto',
            $toolChoice->isSpecific() => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice->functionName(),
                ],
            ],
            default => $toolChoice->mode(),
        };

        if (! $this->supportsToolSelection($request) && is_array($result)) {
            $result = 'auto';
        }

        return $result;
    }

    protected function removeDisallowedEntries(array $jsonSchema): array
    {
        return RequestPayload::removeSchemaKeys($jsonSchema, [
            'x-title',
            'x-php-class',
        ]);
    }

    protected function normalizeSchemaForResponses(array $schema): array
    {
        $properties = $schema['properties'] ?? null;
        if (is_array($properties)) {
            $normalizedProperties = [];
            foreach ($properties as $name => $propertySchema) {
                $normalizedProperties[$name] = is_array($propertySchema)
                    ? $this->normalizeSchemaForResponses($propertySchema)
                    : $propertySchema;
            }
            $schema['properties'] = $normalizedProperties;
            $schema['required'] = array_keys($normalizedProperties);
        }

        $items = $schema['items'] ?? null;
        if (is_array($items)) {
            $schema['items'] = $this->normalizeSchemaForResponses($items);
        }

        $additionalProperties = $schema['additionalProperties'] ?? null;
        if (is_array($additionalProperties)) {
            $schema['additionalProperties'] = $this->normalizeSchemaForResponses($additionalProperties);
        }

        foreach (['$defs', 'definitions'] as $key) {
            $definitions = $schema[$key] ?? null;
            if (! is_array($definitions)) {
                continue;
            }

            $normalizedDefinitions = [];
            foreach ($definitions as $definitionName => $definitionSchema) {
                $normalizedDefinitions[$definitionName] = is_array($definitionSchema)
                    ? $this->normalizeSchemaForResponses($definitionSchema)
                    : $definitionSchema;
            }
            $schema[$key] = $normalizedDefinitions;
        }

        foreach (['anyOf', 'oneOf', 'allOf', 'prefixItems'] as $key) {
            $variants = $schema[$key] ?? null;
            if (! is_array($variants)) {
                continue;
            }

            $normalizedVariants = [];
            foreach ($variants as $variant) {
                $normalizedVariants[] = is_array($variant)
                    ? $this->normalizeSchemaForResponses($variant)
                    : $variant;
            }
            $schema[$key] = $normalizedVariants;
        }

        return $schema;
    }

    protected function resolveMaxOutputTokens(array $options): ?int
    {
        $resolved = $options['max_output_tokens']
            ?? $options['max_completion_tokens']
            ?? $options['max_tokens']
            ?? $this->config->maxTokens
            ?? null;

        if (! is_numeric($resolved)) {
            return null;
        }

        $value = (int) $resolved;

        return $value > 0 ? $value : null;
    }

    protected function addRemainingOptions(array $requestBody, array $options): array
    {
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $requestBody)) {
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $requestBody[$key] = $value;
        }

        return $requestBody;
    }

    protected function toResponseFormatType(InferenceRequest $request): ?string
    {
        return RequestPayload::responseFormatType($request);
    }
}
