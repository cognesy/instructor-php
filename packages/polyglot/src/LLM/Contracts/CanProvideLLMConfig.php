<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<LLMConfig>
 */
interface CanProvideLLMConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = ''): LLMConfig;
}