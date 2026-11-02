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
use Cognesy\Polyglot\Inference\Drivers\Support\StrictJsonSchema;
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
     * Responses function tools are flat (`{type, name, description, parameters}`), unlike the
     * Chat Completions shape that nests them under `function`; the nested shape is rejected.
     */
    protected function toTools(InferenceRequest $request): array
    {
        return array_map(
            fn (array $tool): array => $this->toResponsesTool($tool),
            $this->removeDisallowedEntries($request->tools()->toArray()),
        );
    }

    protected function toResponsesTool(array $tool): array
    {
        $function = $tool['function'] ?? null;

        return match (true) {
            ($tool['type'] ?? null) !== 'function', !is_array($function) => $tool,
            default => ['type' => 'function', ...$function],
        };
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
                'name' => $toolChoice->functionName(),
            ],
            default => $toolChoice->mode(),
        };

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
        return StrictJsonSchema::normalize($schema);
    }

    protected function resolveMaxOutputTokens(array $options): ?int
    {
        $resolved = $options['max_output_tokens']
            ?? $options['max_completion_tokens']
            ?? $options['max_tokens']
            ?? $this->config->maxTokens;

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
