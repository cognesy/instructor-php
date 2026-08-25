<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use JsonException;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class JsonRenderer implements OutputRenderer
{
    public function __construct(private OutputInterface $stdout) {}

    #[Override]
    public function attach(AgentLoop $loop): void {}

    /** @throws JsonException */
    #[Override]
    public function finish(AgentState $state, array $warnings = [], bool $transient = false): void
    {
        $this->stdout->writeln(json_encode(
            AgentResult::fromState($state, $warnings, $transient),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }
}
