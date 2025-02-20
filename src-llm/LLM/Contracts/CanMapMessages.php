<?php

namespace Cognesy\LLM\LLM\Contracts;

interface CanMapMessages
{
    public function map(array $messages): array;
}