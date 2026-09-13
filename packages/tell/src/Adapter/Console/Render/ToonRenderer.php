<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Data\TellResult;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ToonRenderer implements OutputRenderer
{
    private StructuredOutput $output;

    public function __construct(OutputInterface $output) {
        $this->output = new StructuredOutput($output);
    }

    /**
     * Progress channels belong to the invocation, not to one output format, so
     * they are attached alongside this renderer rather than by it.
     */
    #[Override]
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void {}

    #[Override]
    public function finish(TellResult $result): void {
        $this->output->write(AgentResult::fromResult($result));
    }
}
