<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Observability\TellEventNormalizer;
use JsonException;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class JsonRenderer implements OutputRenderer
{
    public function __construct(private OutputInterface $stdout) {}

    #[Override]
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void {}

    /** @throws JsonException */
    #[Override]
    public function finish(AgentState $state, array $warnings = [], TellExecutionMode $mode = TellExecutionMode::Stateless, ?array $branch = null, array $diagnostics = []): void {
        $this->stdout->writeln(json_encode(
            AgentResult::fromState($state, $warnings, $mode, $branch, $diagnostics),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }
}
