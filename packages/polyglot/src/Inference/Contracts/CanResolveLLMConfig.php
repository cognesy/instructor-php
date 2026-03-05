<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Config\LLMConfig;

/**
 * Contract for resolving finalized LLM configuration objects.
 * Implementations return validated, immutable LLMConfig instances
 * regardless of how data was obtained at the application edge.
 */
interface CanResolveLLMConfig
{
    /**
     * Resolve and return a finalized LLMConfig.
     */
    public function resolveConfig(): LLMConfig;
}
