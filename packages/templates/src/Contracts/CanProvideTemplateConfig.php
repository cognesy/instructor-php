<?php

namespace Cognesy\Template\Contracts;

use Cognesy\Template\Data\TemplateEngineConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<TemplateEngineConfig>
 */
interface CanProvideTemplateConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = ''): TemplateEngineConfig;
}