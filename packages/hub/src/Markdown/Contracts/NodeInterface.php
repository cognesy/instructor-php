<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Contracts;

interface NodeInterface
{
    public function accept(NodeVisitor $visitor): mixed;
}