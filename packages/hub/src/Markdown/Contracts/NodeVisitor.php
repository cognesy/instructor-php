<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Contracts;

use Cognesy\InstructorHub\Markdown\Nodes\Node;

interface NodeVisitor
{
    public function visit(Node $node): mixed;
}