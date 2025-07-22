<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Nodes;

final readonly class DocumentNode extends Node
{
    /** @var array<Node> */
    public array $children;

    public function __construct(
        Node ...$children
    ) {
        $this->children = $children;
    }
}