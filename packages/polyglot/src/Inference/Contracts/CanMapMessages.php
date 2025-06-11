<?php

namespace Cognesy\Polyglot\Inference\Contracts;

interface CanMapMessages
{
    public function map(array $messages): array;
}