<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellRuntime;
use Generator;

final readonly class Tell
{
    private function __construct(
        private string $directory,
        private TellAgentFactory $agents,
        private TellRuntime $runtime,
    ) {}

    public static function open(
        string $directory,
        ?TellAgentFactory $agents = null,
        ?CanProvideCancellationSignal $cancellation = null,
    ): self
    {
        $agents ??= TellAgentFactory::installed();

        return new self(
            directory: $directory,
            agents: $agents,
            runtime: new TellRuntime($agents, $cancellation),
        );
    }

    public function run(TellRequest $request): TellResult
    {
        $request = match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };

        return $this->runtime->run($request);
    }

    /**
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    public function runStream(TellRequest $request): Generator
    {
        $request = match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };

        return $this->runtime->stream($request);
    }

    public function workspace(): TellWorkspace
    {
        return new TellWorkspace($this, $this->agents, $this->directory);
    }

    public function conversation(string $name): TellConversation
    {
        return $this->workspace()->conversation($name);
    }
}
