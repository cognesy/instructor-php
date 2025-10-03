<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

final class ToolCallAssemblerResult
{
    public function __construct(
        private ToolCallAssembler $assembler,
        private bool $requiresBufferReset,
    ) {}

    public function assembler(): ToolCallAssembler {
        return $this->assembler;
    }

    public function requiresBufferReset(): bool {
        return $this->requiresBufferReset;
    }
}

