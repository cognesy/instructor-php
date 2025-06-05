<?php

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<DebugConfig>
 */
interface CanProvideDebugConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = '') : DebugConfig;
}