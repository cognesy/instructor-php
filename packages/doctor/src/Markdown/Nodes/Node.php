<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Nodes;

use Cognesy\Doctor\Markdown\Contracts\NodeInterface;
use Cognesy\Doctor\Markdown\Contracts\NodeVisitor;

abstract readonly class Node implements NodeInterface
{
    public function accept(NodeVisitor $visitor): mixed {
        return $visitor->visit($this);
    }
}