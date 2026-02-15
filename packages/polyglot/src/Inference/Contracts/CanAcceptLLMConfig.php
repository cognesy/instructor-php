<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Config\LLMConfig;

interface CanAcceptLLMConfig
{
    public function withLLMConfig(LLMConfig $config): static;
}
