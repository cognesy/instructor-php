<?php declare(strict_types=1);

namespace Cognesy\Doctools\Markdown\Contracts;

interface NodeInterface
{
    public function accept(NodeVisitor $visitor): mixed;
}