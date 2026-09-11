<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use InvalidArgumentException;

/** Applies exact offering capabilities before provider request rendering. */
final readonly class InferenceRequestPreflight
{
    public function apply(InferenceRequest $request): InferenceRequest {
        $capabilities = $request->modelProfile()?->capabilities;
        if ($capabilities === null) {
            return $request;
        }

        $this->assertAvailable($request->isStreamed(), $capabilities->streaming, 'streaming', $request);
        $this->assertAvailable($request->hasTools(), $capabilities->tools, 'tools', $request);
        $this->assertAvailable($request->hasToolChoice(), $capabilities->toolChoice, 'tool choice', $request);
        $this->assertReasoning($request);

        $request = $this->applyResponseFormatWithTools($request);

        return $this->applyResponseFormat($request);
    }

    private function applyResponseFormatWithTools(InferenceRequest $request): InferenceRequest {
        $status = $request->modelProfile()?->capabilities->responseFormatWithTools;
        if (!$request->hasTools() || !$request->hasNonTextResponseFormat()
            || $status !== SupportStatus::Unsupported
        ) {
            return $request;
        }

        return $this->withEffectiveResponseFormat($request, ResponseFormat::empty());
    }

    private function applyResponseFormat(InferenceRequest $request): InferenceRequest {
        $format = $this->effectiveResponseFormat($request);
        $capabilities = $request->modelProfile()?->capabilities;

        return match ($format->type()) {
            'json_object' => $this->applyJsonObject($request, $capabilities?->jsonObject),
            'json_schema' => $this->applyJsonSchema(
                $request,
                $capabilities?->jsonSchema,
                $capabilities?->jsonObject,
            ),
            default => $request,
        };
    }

    private function applyJsonObject(
        InferenceRequest $request,
        ?SupportStatus $jsonObject,
    ): InferenceRequest {
        $this->assertAvailable(true, $jsonObject ?? SupportStatus::Unknown, 'JSON Object', $request);

        return $request;
    }

    private function applyJsonSchema(
        InferenceRequest $request,
        ?SupportStatus $jsonSchema,
        ?SupportStatus $jsonObject,
    ): InferenceRequest {
        if ($jsonSchema !== SupportStatus::Unsupported) {
            return $request;
        }

        if ($jsonObject === SupportStatus::Supported) {
            return $this->withEffectiveResponseFormat($request, ResponseFormat::jsonObject());
        }

        throw new InvalidArgumentException($this->message('JSON Schema', $request));
    }

    private function assertReasoning(InferenceRequest $request): void {
        if (!$request->hasReasoning()) {
            return;
        }

        $reasoning = $request->modelProfile()?->capabilities->reasoning;
        if ($reasoning?->supports($request->reasoning()) === true) {
            return;
        }

        throw new InvalidArgumentException($this->message('reasoning selection', $request));
    }

    private function assertAvailable(
        bool $requested,
        SupportStatus $status,
        string $feature,
        InferenceRequest $request,
    ): void {
        if (!$requested || !$status->isUnsupported()) {
            return;
        }

        throw new InvalidArgumentException($this->message($feature, $request));
    }

    private function effectiveResponseFormat(InferenceRequest $request): ResponseFormat {
        if (!$request->responseFormat()->isEmpty()) {
            return $request->responseFormat();
        }

        return $request->cachedContext()?->responseFormat() ?? ResponseFormat::empty();
    }

    private function withEffectiveResponseFormat(
        InferenceRequest $request,
        ResponseFormat $format,
    ): InferenceRequest {
        if (!$request->responseFormat()->isEmpty()) {
            return $request->withResponseFormat($format);
        }

        $cachedContext = $request->cachedContext();
        if ($cachedContext === null) {
            return $request;
        }

        return $request->withCachedContext($cachedContext->withResponseFormat($format));
    }

    private function message(string $feature, InferenceRequest $request): string {
        $key = $request->modelProfile()?->key;
        $offering = $key === null ? $request->model() : "{$key->driver}/{$key->model}";

        return "{$feature} is not supported by model offering {$offering}.";
    }
}
