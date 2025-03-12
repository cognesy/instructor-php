<?php

namespace Cognesy\Polyglot\LLM\Data;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ContentBlocks
{
    /**
     * @var ContentBlock[] $blocks
     */
    private array $blocks = [];

    public function __construct(ContentBlock ...$blocks) {
        $this->blocks = $blocks;
    }

    public function add(ContentBlock $block) : self {
        $this->blocks[] = $block;
        return $this;
    }

    public function all() : array {
        return $this->blocks;
    }

    public function count() : int {
        return count($this->blocks);
    }

    public function first() : ?ContentBlock {
        return $this->blocks[0] ?? null;
    }

    public function last() : ?ContentBlock {
        if (empty($this->blocks)) {
            return null;
        }
        return $this->blocks[count($this->blocks) - 1];
    }

    public function updateLast(string $text, array $data = []) : self {
        $last = $this->last();
        if ($last) {
            $last->withContent($text)->withData($data);
        } else {
            $this->add(new ContentBlock(content: $text, data: $data));
        }
        return $this;
    }

    public function empty() : bool {
        return empty($this->blocks);
    }

    public function reset() : void {
        $this->blocks = [];
    }

    public function content() : string {
        return implode(
            separator: "\n",
            array: array_map(
                fn($block) => $block->content(),
                $this->blocks
            )
        );
    }

    public function toString() : string {
        return implode(
            separator: "\n",
            array: array_map(
                fn($block) => $block->toString(),
                $this->blocks
            )
        );
    }

    public function toArray() : array {
        return array_map(
            fn($block) => $block->toArray(),
            $this->blocks
        );
    }
}