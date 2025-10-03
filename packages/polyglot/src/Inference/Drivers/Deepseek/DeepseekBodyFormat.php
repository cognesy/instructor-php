<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Str;

class DeepseekBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        $model = $request->model() ?: $this->config->model;
        $messages = match($this->supportsAlternatingRoles($request)) {
            false => Messages::fromArray($request->messages())->toMergedPerRole()->toArray(),
            true => $request->messages(),
        };

        $requestBody = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $requestBody['stream_options']['include_usage'] = true;
        }

        $requestBody['response_format'] = match(true) {
            $request->hasTools() && !$this->supportsNonTextResponseForTools($request) => [],
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

    #[\Override]
    protected function supportsToolSelection(InferenceRequest $request) : bool {
        $model = $request->model() ?: $this->config->model;
        return !Str::contains($model, 'reasoner');
    }

    #[\Override]
    protected function supportsStructuredOutput(InferenceRequest $request) : bool {
        $model = $request->model() ?: $this->config->model;
        return !Str::contains($model, 'reasoner');
    }

    #[\Override]
    protected function supportsAlternatingRoles(InferenceRequest $request) : bool {
        $model = $request->model() ?: $this->config->model;
        return !Str::contains($model, 'reasoner');
    }

    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        if (!$this->supportsStructuredOutput($request)) {
            return ['type' => 'text'];
        }

        $mode = $this->toResponseFormatMode($request);
        if ($mode === null) {
            return [];
        }

        // Deepseek API supports: json_object, text (no schema support)
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => ['type' => 'json_object'])
            ->withToJsonSchemaHandler(fn() => ['type' => 'json_object']); // Falls back to json_object

        return $responseFormat->as($mode);
    }
}
