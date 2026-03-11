<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Value;

final readonly class CommandSpec
{
    public function __construct(
        private Argv $argv,
        private ?string $stdin = null,
    ) {}

    public function argv(): Argv {
        return $this->argv;
    }

    public function stdin(): ?string {
        return $this->stdin;
    }
}
