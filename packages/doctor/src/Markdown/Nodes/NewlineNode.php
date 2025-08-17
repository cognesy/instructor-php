<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Nodes;

final readonly class NewlineNode extends Node
{
    public function __construct(int $lineNumber = 0) {
        parent::__construct($lineNumber);
    }
}