<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Agent;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Models\ModelCapabilities;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use InvalidArgumentException;

/**
 * Tell agents always call tools. When the resolved offering forbids tools with enabled reasoning
 * (`toolsWithReasoning: unsupported`) and the run keeps reasoning on, the agent is moved to the
 * provider's Responses API for the same model, which accepts tools with reasoning.
 */
final readonly class TellToolReasoningRoute
{
    /** @var array<string, array{driver: string, endpoint: string}> */
    private const RESPONSES_ROUTES = [
        'openai' => ['driver' => 'openai-responses', 'endpoint' => '/responses'],
    ];

    public function __construct(
        private ModelCatalog $catalog,
    ) {}

    public function apply(LLMConfig $config, ?ReasoningEffort $effort): LLMConfig {
        $capabilities = $this->catalog->find($config->driver, $config->model)->capabilities;
        if (!$this->keepsReasoningWithTools($capabilities, $effort)) {
            return $config;
        }
        $route = self::RESPONSES_ROUTES[$config->driver] ?? null;
        $target = match ($route) {
            null => null,
            default => $this->catalog->find($route['driver'], $config->model)->capabilities,
        };
        if ($route === null || $target?->toolsWithReasoning !== SupportStatus::Supported) {
            throw new InvalidArgumentException(
                "Model '{$config->driver}/{$config->model}' does not accept tools with enabled reasoning "
                . 'according to Polyglot capability metadata. Use a connection that supports it, '
                . 'or run without a reasoning effort on a model that can disable reasoning.',
            );
        }

        return $config->withOverrides($route);
    }

    private function keepsReasoningWithTools(ModelCapabilities $capabilities, ?ReasoningEffort $effort): bool {
        return match (true) {
            $capabilities->toolsWithReasoning !== SupportStatus::Unsupported => false,
            $effort !== null => true,
            default => !$capabilities->reasoning->supports(ReasoningSelection::disabled()),
        };
    }
}
