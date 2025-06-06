<?php

namespace Cognesy\Utils\Config\Contracts;

interface CanProvideConfig
{
    public function getConfig(string $group, ?string $preset = null) : array;
}