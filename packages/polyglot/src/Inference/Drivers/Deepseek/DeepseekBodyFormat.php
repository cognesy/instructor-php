<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestMessages;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;

class DeepseekBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request): array
    {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        $model = $request->model() ?: $this->config->model;
        $messages = RequestMessages::forMapping($request, $this->supportsAlternatingRoles($request));

        $requestBody = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ], static fn (mixed $value): bool => (bool) $value), $options);

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

        return RequestPayload::filterEmptyValues($requestBody);
    }

    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsToolSelection(InferenceRequest $request): bool
    {
        // DeepSeek V4 Flash, Pro, and Flash Vision all support tool calls and
        // explicit tool choice, including when thinking mode is enabled.
        return true;
    }

    #[\Override]
    protected function supportsStructuredOutput(InferenceRequest $request): bool
    {
        // V4 supports JSON Output. JSON Schema requests are rendered as the
        // provider's supported json_object form below.
        return true;
    }

    #[\Override]
    protected function supportsAlternatingRoles(InferenceRequest $request): bool
    {
        return true;
    }

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request): bool
    {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    // DeepSeek V4 supports json_object and text but not native JSON Schema, so
    // schema mode degrades to plain JSON.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat): array
    {
        return $this->toJsonObjectResponseFormat($responseFormat);
    }
}
