<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Execution\CanDisposeTellResources;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Execution\CanObserveTellRun;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellConversation;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Core\Discovery\TellCatalogue;
use Cognesy\Tell\Core\Tool\TellTools;
use Cognesy\Tell\Core\Workspace\TellWorkspace;
use Generator;

final readonly class Tell
{
    public function __construct(
        private string $directory,
        private CanRunTell $runner,
        private CanManageTellWorkspace $workspaces,
        private CanAccessTellConversations $conversations,
        private CanCatalogueTellProviders $providerCatalogue,
        private CanDispatchTellTool $toolDispatcher,
        private CanDisposeTellResources $resources,
        private ?CanProvideCancellationSignal $cancellation,
    ) {}

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
    public function start(TellRequest $request): CanObserveTellRun {
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

    public function conversation(string $name): CanUseTellConversation {
        return $this->workspace()->conversation($name);
    }

    public function catalogue(): TellCatalogue {
        return new TellCatalogue(
            $this->providerCatalogue,
            $this->directory,
        );
    }

    public function tools(): TellTools {
        return new TellTools($this->toolDispatcher, $this->cancellation);
    }

    /** Release host-owned resources. Safe to call more than once. */
    public function dispose(): void {
        $this->resources->dispose();
    }
}
