<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Contracts;

interface NodeInterface
{
    public function accept(NodeVisitor $visitor): mixed;
}