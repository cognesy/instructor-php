<?php declare(strict_types=1);

namespace Cognesy\Doctools\Markdown\Contracts;

use Cognesy\Doctools\Markdown\Nodes\Node;

interface NodeVisitor
{
    public function visit(Node $node): mixed;
}