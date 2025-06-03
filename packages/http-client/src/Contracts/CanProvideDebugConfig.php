<?php

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Debug\DebugConfig;

interface CanProvideDebugConfig
{
    public function getConfig(string $preset = '') : DebugConfig;
}