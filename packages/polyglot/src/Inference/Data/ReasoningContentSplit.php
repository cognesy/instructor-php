<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

final readonly class ReasoningContentSplit
{
    public function __construct(
        public string $content,
        public string $reasoningContent,
    ) {}
}
