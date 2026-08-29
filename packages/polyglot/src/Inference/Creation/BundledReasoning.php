<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Polyglot\Inference\Contracts\CanTranslateReasoning;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBudgetRange;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningCapabilities;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningDefaultBehavior;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffortMapping;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffortMappings;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningMappingQuality;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelectionKind;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelectionKinds;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;

/** Curated reasoning profiles for bundled, model-identifiable provider routes. */
final class BundledReasoning
{
    public static function openAiChat(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::NamedEffort,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^gpt-5\.6(?:-|$)/', $model) === 1 => self::namedCapabilities(
                    [ReasoningSelectionKind::Disabled, ReasoningSelectionKind::Effort],
                    ReasoningEffort::Low,
                    ReasoningEffort::Medium,
                    ReasoningEffort::High,
                    ReasoningEffort::XHigh,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function openAiResponses(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::OpenResponses,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^gpt-5\.6(?:-|$)/', $model) === 1 => self::namedCapabilities(
                    [ReasoningSelectionKind::Disabled, ReasoningSelectionKind::Effort],
                    ReasoningEffort::Low,
                    ReasoningEffort::Medium,
                    ReasoningEffort::High,
                    ReasoningEffort::XHigh,
                    ReasoningEffort::Max,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function anthropic(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::Anthropic,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^claude-(?:sonnet|opus)-4-6(?:-|$)/', $model) === 1 => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(
                        ReasoningSelectionKind::Disabled,
                        ReasoningSelectionKind::Effort,
                        ReasoningSelectionKind::Budget,
                        ReasoningSelectionKind::Adaptive,
                    ),
                    effortMappings: ReasoningEffortMappings::exact(
                        ReasoningEffort::Low,
                        ReasoningEffort::Medium,
                        ReasoningEffort::High,
                        ReasoningEffort::Max,
                    ),
                    budgetRange: new ReasoningBudgetRange(1024),
                    defaultBehavior: ReasoningDefaultBehavior::Adaptive,
                    reasoningContentVisible: true,
                    reasoningTokensVisible: true,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function deepSeek(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::NamedEffort,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^deepseek-v4-(?:flash|pro|flash-vision-exp)$/', $model) === 1 => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(
                        ReasoningSelectionKind::Disabled,
                        ReasoningSelectionKind::Effort,
                    ),
                    effortMappings: new ReasoningEffortMappings(
                        self::exact(ReasoningEffort::Low),
                        self::lossy(ReasoningEffort::Medium, ReasoningEffort::High),
                        self::exact(ReasoningEffort::High),
                        self::lossy(ReasoningEffort::XHigh, ReasoningEffort::High),
                        self::exact(ReasoningEffort::Max),
                    ),
                    defaultBehavior: ReasoningDefaultBehavior::Enabled,
                    reasoningContentVisible: true,
                    reasoningTokensVisible: true,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function gemini(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::Gemini,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^gemini-3(?:\.|-|$)/', $model) === 1 => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(ReasoningSelectionKind::Effort),
                    effortMappings: ReasoningEffortMappings::exact(
                        ReasoningEffort::Low,
                        ReasoningEffort::High,
                    ),
                    defaultBehavior: ReasoningDefaultBehavior::Mandatory,
                    reasoningContentVisible: true,
                    reasoningTokensVisible: true,
                ),
                preg_match('/^gemini-2\.5(?:-|$)/', $model) === 1 => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(
                        ReasoningSelectionKind::Disabled,
                        ReasoningSelectionKind::Budget,
                        ReasoningSelectionKind::Adaptive,
                    ),
                    effortMappings: ReasoningEffortMappings::none(),
                    budgetRange: new ReasoningBudgetRange(1),
                    defaultBehavior: ReasoningDefaultBehavior::Adaptive,
                    reasoningContentVisible: true,
                    reasoningTokensVisible: true,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function geminiOai(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::NamedEffort,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^gemini-3(?:\.|-|$)/', $model) === 1 => self::mandatoryNamedCapabilities(
                    [ReasoningSelectionKind::Effort],
                    ReasoningEffort::Low,
                    ReasoningEffort::High,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function glm(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::BooleanThinking,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^glm-4\.7(?:-|$)/', $model) === 1 => self::modeCapabilities(
                    ReasoningDefaultBehavior::Enabled,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function qwen(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::Qwen,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^qwen3\.8(?:-|$)/', $model) === 1 => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(
                        ReasoningSelectionKind::Disabled,
                        ReasoningSelectionKind::Enabled,
                        ReasoningSelectionKind::Effort,
                        ReasoningSelectionKind::Budget,
                        ReasoningSelectionKind::Adaptive,
                    ),
                    effortMappings: ReasoningEffortMappings::exact(
                        ReasoningEffort::Low,
                        ReasoningEffort::Medium,
                        ReasoningEffort::XHigh,
                    ),
                    budgetRange: new ReasoningBudgetRange(1),
                    defaultBehavior: ReasoningDefaultBehavior::Adaptive,
                    reasoningContentVisible: true,
                    reasoningTokensVisible: true,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function cohere(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::Cohere,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^command-a-reasoning(?:-|$)/', $model) === 1 => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(
                        ReasoningSelectionKind::Disabled,
                        ReasoningSelectionKind::Enabled,
                        ReasoningSelectionKind::Budget,
                        ReasoningSelectionKind::Adaptive,
                    ),
                    effortMappings: ReasoningEffortMappings::none(),
                    budgetRange: new ReasoningBudgetRange(1),
                    defaultBehavior: ReasoningDefaultBehavior::Enabled,
                    reasoningContentVisible: false,
                    reasoningTokensVisible: true,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function mistral(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::NamedEffort,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^magistral-medium(?:-|$)/', $model) === 1 => self::namedCapabilities(
                    [ReasoningSelectionKind::Disabled, ReasoningSelectionKind::Effort],
                    ReasoningEffort::High,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function moonshot(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::BooleanThinking,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^kimi-k2\.[56](?:-|$)/', $model) === 1 => self::modeCapabilities(
                    ReasoningDefaultBehavior::Enabled,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function xai(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::NamedEffort,
            static fn (string $model): ReasoningCapabilities => match (true) {
                preg_match('/^grok-4\.6(?:-|$)/', $model) === 1 => self::mandatoryNamedCapabilities(
                    [ReasoningSelectionKind::Effort],
                    ReasoningEffort::Low,
                    ReasoningEffort::Medium,
                    ReasoningEffort::High,
                    ReasoningEffort::XHigh,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    public static function openRouter(): CanTranslateReasoning
    {
        return self::translator(
            ReasoningWireFormat::OpenRouter,
            static fn (string $model): ReasoningCapabilities => match ($model) {
                'openai/gpt-oss-120b' => new ReasoningCapabilities(
                    known: true,
                    selectionKinds: new ReasoningSelectionKinds(
                        ReasoningSelectionKind::Disabled,
                        ReasoningSelectionKind::Enabled,
                        ReasoningSelectionKind::Effort,
                        ReasoningSelectionKind::Budget,
                        ReasoningSelectionKind::Adaptive,
                    ),
                    effortMappings: ReasoningEffortMappings::exact(...ReasoningEffort::cases()),
                    budgetRange: new ReasoningBudgetRange(1),
                    defaultBehavior: ReasoningDefaultBehavior::Enabled,
                    reasoningTokensVisible: true,
                ),
                default => ReasoningCapabilities::unknown(),
            },
        );
    }

    /** @param \Closure(string): ReasoningCapabilities $capabilities */
    private static function translator(
        ReasoningWireFormat $wireFormat,
        \Closure $capabilities,
    ): CanTranslateReasoning {
        return new ConfiguredReasoningTranslator($wireFormat, $capabilities);
    }

    /** @param list<ReasoningSelectionKind> $kinds */
    private static function namedCapabilities(
        array $kinds,
        ReasoningEffort ...$efforts,
    ): ReasoningCapabilities {
        return new ReasoningCapabilities(
            known: true,
            selectionKinds: new ReasoningSelectionKinds(...$kinds),
            effortMappings: ReasoningEffortMappings::exact(...$efforts),
            defaultBehavior: ReasoningDefaultBehavior::Enabled,
        );
    }

    private static function modeCapabilities(
        ReasoningDefaultBehavior $defaultBehavior,
    ): ReasoningCapabilities {
        return new ReasoningCapabilities(
            known: true,
            selectionKinds: new ReasoningSelectionKinds(
                ReasoningSelectionKind::Disabled,
                ReasoningSelectionKind::Enabled,
                ReasoningSelectionKind::Adaptive,
            ),
            effortMappings: ReasoningEffortMappings::none(),
            defaultBehavior: $defaultBehavior,
        );
    }

    /** @param list<ReasoningSelectionKind> $kinds */
    private static function mandatoryNamedCapabilities(
        array $kinds,
        ReasoningEffort ...$efforts,
    ): ReasoningCapabilities {
        return new ReasoningCapabilities(
            known: true,
            selectionKinds: new ReasoningSelectionKinds(...$kinds),
            effortMappings: ReasoningEffortMappings::exact(...$efforts),
            defaultBehavior: ReasoningDefaultBehavior::Mandatory,
        );
    }

    private static function exact(ReasoningEffort $effort): ReasoningEffortMapping
    {
        return new ReasoningEffortMapping($effort, $effort->value, $effort);
    }

    private static function lossy(
        ReasoningEffort $requested,
        ReasoningEffort $effective,
    ): ReasoningEffortMapping {
        return new ReasoningEffortMapping(
            requested: $requested,
            providerValue: $requested->value,
            effective: $effective,
            quality: ReasoningMappingQuality::Lossy,
        );
    }
}
