<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<StructuredOutputConfig>
 */
interface CanProvideStructuredOutputConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = ''): StructuredOutputConfig;
}