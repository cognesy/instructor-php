<?php

namespace Cognesy\Config\Contracts;

interface CanProvideConfig
{
    public function getConfig(string $group, ?string $preset = null) : array;
}