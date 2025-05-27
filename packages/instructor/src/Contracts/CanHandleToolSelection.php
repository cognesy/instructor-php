<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

interface CanHandleToolSelection extends CanProvideJsonSchema, CanProvideSchema
{
    public function toToolCallsJson(): array;
}