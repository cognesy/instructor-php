<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Nodes;

final readonly class DocumentNode extends Node
{
    /** @var array<Node> */
    public array $children;

    public function __construct(
        Node ...$children
    ) {
        $this->children = $children;
    }

    public static function fromIterator(iterable $nodes): self
    {
        return new self(...iterator_to_array($nodes));
    }
}