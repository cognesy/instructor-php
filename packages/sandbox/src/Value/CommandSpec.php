<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Value;

final readonly class CommandSpec
{
    private Argv $argv;
    private ?string $stdin;

    public function __construct(Argv $argv, ?string $stdin = null) {
        $this->argv = $argv;
        $this->stdin = $stdin;
    }

    public function argv() : Argv {
        return $this->argv;
    }

    public function stdin() : ?string {
        return $this->stdin;
    }
}
