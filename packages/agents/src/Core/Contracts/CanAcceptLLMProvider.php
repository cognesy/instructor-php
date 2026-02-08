<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * Implemented by drivers that can have their LLM provider injected or swapped.
 */
interface CanAcceptLLMProvider
{
    public function llmProvider(): LLMProvider;

    public function withLLMProvider(LLMProvider $llm): static;
}
