<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\LLMProvider;

interface CanAcceptLLMProvider
{
    public function llmProvider(): LLMProvider;

    public function withLLMProvider(LLMProvider $llm): static;
}
