<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

/**
 * Overrides the LLMConfig when a skill with a model field is active.
 *
 * Triggers on BeforeStep. Checks AgentState metadata for an active skill model
 * override and applies it by creating a new LLMConfig with the specified model.
 */
final readonly class SkillModelOverrideHook implements HookInterface
{
    public const META_KEY = 'active_skill_model';

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $model = $context->state()->metadata()->get(self::META_KEY);
        if (!is_string($model) || $model === '') {
            return $context;
        }

        $currentConfig = $context->state()->llmConfig();
        if ($currentConfig !== null && $currentConfig->model === $model) {
            return $context;
        }

        // Create a new config based on existing with overridden model
        $newConfig = $currentConfig !== null
            ? new LLMConfig(
                apiUrl: $currentConfig->apiUrl,
                apiKey: $currentConfig->apiKey,
                endpoint: $currentConfig->endpoint,
                queryParams: $currentConfig->queryParams,
                metadata: $currentConfig->metadata,
                model: $model,
                maxTokens: $currentConfig->maxTokens,
                contextLength: $currentConfig->contextLength,
                maxOutputLength: $currentConfig->maxOutputLength,
                driver: $currentConfig->driver,
                options: $currentConfig->options,
                pricing: $currentConfig->pricing,
            )
            : new LLMConfig(model: $model);

        return $context->withState($context->state()->withLLMConfig($newConfig));
    }
}
