<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

interface CanMapMessages
{
    public function map(array $messages): array;
}