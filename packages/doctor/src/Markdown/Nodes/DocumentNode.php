<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Nodes;

final readonly class DocumentNode extends Node
{
    /** @var array<Node> */
    public array $children;

    public function __construct(
        Node ...$children
    ) {
        parent::__construct(1); // Document starts at line 1
        $this->children = $children;
    }

    /**
     * @param iterable<Node> $nodes
     */
    public static function fromIterator(iterable $nodes): self
    {
        return new self(...iterator_to_array($nodes));
    }
}