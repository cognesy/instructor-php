<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Models\ModelCapabilities;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

/**
 * Disables provider-default reasoning for tool calls on routes whose exact model record says
 * function tools cannot be combined with reasoning (`toolsWithReasoning: unsupported`) and
 * reasoning can be disabled. Models without that fact are sent unchanged.
 */
final readonly class OpenAIToolReasoningPolicy
{
    private const DRIVER = 'openai';

    public function __construct(
        private ?ModelCatalog $models = null,
    ) {}

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function apply(InferenceRequest $request, array $body): array {
        return match (true) {
            !isset($body['tools']) => $body,
            !$request->reasoning()->isDefault() => $body,
            array_key_exists('reasoning_effort', $body) => $body,
            !$this->requiresDisabledReasoning($request, (string) ($body['model'] ?? '')) => $body,
            default => [...$body, 'reasoning_effort' => 'none'],
        };
    }

    private function requiresDisabledReasoning(InferenceRequest $request, string $model): bool {
        $capabilities = $request->modelProfile()->capabilities ?? $this->catalogCapabilities($model);

        return $capabilities->toolsWithReasoning === SupportStatus::Unsupported
            && $capabilities->reasoning->supports(ReasoningSelection::disabled());
    }

    private function catalogCapabilities(string $model): ModelCapabilities {
        return match ($model) {
            '' => new ModelCapabilities(),
            default => ($this->models ?? ModelCatalog::discover())->find(self::DRIVER, $model)->capabilities,
        };
    }
}
