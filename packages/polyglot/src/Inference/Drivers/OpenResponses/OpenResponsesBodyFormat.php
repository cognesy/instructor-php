<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
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
    public function toRequestBody(InferenceRequest $request): array {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());
        $maxOutputTokens = $this->resolveMaxOutputTokens($options);
        unset($options['max_output_tokens'], $options['max_completion_tokens'], $options['max_tokens']);

        $messages = match($this->supportsAlternatingRoles($request)) {
            false => Messages::fromArray($request->messages())->toMergedPerRole()->toArray(),
            true => $request->messages(),
        };

        // Extract system instructions and non-system messages
        $systemInstructions = $this->extractSystemInstructions($messages);
        $inputMessages = $this->filterNonSystemMessages($messages);

        $requestBody = array_filter([
            'model' => $request->model() ?: $this->config->model,
            'instructions' => $systemInstructions ?: null,
            'input' => $this->messageFormat->map($inputMessages),
            'max_output_tokens' => $maxOutputTokens,
            'stream' => ($options['stream'] ?? false) ?: null,
        ], fn($v) => $v !== null && $v !== '' && $v !== []);

        // Add temperature and top_p if specified
        if (isset($options['temperature'])) {
            $requestBody['temperature'] = $options['temperature'];
        }
        if (isset($options['top_p'])) {
            $requestBody['top_p'] = $options['top_p'];
        }

        // Handle response format
        $textFormat = $this->toTextFormat($request);
        if (!empty($textFormat)) {
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
        if (!empty($options['truncation'])) {
            $requestBody['truncation'] = $options['truncation'];
        }

        // Add metadata if specified
        if (!empty($options['metadata'])) {
            $requestBody['metadata'] = $options['metadata'];
        }

        // Add previous_response_id for conversation chaining
        if (!empty($options['previous_response_id'])) {
            $requestBody['previous_response_id'] = $options['previous_response_id'];
        }

        return $this->addRemainingOptions($requestBody, $options);
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsToolSelection(InferenceRequest $request): bool {
        return true;
    }

    protected function supportsStructuredOutput(InferenceRequest $request): bool {
        return true;
    }

    protected function supportsAlternatingRoles(InferenceRequest $request): bool {
        return true;
    }

    // INTERNAL ///////////////////////////////////////////////

    /**
     * Extract system instructions from messages.
     */
    protected function extractSystemInstructions(array $messages): string {
        $systemMessages = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            if ($role === 'system' || $role === 'developer') {
                $content = $message['content'] ?? '';
                if (is_array($content)) {
                    // Handle content array format
                    foreach ($content as $part) {
                        if (isset($part['text'])) {
                            $systemMessages[] = $part['text'];
                        } elseif (is_string($part)) {
                            $systemMessages[] = $part;
                        }
                    }
                } else {
                    $systemMessages[] = $content;
                }
            }
        }
        return implode("\n\n", $systemMessages);
    }

    /**
     * Filter out system/developer messages from the messages array.
     */
    protected function filterNonSystemMessages(array $messages): array {
        return array_values(array_filter($messages, function ($message) {
            $role = $message['role'] ?? '';
            return !in_array($role, ['system', 'developer'], true);
        }));
    }

    /**
     * Convert response format to OpenResponses text.format structure.
     */
    protected function toTextFormat(InferenceRequest $request): array {
        $mode = $this->toResponseFormatMode($request);
        if ($mode === null) {
            return [];
        }

        $responseFormat = $request->responseFormat();
        if (!($responseFormat instanceof ResponseFormat)) {
            return [];
        }

        return match($mode) {
            OutputMode::Text => ['format' => ['type' => 'text']],
            OutputMode::Json => ['format' => ['type' => 'json_object']],
            OutputMode::JsonSchema => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $responseFormat->schemaName(),
                    'schema' => $this->removeDisallowedEntries($responseFormat->schema()),
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
    protected function toTools(InferenceRequest $request): array {
        return $this->removeDisallowedEntries($request->tools());
    }

    /**
     * Convert tool choice to OpenResponses format.
     */
    protected function toToolChoice(InferenceRequest $request): array|string|null {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();
        $toolName = $toolChoice['function']['name'] ?? null;

        $result = match(true) {
            empty($tools) => null,
            empty($toolChoice) => 'auto',
            !empty($toolName) => [
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                ]
            ],
            default => null,
        };

        if (!$this->supportsToolSelection($request) && is_array($result)) {
            $result = 'auto';
        }

        return $result;
    }

    protected function removeDisallowedEntries(array $jsonSchema): array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
            ],
        );
    }

    protected function resolveMaxOutputTokens(array $options): ?int {
        $resolved = $options['max_output_tokens']
            ?? $options['max_completion_tokens']
            ?? $options['max_tokens']
            ?? $this->config->maxTokens
            ?? null;

        if (!is_numeric($resolved)) {
            return null;
        }

        $value = (int) $resolved;
        return $value > 0 ? $value : null;
    }

    protected function addRemainingOptions(array $requestBody, array $options): array {
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

    protected function toResponseFormatMode(InferenceRequest $request): ?OutputMode {
        if (!$request->outputMode()?->is(OutputMode::Unrestricted)) {
            return $request->outputMode();
        }
        if ($request->hasTextResponseFormat()) {
            return OutputMode::Text;
        }
        if (!$request->hasResponseFormat()) {
            return null;
        }

        $responseFormat = $request->responseFormat();
        if (!($responseFormat instanceof ResponseFormat)) {
            return null;
        }

        $type = $responseFormat->type();
        return match($type) {
            'json' => OutputMode::Json,
            'json_object' => OutputMode::Json,
            'json_schema' => OutputMode::JsonSchema,
            default => null,
        };
    }
}
