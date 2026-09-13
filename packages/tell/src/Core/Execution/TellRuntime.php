<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Execution;

use Closure;
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Observation\CanJournalTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanRecordTellJournal;
use Cognesy\Tell\Core\Contract\Observation\CanRecordTellTrace;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellExecutionWorkspace;
use Cognesy\Tell\Core\Observation\TellExecutionEventStream;
use Cognesy\Tell\Core\Workspace\Execution\TurnException;
use Cognesy\Tell\Data\TellBranchSelection;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceStatus;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final readonly class TellRuntime
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanOpenTellExecutionWorkspace $workspaces,
        private CanTraceTellExecution $tracer,
        private CanJournalTellExecution $journal,
        private CanResolveTellConfiguration $configuration,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanObserveTellExecution $observer = null,
    ) {}

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    public function run(TellRequest $request, ?callable $prepareLoop = null): TellResult {
        return $this->start($request, $prepareLoop)->wait();
    }

    /**
     * @param  callable(AgentLoop, TellRequest, ?string): void|null  $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
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
    public function start(TellRequest $request, ?callable $prepareLoop = null): TellRun {
        $this->assertSelection($request);
        $request = $this->configuration->resolve($request)->request;
        $diagnostics = new TellDiagnostics();
        $outcome = new TellRunOutcome();

        $stream = match ($request->mode) {
            TellExecutionMode::Automatic => $this->streamAutomatic($request, $prepareLoop, $diagnostics, $outcome),
            TellExecutionMode::Stateless => $this->streamWithoutWorkspace(
                $request,
                $prepareLoop,
                TellExecutionMode::Stateless,
                $diagnostics,
                $outcome,
            ),
            TellExecutionMode::Durable => $this->streamDurable($request, $prepareLoop, $diagnostics, $outcome),
            TellExecutionMode::Transient => $this->streamTransient($request, $prepareLoop, $diagnostics, $outcome),
        };

        return new TellRun($stream, $outcome, $diagnostics);
    }

    /**
     * Settles a terminal checkpoint in the outcome the moment it appears, so
     * the run's result never depends on the caller advancing past the final
     * yield. Durable runners separately record successful publication.
     */
    private function recordTerminal(
        TellRunOutcome $outcome,
        AgentState $state,
        CanRecordTellTrace $trace,
        CanRecordTellJournal $journal,
        TellExecutionMode $requestedMode,
        TellDiagnostics $diagnostics,
        TellExecutionEventStream $events,
    ): void {
        if (!in_array($state->status(), [
            ExecutionStatus::Completed,
            ExecutionStatus::Stopped,
            ExecutionStatus::Failed,
        ], true)) {
            return;
        }
        $outcome->recordSettled($state);
        $termination = TellTermination::fromState($state);
        $trace->recordOutcome(
            $termination,
            $outcome->publication(),
            $requestedMode,
        );
        if ($trace->reference()->status === TellTraceStatus::Failed) {
            $diagnostics->recordTraceWriteFailure();
        }
        $journal->recordOutcome(
            $termination,
            $outcome->publication(),
            $trace->reference(),
            $requestedMode,
        );
        $events->settle($termination, $outcome->publication());
        if ($state->status() === ExecutionStatus::Completed && !$state->hasFinalResponse()) {
            throw new TurnException(
                'Tell execution completed without a final response.',
                termination: $termination,
                publication: $outcome->publication(),
                trace: $trace->reference(),
            );
        }
    }

    /**
     * Settles a fully drained run: whatever the outcome already holds wins, so a
     * settled state is never overwritten by a recomputed one.
     */
    private function finish(
        TellRunOutcome $outcome,
        AgentState $state,
    ): TellResult {
        $outcome->recordSettled($state);

        return $outcome->result() ?? throw new RuntimeException(
            'Tell run reached its final state without a result builder.',
        );
    }

    /** @return Closure(AgentState, TellPublication): TellResult */
    private function resultBuilder(
        TellRequest $request,
        TellExecutionMode $mode,
        CanRecordTellTrace $trace,
        TellDiagnostics $diagnostics,
    ): Closure {
        return static fn (AgentState $state, TellPublication $publication): TellResult => new TellResult(
            state: $state,
            requestedMode: $request->mode,
            mode: $mode,
            publication: $publication,
            trace: $trace->reference(),
            warnings: $diagnostics->warnings(),
            diagnostics: $diagnostics->all(),
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamAutomatic(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, TellRunOutcome $outcome): Generator {
        $workspace = $this->workspaces->open($request->directory);
        if ($workspace === null) {
            if ($request->branch !== null) {
                throw new RuntimeException('Tell branch selection requires an initialized workspace. Call tell init or initialize the workspace first.');
            }
            if ($request->session !== null) {
                throw new RuntimeException('Tell named sessions require an initialized workspace. Call tell init or initialize the workspace first.');
            }

            return $this->streamWithoutWorkspace(
                $request,
                $prepareLoop,
                TellExecutionMode::Stateless,
                $diagnostics,
                $outcome,
            );
        }
        return $this->streamWorkspace($request, $workspace, $prepareLoop, $diagnostics, $outcome);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWithoutWorkspace(
        TellRequest $request,
        ?callable $prepareLoop,
        TellExecutionMode $mode,
        TellDiagnostics $diagnostics,
        TellRunOutcome $outcome,
    ): Generator {
        [$definition, $loop, $trace, $journal, $events] = $this->definitionAndLoop($request, $prepareLoop, diagnostics: $diagnostics);
        $state = $this->seed($definition, $request);
        $publication = TellPublication::notApplicable();
        $outcome->useBuilder(
            $this->resultBuilder($request, $mode, $trace, $diagnostics),
            $publication,
        );
        $states = $loop->iterate($state);
        $finalState = $state;
        foreach ($states as $checkpoint) {
            $finalState = $checkpoint;
            $this->recordTerminal($outcome, $checkpoint, $trace, $journal, $request->mode, $diagnostics, $events);
            yield new TellProgress($checkpoint);
        }

        return $this->finish($outcome, $finalState);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamDurable(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, TellRunOutcome $outcome): Generator {
        $workspace = $this->workspaces->open($request->directory);
        if ($workspace === null) {
            throw new RuntimeException('Tell durable execution requires an initialized workspace. Call tell init or initialize the workspace first.');
        }

        return $this->streamWorkspace($request, $workspace, $prepareLoop, $diagnostics, $outcome);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamTransient(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, TellRunOutcome $outcome): Generator {
        $workspace = $this->workspaces->open($request->directory);
        if ($workspace === null) {
            return yield from $this->streamWithoutWorkspace(
                $request,
                $prepareLoop,
                TellExecutionMode::Transient,
                $diagnostics,
                $outcome,
            );
        }

        $session = $request->session === null ? null : SessionId::from($request->session);
        $branch = $session === null ? $workspace->branch($request->branch) : null;
        [$definition, $loop, $trace, $journal, $events] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch, $diagnostics);
        $publication = TellPublication::notApplicable(
            workspace: $workspace->root(),
            branch: $branch?->name,
            session: $request->session,
            branchSource: $branch?->source,
        );
        $outcome->useBuilder(
            $this->resultBuilder($request, TellExecutionMode::Transient, $trace, $diagnostics),
            $publication,
        );
        $states = $workspace->transient($session, $branch, $loop, $definition, $request->prompt);
        foreach ($states as $state) {
            $this->recordTerminal($outcome, $state, $trace, $journal, $request->mode, $diagnostics, $events);
            yield new TellProgress($state);
        }

        return $this->finish($outcome, $states->getReturn());
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWorkspace(
        TellRequest $request,
        CanUseTellExecutionWorkspace $workspace,
        ?callable $prepareLoop,
        TellDiagnostics $diagnostics,
        TellRunOutcome $outcome,
    ): Generator {
        $session = match ($request->session) {
            null => null,
            default => SessionId::from($request->session),
        };
        $branch = match ($session) {
            null => $workspace->branch($request->branch),
            default => null,
        };
        [$definition, $loop, $trace, $journal, $events] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch, $diagnostics);
        $publication = TellPublication::notAttempted(
            workspace: $workspace->root(),
            branch: $branch?->name,
            session: $request->session,
            branchSource: $branch?->source,
        );
        $outcome->useBuilder(
            $this->resultBuilder($request, TellExecutionMode::Durable, $trace, $diagnostics),
            $publication,
        );
        $states = match ($session) {
            null => $workspace->turn(
                $branch ?? throw new RuntimeException('Tell workspace branch selection was not resolved.'),
                $loop,
                $definition,
                $request->prompt,
                $outcome,
            ),
            default => $workspace->session($session, $loop, $definition, $request->prompt, $outcome),
        };
        try {
            foreach ($states as $state) {
                $this->recordTerminal($outcome, $state, $trace, $journal, $request->mode, $diagnostics, $events);
                yield new TellProgress($state);
            }
        } catch (TurnException $exception) {
            if ($exception->termination() !== null && $exception->publication() !== null) {
                $trace->recordOutcome($exception->termination(), $exception->publication(), $request->mode);
                if ($trace->reference()->status === TellTraceStatus::Failed) {
                    $diagnostics->recordTraceWriteFailure();
                }
                $journal->recordOutcome(
                    $exception->termination(),
                    $exception->publication(),
                    $trace->reference(),
                    $request->mode,
                );
                $events->settle($exception->termination(), $exception->publication());
            }
            throw $exception->withTrace($trace->reference());
        }

        return $this->finish($outcome, $states->getReturn());
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function loop(
        TellRequest $request,
        AgentDefinition $definition,
        ?callable $prepareLoop,
        ?CanUseTellExecutionWorkspace $workspace = null,
        ?TellBranchSelection $selectedBranch = null,
        ?TellDiagnostics $diagnostics = null,
    ): array {
        $delegation = match ($workspace) {
            null => null,
            default => $workspace->delegation(
                $selectedBranch ?? $workspace->branch(null),
                $this->cancellation,
            ),
        };
        $loop = $this->agents->build($request, $this->cancellation, $definition, $delegation, $diagnostics);
        $journal = $this->journal->attach($loop, $request);
        $trace = $this->tracer->attach($loop, $request);
        $branchName = match ($selectedBranch) {
            null => $request->branch,
            default => $selectedBranch->name,
        };
        $events = new TellExecutionEventStream(
            mode: $request->mode,
            agent: $request->agent,
            observer: $this->observer,
            listeners: $request->listeners(),
            branch: $branchName,
            session: $request->session,
        );
        $events->attach($loop);
        if ($prepareLoop !== null) {
            $prepareLoop($loop, $request, $selectedBranch?->name);
        }

        return [$loop, $trace, $journal, $events];
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return array{AgentDefinition, AgentLoop, CanRecordTellTrace, CanRecordTellJournal, TellExecutionEventStream}
     */
    private function definitionAndLoop(
        TellRequest $request,
        ?callable $prepareLoop,
        ?CanUseTellExecutionWorkspace $workspace = null,
        ?TellBranchSelection $selectedBranch = null,
        ?TellDiagnostics $diagnostics = null,
    ): array {
        $definition = $this->agents->definition($request);

        [$loop, $trace, $journal, $events] = $this->loop($request, $definition, $prepareLoop, $workspace, $selectedBranch, $diagnostics);

        return [$definition, $loop, $trace, $journal, $events];
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
