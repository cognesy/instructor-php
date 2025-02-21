<?php

namespace Cognesy\Polyglot\LLM\Contracts;

interface CanMapMessages
{
    public function map(array $messages): array;
}