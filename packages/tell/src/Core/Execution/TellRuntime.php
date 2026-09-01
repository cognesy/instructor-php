<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Execution;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellExecutionWorkspace;
use Cognesy\Tell\Data\TellBranchSelection;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final readonly class TellRuntime implements \Cognesy\Tell\Core\Contract\Execution\CanExecuteTellRuntime
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanOpenTellExecutionWorkspace $workspaces,
        private CanTraceTellExecution $tracer,
        private CanResolveTellConfiguration $configuration,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanObserveTellExecution $observer = null,
    ) {}

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    #[\Override]
    public function run(TellRequest $request, ?callable $prepareLoop = null): TellResult {
        return $this->start($request, $prepareLoop)->wait();
    }

    /**
     * @param  callable(AgentLoop, TellRequest, ?string): void|null  $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    #[\Override]
    public function stream(TellRequest $request, ?callable $prepareLoop = null): Generator {
        return $this->start($request, $prepareLoop)->checkpoints();
    }

    /**
     * Starts a run and hands back a handle. Unlike a bare generator, the handle
     * carries the outcome, so a caller that stops iterating early still gets a
     * result and an abandoned run is reported rather than lost.
     *
     * @param  callable(AgentLoop, TellRequest, ?string): void|null  $prepareLoop
     */
    #[\Override]
    public function start(TellRequest $request, ?callable $prepareLoop = null): TellRun {
        $this->assertSelection($request);
        $request = $this->configuration->resolve($request)->request;
        $diagnostics = new TellDiagnostics();
        $outcome = new TellRunOutcome();

        $stream = match ($request->mode) {
            TellExecutionMode::Automatic => $this->streamAutomatic($request, $prepareLoop, $diagnostics, $outcome),
            TellExecutionMode::Stateless => $this->streamStateless($request, $prepareLoop, $diagnostics, $outcome),
            TellExecutionMode::Durable => $this->streamDurable($request, $prepareLoop, $diagnostics, $outcome),
            TellExecutionMode::Transient => $this->streamTransient($request, $prepareLoop, $diagnostics, $outcome),
        };

        return new TellRun($stream, $outcome, $diagnostics);
    }

    /**
     * Publishes a terminal checkpoint to the outcome the moment it appears, so
     * the run's result never depends on the caller advancing past the final
     * yield. Runners that commit durably record themselves and win the race.
     */
    private function recordTerminal(?TellRunOutcome $outcome, AgentState $state): void {
        if ($outcome === null || $outcome->state() !== null) {
            return;
        }
        if ($state->status() !== ExecutionStatus::Completed) {
            return;
        }
        $outcome->recordCommitted($state);
    }

    /**
     * Settles a fully drained run: whatever the outcome already holds wins, so a
     * committed state is never overwritten by a recomputed one.
     *
     * @param  callable(AgentState): TellResult  $build
     */
    private function finish(?TellRunOutcome $outcome, AgentState $state, callable $build): TellResult {
        $outcome?->recordCommitted($state);

        return $outcome?->result() ?? $build($state);
    }

    /**
     * Resolves the same branch and policy inputs as a turn without creating a
     * state, attaching traces, or publishing an arena ref. Direct tool calls
     * deliberately use this boundary instead of run().
     */
    #[\Override]
    public function resolveDirectRequest(TellRequest $request): TellRequest {
        $this->assertSelection($request);

        return $this->configuration->resolve($request)->request;
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamAutomatic(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        $workspace = $this->workspaces->open($request->directory);
        if ($workspace === null) {
            if ($request->branch !== null) {
                throw new RuntimeException('Tell branch selection requires an initialized workspace. Call tell init or initialize the workspace first.');
            }
            if ($request->session !== null) {
                throw new RuntimeException('Tell named sessions require an initialized workspace. Call tell init or initialize the workspace first.');
            }

            return $this->streamStateless($request, $prepareLoop, $diagnostics, $outcome);
        }
        if ($request->session === null) {
            return $this->streamWorkspaceTurn(
                $request,
                $workspace,
                $prepareLoop,
                $workspace->branch($request->branch),
                $diagnostics,
                $outcome,
            );
        }

        return $this->streamWorkspaceSession($request, $workspace, $prepareLoop, $diagnostics, $outcome);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamStateless(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, diagnostics: $diagnostics);
        $state = $this->seed($definition, $request);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            $s,
            warnings: $diagnostics->warnings(),
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = $loop->iterate($state);
        $finalState = $state;
        foreach ($states as $checkpoint) {
            $finalState = $checkpoint;
            $this->recordTerminal($outcome, $checkpoint);
            yield new TellProgress($checkpoint);
        }

        return $this->finish($outcome, $finalState, $build);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamDurable(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        $workspace = $this->workspaces->open($request->directory);
        if ($workspace === null) {
            throw new RuntimeException('Tell durable execution requires an initialized workspace. Call tell init or initialize the workspace first.');
        }

        return match ($request->session) {
            null => $this->streamWorkspaceTurn($request, $workspace, $prepareLoop, $workspace->branch($request->branch), $diagnostics, $outcome),
            default => $this->streamWorkspaceSession($request, $workspace, $prepareLoop, $diagnostics, $outcome),
        };
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamTransient(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        $workspace = $this->workspaces->open($request->directory);
        if ($workspace === null) {
            [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, diagnostics: $diagnostics);
            $state = $this->seed($definition, $request);
            $build = static fn (AgentState $s): TellResult => new TellResult(
                $s,
                warnings: $diagnostics->warnings(),
                transient: true,
                diagnostics: $diagnostics->all(),
            );
            $outcome?->useBuilder($build);
            $states = $loop->iterate($state);
            $finalState = $state;
            foreach ($states as $checkpoint) {
                $finalState = $checkpoint;
                $this->recordTerminal($outcome, $checkpoint);
                yield new TellProgress($checkpoint);
            }

            return $this->finish($outcome, $finalState, $build);
        }

        $session = $request->session === null ? null : SessionId::from($request->session);
        $branch = $session === null ? $workspace->branch($request->branch) : null;
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch, $diagnostics);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            state: $s,
            warnings: $diagnostics->warnings(),
            transient: true,
            session: $request->session,
            workspace: $workspace->root(),
            branch: $branch?->name,
            branchSource: $branch?->source,
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = $workspace->transient($session, $branch, $loop, $definition, $request->prompt);
        foreach ($states as $state) {
            $this->recordTerminal($outcome, $state);
            yield new TellProgress($state);
        }

        return $this->finish($outcome, $states->getReturn(), $build);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWorkspaceTurn(
        TellRequest $request,
        CanUseTellExecutionWorkspace $workspace,
        ?callable $prepareLoop,
        TellBranchSelection $branch,
        TellDiagnostics $diagnostics,
        ?TellRunOutcome $outcome = null,
    ): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch, $diagnostics);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            state: $s,
            warnings: $diagnostics->warnings(),
            durable: true,
            workspace: $workspace->root(),
            branch: $branch->name,
            branchSource: $branch->source,
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = $workspace->turn($branch, $loop, $definition, $request->prompt);
        foreach ($states as $state) {
            $this->recordTerminal($outcome, $state);
            yield new TellProgress($state);
        }

        return $this->finish($outcome, $states->getReturn(), $build);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWorkspaceSession(
        TellRequest $request,
        CanUseTellExecutionWorkspace $workspace,
        ?callable $prepareLoop,
        TellDiagnostics $diagnostics,
        ?TellRunOutcome $outcome = null,
    ): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, diagnostics: $diagnostics);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            state: $s,
            warnings: [...($outcome?->warnings() ?? []), ...$diagnostics->warnings()],
            durable: true,
            session: $request->session,
            workspace: $workspace->root(),
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = $workspace->session(SessionId::from($request->session ?? ''), $loop, $definition, $request->prompt);
        foreach ($states as $state) {
            $this->recordTerminal($outcome, $state);
            yield new TellProgress($state);
        }

        return $this->finish($outcome, $states->getReturn(), $build);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function loop(
        TellRequest $request,
        AgentDefinition $definition,
        ?callable $prepareLoop,
        ?CanUseTellExecutionWorkspace $workspace = null,
        ?TellBranchSelection $selectedBranch = null,
        ?TellDiagnostics $diagnostics = null,
    ): AgentLoop {
        $delegation = match ($workspace) {
            null => null,
            default => $workspace->delegation(
                $selectedBranch ?? $workspace->branch(null),
                $this->cancellation,
            ),
        };
        $loop = $this->agents->build($request, $this->cancellation, $definition, $delegation, $diagnostics);
        $this->tracer->attach($loop, $request);
        $branchName = match ($selectedBranch) {
            null => $request->branch,
            default => $selectedBranch->name,
        };
        if ($this->observer !== null) {
            $observer = $this->observer;
            $normalized = new TellEventNormalizer($branchName, $request->session);
            $loop->wiretap(static function (object $event) use ($observer, $normalized, $request): void {
                $observer->observe(TellEventEnvelope::fromNormalized(
                    $normalized->normalize($event),
                    $request->mode,
                    $request->agent,
                ));
            });
        }
        foreach ($request->listeners() as $listener) {
            $normalizer = new TellEventNormalizer($branchName, $request->session);
            $loop->wiretap(static function (object $event) use ($listener, $normalizer, $request): void {
                $listener(TellEventEnvelope::fromNormalized(
                    $normalizer->normalize($event),
                    $request->mode,
                    $request->agent,
                ));
            });
        }
        if ($prepareLoop !== null) {
            $prepareLoop($loop, $request, $selectedBranch?->name);
        }

        return $loop;
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return array{AgentDefinition, AgentLoop}
     */
    private function definitionAndLoop(
        TellRequest $request,
        ?callable $prepareLoop,
        ?CanUseTellExecutionWorkspace $workspace = null,
        ?TellBranchSelection $selectedBranch = null,
        ?TellDiagnostics $diagnostics = null,
    ): array {
        $definition = $this->agents->definition($request);

        return [$definition, $this->loop($request, $definition, $prepareLoop, $workspace, $selectedBranch, $diagnostics)];
    }

    private function seed(AgentDefinition $definition, TellRequest $request): AgentState {
        return (new DefinitionStateFactory())
            ->instantiateAgentState($definition)
            ->withUserMessage($request->prompt);
    }

    private function assertSelection(TellRequest $request): void {
        if ($request->branch !== null && $request->session !== null) {
            throw new InvalidArgumentException('--branch and --session cannot be used together.');
        }
    }

}
