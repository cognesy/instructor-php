<?php

namespace Cognesy\Template\Contracts;

use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<\Cognesy\Template\Config\TemplateEngineConfig>
 */
interface CanProvideTemplateConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = ''): TemplateEngineConfig;
}