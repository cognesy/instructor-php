<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestMessages;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;
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

        $options = $this->filterOptions(array_merge($this->config->options, $request->options()));

        $messages = RequestMessages::forMapping($request, $this->supportsAlternatingRoles($request));

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ], static fn (mixed $value): bool => (bool) $value), $options);

        $requestBody = $this->normalizeTokenLimits($requestBody);
        $requestBody = $this->applyStreamOptions($requestBody, $options);

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

    // PROVIDER VARIATION HOOKS ///////////////////////////////

    /**
     * Filter/adjust merged request options before they land in the body.
     * Override to drop options the provider rejects.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    protected function filterOptions(array $options): array
    {
        return $options;
    }

    /**
     * max_tokens is deprecated in OpenAI API, use max_completion_tokens instead.
     * Preserve an explicitly provided max_completion_tokens (from options) if present.
     * Override for providers that still use max_tokens.
     *
     * @param array<string,mixed> $requestBody
     * @return array<string,mixed>
     */
    protected function normalizeTokenLimits(array $requestBody): array
    {
        if (array_key_exists('max_tokens', $requestBody) && ! array_key_exists('max_completion_tokens', $requestBody)) {
            $requestBody['max_completion_tokens'] = $requestBody['max_tokens'];
        }
        unset($requestBody['max_tokens']);

        return $requestBody;
    }

    /**
     * Streaming extras (usage reporting). Override for providers without
     * stream_options support.
     *
     * @param array<string,mixed> $requestBody
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    protected function applyStreamOptions(array $requestBody, array $options): array
    {
        if ($options['stream'] ?? false) {
            $requestBody['stream_options']['include_usage'] = true;
        }

        return $requestBody;
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
        $result = $this->renderResponseFormatForType($request->responseFormat(), $type);

        return RequestPayload::filterEmptyValues($result);
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
        return RequestPayload::removeSchemaKeys($jsonSchema, [
            'x-title',
            'x-php-class',
        ]);
    }

    /**
     * Dispatches the requested mode to the three provider-variation hooks below.
     *
     * These three used to be `Closure`s injected into the `ResponseFormat` value object, so
     * every request allocated two closures and two copies of the object to reach a payload the
     * body format already had everything to build. A provider varies its *rendering*, which is
     * dispatch — and this class is already the per-provider one, so the dispatch belongs here.
     */
    protected function renderResponseFormatForType(ResponseFormat $responseFormat, ?string $type): array
    {
        return match ($type) {
            // `json` is unreachable from either caller: both take $type from
            // toResponseFormatType(), which already folds `json` into `json_object`. Kept
            // because toResponseFormatType() is a protected extension point a subclass may
            // override — but do not expect a test to cover this arm, because nothing can
            // currently reach it.
            'json',
            'json_object' => $this->toJsonObjectResponseFormat($responseFormat),
            'json_schema' => $this->toJsonSchemaResponseFormat($responseFormat),
            'text' => $this->toTextResponseFormat($responseFormat),
            default => [],
        };
    }

    /**
     * Every provider in this family agrees on text mode, so no subclass overrides this. It is
     * a hook anyway because a provider that disagreed would otherwise have to reimplement the
     * dispatch above to say so.
     */
    protected function toTextResponseFormat(ResponseFormat $responseFormat): array
    {
        return ['type' => 'text'];
    }

    protected function toJsonObjectResponseFormat(ResponseFormat $responseFormat): array
    {
        return ['type' => 'json_object'];
    }

    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $responseFormat->schemaName(),
                'schema' => $this->removeDisallowedEntries($responseFormat->schema()),
                'strict' => $responseFormat->strict(),
            ],
        ];
    }

    protected function toResponseFormatType(InferenceRequest $request): ?string
    {
        return RequestPayload::responseFormatType($request);
    }
}
