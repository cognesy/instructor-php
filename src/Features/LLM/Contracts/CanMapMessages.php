<?php

namespace Cognesy\Instructor\Features\LLM\Contracts;

interface CanMapMessages
{
    public function map(array $messages): array;
}