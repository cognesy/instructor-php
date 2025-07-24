<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Doctest\Internal;

final class DoctestIdNode extends DoctestNode
{
    public function __construct(
        public readonly string $id,
        int $startLine,
        int $endLine,
    ) {
        parent::__construct($startLine, $endLine);
    }
}