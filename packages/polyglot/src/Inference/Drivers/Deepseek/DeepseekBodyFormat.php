<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestMessages;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestPayload;
use InvalidArgumentException;

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

        $requestBody['response_format'] = $this->toResponseFormat($request);
        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request);
        }

        return RequestPayload::filterEmptyValues($requestBody);
    }

    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsAlternatingRoles(InferenceRequest $request): bool
    {
        return true;
    }

    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat): array
    {
        throw new InvalidArgumentException(
            'DeepSeek cannot render JSON Schema; request JSON Object explicitly '
            . 'or enable an evidenced lossy fallback before rendering.',
        );
    }
}
