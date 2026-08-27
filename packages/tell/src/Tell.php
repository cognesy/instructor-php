<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Composition\TellHost;
use Cognesy\Tell\Discovery\TellCatalogue;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Testing\TellTestFactory;
use Cognesy\Tell\Tool\TellTools;
use Generator;

final readonly class Tell
{
    private function __construct(
        private string $directory,
        private TellAgentFactory $agents,
        private TellHost $host,
        private ?CanProvideCancellationSignal $cancellation,
    ) {}

    public static function open(
        string $directory,
        ?TellAgentFactory $agents = null,
        ?CanProvideCancellationSignal $cancellation = null,
    ): self {
        $agents ??= TellAgentFactory::installed();
        $host = TellHost::standard(
            directory: $directory,
            paths: $agents->paths(),
            agentFactory: $agents,
            cancellation: $cancellation,
        )->boot();

        return new self(
            directory: $directory,
            agents: $agents,
            host: $host,
            cancellation: $cancellation,
        );
    }

    /**
     * Open Tell with deterministic, in-process model responses.
     *
     * No network request or real provider credential is used. For scripted
     * tool, failure, or usage steps, use TellTestFactory directly.
     */
    public static function testing(string $directory, string ...$responses): self
    {
        return TellTestFactory::responses(...$responses)->open($directory);
    }

    public function run(TellRequest $request): TellResult
    {
        $request = match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };

        return $this->host->runner()->run($request);
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

        return $this->host->runner()->stream($request);
    }

    public function workspace(): TellWorkspace
    {
        return new TellWorkspace(
            $this->agents,
            $this->directory,
            $this->host->workspace(),
            $this->host->conversations(),
        );
    }

    public function conversation(string $name): TellConversation
    {
        return $this->workspace()->conversation($name);
    }

    public function catalogue(): TellCatalogue
    {
        return new TellCatalogue($this->agents, $this->directory);
    }

    public function tools(): TellTools
    {
        return TellTools::controlled($this->host->tools(), $this->cancellation);
    }

    /** Explicit control surface for inspection and host-owned capabilities. */
    public function host(): TellHost
    {
        return $this->host;
    }

    /** Release host-owned resources. Safe to call more than once. */
    public function dispose(): void
    {
        $this->host->dispose();
    }
}
