<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Nodes;

use Cognesy\InstructorHub\Markdown\Contracts\NodeInterface;
use Cognesy\InstructorHub\Markdown\Contracts\NodeVisitor;

abstract readonly class Node implements NodeInterface
{
    public function accept(NodeVisitor $visitor): mixed {
        return $visitor->visit($this);
    }
}