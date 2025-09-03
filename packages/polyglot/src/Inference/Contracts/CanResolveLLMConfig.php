<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Config\LLMConfig;

/**
 * Contract for resolving LLM configuration from various sources.
 * Implementations handle preset resolution, DSN parsing and override merging
 * to produce a validated, immutable LLMConfig instance.
 */
interface CanResolveLLMConfig
{
    /**
     * Resolve and return a finalized LLMConfig.
     */
    public function resolveConfig(): LLMConfig;
}
