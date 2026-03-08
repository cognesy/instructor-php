<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

final readonly class ToolCallDelta
{
    public function __construct(
        public string $id = '',
        public string $name = '',
        public string $args = '',
    ) {}
}
