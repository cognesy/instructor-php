<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputConfig;

interface CanProvideStructuredOutputConfig
{
    public function getConfig(?string $preset = ''): StructuredOutputConfig;
}