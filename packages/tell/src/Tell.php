<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Composition\Standalone\StandaloneTellHost;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Contracts\CanDisposeTellResources;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Discovery\TellCatalogue;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellRun;
use Cognesy\Tell\Testing\TellTestFactory;
use Cognesy\Tell\Tool\TellTools;
use Cognesy\Tell\Workspace\TellConversation;
use Cognesy\Tell\Workspace\TellWorkspace;
use Generator;

final readonly class Tell
{
    private function __construct(
        private string $directory,
        private CanRunTell $runner,
        private CanManageTellWorkspace $workspaces,
        private CanAccessTellConversations $conversations,
        private CanResolveTellPaths $paths,
        private CanDispatchTellTool $toolDispatcher,
        private CanDisposeTellResources $resources,
        private ?CanProvideCancellationSignal $cancellation,
    ) {}

    public static function open(
        string $directory,
        ?TellAgentFactory $agents = null,
        ?CanProvideCancellationSignal $cancellation = null,
    ): self {
        return StandaloneTellHost::open($directory, $agents, $cancellation);
    }

    /** @internal Composition roots construct the public facade through this seam. */
    public static function fromCapabilities(
        string $directory,
        CanRunTell $runner,
        CanManageTellWorkspace $workspaces,
        CanAccessTellConversations $conversations,
        CanResolveTellPaths $paths,
        CanDispatchTellTool $tools,
        CanDisposeTellResources $resources,
        ?CanProvideCancellationSignal $cancellation = null,
    ): self {
        return new self(
            directory: $directory,
            runner: $runner,
            workspaces: $workspaces,
            conversations: $conversations,
            paths: $paths,
            toolDispatcher: $tools,
            resources: $resources,
            cancellation: $cancellation,
        );
    }

    /**
     * Open Tell with deterministic, in-process model responses.
     *
     * No network request or real provider credential is used. For scripted
     * tool, failure, or usage steps, use TellTestFactory directly.
     */
    public static function testing(string $directory, string ...$responses): self {
        return TellTestFactory::responses(...$responses)->open($directory);
    }

    public function run(TellRequest $request): TellResult {
        $request = match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };

        return $this->runner->run($request);
    }

    /**
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    public function runStream(TellRequest $request): Generator {
        $request = match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };

        return $this->runner->stream($request);
    }

    /**
     * Starts a run and hands back a handle. Prefer this over runStream() when
     * you may stop consuming checkpoints early: the handle still carries the
     * result, and a run torn down before it committed is reported.
     */
    public function start(TellRequest $request): TellRun {
        $request = match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };

        return $this->runner->start($request);
    }

    public function workspace(): TellWorkspace {
        return new TellWorkspace(
            $this->directory,
            $this->workspaces,
            $this->conversations,
        );
    }

    public function conversation(string $name): TellConversation {
        return $this->workspace()->conversation($name);
    }

    public function catalogue(): TellCatalogue {
        $resolved = $this->paths->resolve($this->directory);

        return new TellCatalogue(
            new \Cognesy\Tell\Configuration\TellPaths($resolved->packageAgents, $resolved->home),
            $this->directory,
        );
    }

    public function tools(): TellTools {
        return TellTools::controlled($this->toolDispatcher, $this->cancellation);
    }

    /** Release host-owned resources. Safe to call more than once. */
    public function dispose(): void {
        $this->resources->dispose();
    }
}
