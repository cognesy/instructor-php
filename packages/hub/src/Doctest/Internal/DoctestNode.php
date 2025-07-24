<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Doctest\Internal;

abstract class DoctestNode
{
    public function __construct(
        public readonly int $startLine,
        public readonly int $endLine,
    ) {}
}