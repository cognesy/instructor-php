<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\LLMConfig;

interface CanProvideLLMConfig
{
    public function getConfig(?string $preset = ''): LLMConfig;
}