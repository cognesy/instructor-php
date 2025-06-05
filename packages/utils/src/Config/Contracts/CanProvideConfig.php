<?php

namespace Cognesy\Utils\Config\Contracts;

/**
 * Generic configuration provider interface
 *
 * @template T
 */
interface CanProvideConfig
{
    public function getConfig(?string $preset = '');
}
