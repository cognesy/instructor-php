<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Contracts;

use Cognesy\Doctor\Markdown\Nodes\Node;

interface NodeVisitor
{
    public function visit(Node $node): mixed;
}