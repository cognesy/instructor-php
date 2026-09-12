<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequestAdjustment;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningMappingQuality;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelectionKind;
use InvalidArgumentException;

/** Applies exact offering capabilities before provider request rendering. */
final readonly class InferenceRequestPreflight
{
    public function __construct(private bool $allowLossyFallback = false) {}

    public function apply(InferenceRequest $request): InferenceRequest {
        $request = $this->applyReasoning($request);
        $capabilities = $request->modelProfile()?->capabilities;
        if ($capabilities === null) {
            return $request;
        }

        $this->assertAvailable($request->isStreamed(), $capabilities->streaming, 'streaming', $request);
        $this->assertAvailable($request->hasTools(), $capabilities->tools, 'tools', $request);
        $this->assertAvailable($request->hasToolChoice(), $capabilities->toolChoice, 'tool choice', $request);
        $this->assertResponseFormatWithTools($request);

        return $this->applyResponseFormat($request);
    }

    private function assertResponseFormatWithTools(InferenceRequest $request): void {
        $status = $request->modelProfile()?->capabilities->responseFormatWithTools;
        if (!$request->hasTools() || !$request->hasNonTextResponseFormat()
            || $status !== SupportStatus::Unsupported
        ) {
            return;
        }

        throw new InvalidArgumentException($this->message('response format with tools', $request));
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

        if ($jsonObject !== SupportStatus::Supported) {
            throw new InvalidArgumentException($this->message('JSON Schema', $request));
        }
        if (!$this->allowLossyFallback) {
            throw new InvalidArgumentException(
                'JSON Schema requires lossy fallback to JSON Object, but '
                . 'llm.allow_lossy_fallback is false.',
            );
        }

        return $this->withEffectiveResponseFormat($request, ResponseFormat::jsonObject())
            ->withAdjustment(new InferenceRequestAdjustment(
                feature: 'response_format',
                requested: 'json_schema',
                effective: 'json_object',
                reason: 'The exact offering does not support JSON Schema.',
            ));
    }

    private function applyReasoning(InferenceRequest $request): InferenceRequest {
        $selection = $request->reasoning();
        $reasoning = $request->reasoningCapabilities();
        if ($selection->isDefault() || $reasoning === null || !$reasoning->known) {
            return $request;
        }

        $mapping = $selection->effort === null
            ? null
            : $reasoning->effortMappings->find($selection->effort);
        if ($mapping?->quality !== ReasoningMappingQuality::Lossy) {
            if ($reasoning->supports($selection)) {
                return $request;
            }

            throw new InvalidArgumentException($this->message('reasoning selection', $request));
        }
        if (!$this->allowLossyFallback) {
            throw new InvalidArgumentException(
                'Reasoning selection requires a lossy mapping, but '
                . 'llm.allow_lossy_fallback is false.',
            );
        }

        $effective = match ($selection->kind) {
            ReasoningSelectionKind::Adaptive => ReasoningSelection::adaptive($mapping->effective),
            default => ReasoningSelection::effort($mapping->effective),
        };

        return $request->withAdjustment(new InferenceRequestAdjustment(
            feature: 'reasoning',
            requested: $this->reasoningLabel($selection),
            effective: $this->reasoningLabel($effective),
            reason: 'The supplied reasoning effort mapping is lossy.',
        ));
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

    private function reasoningLabel(ReasoningSelection $selection): string {
        return match ($selection->kind) {
            ReasoningSelectionKind::Effort,
            ReasoningSelectionKind::Adaptive => "{$selection->kind->value}:{$selection->effort?->value}",
            ReasoningSelectionKind::Budget => "budget:{$selection->budgetTokens}",
            default => $selection->kind->value,
        };
    }
}
