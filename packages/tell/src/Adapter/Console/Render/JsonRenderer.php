<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Data\TellResult;
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
    public function finish(TellResult $result): void {
        $this->stdout->writeln(json_encode(
            AgentResult::fromResult($result),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }
}
