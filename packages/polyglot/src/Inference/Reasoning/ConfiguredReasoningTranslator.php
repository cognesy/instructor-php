<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateReasoning;
use InvalidArgumentException;

/** Translates a curated model capability profile into one provider wire family. */
final readonly class ConfiguredReasoningTranslator implements CanTranslateReasoning
{
    /** @param Closure(string): ReasoningCapabilities $capabilities */
    public function __construct(
        private ReasoningWireFormat $wireFormat,
        private Closure $capabilities,
    ) {}

    public function capabilities(string $model): ReasoningCapabilities
    {
        return ($this->capabilities)($model);
    }

    public function translate(string $model, ReasoningSelection $selection): ReasoningTranslation
    {
        if ($selection->isDefault()) {
            return ReasoningTranslation::omitted($selection);
        }

        $capabilities = $this->capabilities($model);
        if (! $capabilities->supports($selection)) {
            throw new InvalidArgumentException(
                "Reasoning selection {$selection->kind->value} is not supported by model {$model}.",
            );
        }

        $mapping = $selection->effort === null
            ? null
            : $capabilities->effortMappings->find($selection->effort);
        $quality = match ($mapping) {
            null => ReasoningMappingQuality::Exact,
            default => $mapping->quality,
        };
        $effective = match (true) {
            $mapping === null => $selection,
            $selection->kind === ReasoningSelectionKind::Adaptive => ReasoningSelection::adaptive($mapping->effective),
            default => ReasoningSelection::effort($mapping->effective),
        };

        return new ReasoningTranslation(
            options: new ReasoningOptions($this->toOptions($selection, $mapping)),
            quality: $quality,
            requested: $selection,
            effective: $effective,
        );
    }

    private function toOptions(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): array {
        return match ($this->wireFormat) {
            ReasoningWireFormat::NamedEffort => $this->namedEffort($selection, $mapping),
            ReasoningWireFormat::OpenResponses => [
                'reasoning' => ['effort' => $this->effortValue($selection, $mapping)],
            ],
            ReasoningWireFormat::Anthropic => $this->anthropic($selection, $mapping),
            ReasoningWireFormat::Gemini => $this->gemini($selection, $mapping),
            ReasoningWireFormat::BooleanThinking => [
                'thinking' => $selection->kind !== ReasoningSelectionKind::Disabled,
            ],
            ReasoningWireFormat::Cohere => $this->cohere($selection),
            ReasoningWireFormat::OpenRouter => $this->openRouter($selection, $mapping),
            ReasoningWireFormat::Qwen => $this->qwen($selection, $mapping),
        };
    }

    private function namedEffort(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): array {
        return ['reasoning_effort' => $this->effortValue($selection, $mapping)];
    }

    private function effortValue(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): string {
        return match ($selection->kind) {
            ReasoningSelectionKind::Disabled => 'none',
            ReasoningSelectionKind::Adaptive,
            ReasoningSelectionKind::Enabled => 'auto',
            default => $this->providerValue($mapping),
        };
    }

    private function providerValue(?ReasoningEffortMapping $mapping): string
    {
        if ($mapping === null) {
            throw new InvalidArgumentException('Reasoning effort mapping is missing.');
        }

        return $mapping->providerValue;
    }

    private function anthropic(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): array {
        return match ($selection->kind) {
            ReasoningSelectionKind::Disabled => ['thinking' => ['type' => 'disabled']],
            ReasoningSelectionKind::Budget => [
                'thinking' => [
                    'type' => 'enabled',
                    'budget_tokens' => $selection->budgetTokens,
                ],
            ],
            ReasoningSelectionKind::Adaptive => array_replace_recursive(
                ['thinking' => ['type' => 'adaptive']],
                $mapping === null ? [] : ['output_config' => ['effort' => $mapping->providerValue]],
            ),
            ReasoningSelectionKind::Effort => [
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => $mapping?->providerValue],
            ],
            default => throw new InvalidArgumentException('Unsupported Anthropic reasoning selection.'),
        };
    }

    private function gemini(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): array {
        $thinkingConfig = match ($selection->kind) {
            ReasoningSelectionKind::Disabled => ['thinkingBudget' => 0],
            ReasoningSelectionKind::Budget => ['thinkingBudget' => $selection->budgetTokens],
            ReasoningSelectionKind::Adaptive => ['thinkingBudget' => -1],
            ReasoningSelectionKind::Effort => ['thinkingLevel' => strtoupper((string) $mapping?->providerValue)],
            default => throw new InvalidArgumentException('Unsupported Gemini reasoning selection.'),
        };

        return ['generationConfig' => ['thinkingConfig' => $thinkingConfig]];
    }

    private function cohere(ReasoningSelection $selection): array
    {
        return match ($selection->kind) {
            ReasoningSelectionKind::Disabled => ['thinking' => ['type' => 'disabled']],
            ReasoningSelectionKind::Budget => [
                'thinking' => [
                    'type' => 'enabled',
                    'token_budget' => $selection->budgetTokens,
                ],
            ],
            ReasoningSelectionKind::Enabled,
            ReasoningSelectionKind::Adaptive => ['thinking' => ['type' => 'enabled']],
            default => throw new InvalidArgumentException('Unsupported Cohere reasoning selection.'),
        };
    }

    private function openRouter(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): array {
        $reasoning = match ($selection->kind) {
            ReasoningSelectionKind::Disabled => ['enabled' => false],
            ReasoningSelectionKind::Enabled,
            ReasoningSelectionKind::Adaptive => ['enabled' => true],
            ReasoningSelectionKind::Budget => ['max_tokens' => $selection->budgetTokens],
            ReasoningSelectionKind::Effort => ['effort' => $mapping?->providerValue],
            default => throw new InvalidArgumentException('Unsupported OpenRouter reasoning selection.'),
        };

        return ['reasoning' => $reasoning];
    }

    private function qwen(
        ReasoningSelection $selection,
        ?ReasoningEffortMapping $mapping,
    ): array {
        return match ($selection->kind) {
            ReasoningSelectionKind::Disabled => ['enable_thinking' => false],
            ReasoningSelectionKind::Enabled,
            ReasoningSelectionKind::Adaptive => ['enable_thinking' => true],
            ReasoningSelectionKind::Budget => [
                'enable_thinking' => true,
                'thinking_budget' => $selection->budgetTokens,
            ],
            ReasoningSelectionKind::Effort => [
                'enable_thinking' => true,
                'reasoning_effort' => $mapping?->providerValue,
            ],
            default => throw new InvalidArgumentException('Unsupported Qwen reasoning selection.'),
        };
    }
}
