<?php declare(strict_types=1);

namespace Cognesy\Doctools\Markdown\Nodes;

final readonly class HeaderNode extends Node
{
    public function __construct(
        public int $level,
        public string $content,
        int $lineNumber = 0,
    ) {
        parent::__construct($lineNumber);
    }
}