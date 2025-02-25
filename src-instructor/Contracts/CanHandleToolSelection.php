<?php

namespace Cognesy\Instructor\Contracts;

interface CanHandleToolSelection extends CanProvideJsonSchema, CanProvideSchema
{
    public function toToolCallsJson(): array;
}