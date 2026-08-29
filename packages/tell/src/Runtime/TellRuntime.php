<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Configuration\TellExecutionPolicy;
use Cognesy\Tell\Configuration\TellPolicyDefaults;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\Execution\TransientRunner;
use Cognesy\Tell\Workspace\Execution\TurnRunner;
use Cognesy\Tell\Workspace\Session\SessionRunner;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final readonly class TellRuntime
{
    public function __construct(
        private TellAgentFactory $agents,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanResolveTellConfiguration $configuration = null,
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
        $request = $this->configuration?->resolve($request)->request ?? $this->withBranchConfig($request);
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
    public function resolveDirectOptions(TellOptions $options): TellOptions {
        $request = TellRequest::fromOptions($options);
        $this->assertSelection($request);

        return ($this->configuration?->resolve($request)->request ?? $this->withBranchConfig($request))->toOptions();
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamAutomatic(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        $workspace = $this->agents->workspace()->discover($request->directory);
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
            $arena = new FilesystemArena($workspace);

            return $this->streamWorkspaceTurn($request, $arena, $workspace->paths->root, $prepareLoop, (new BranchResolver($arena, $workspace))->resolve($request->branch), $diagnostics, $outcome);
        }

        return $this->streamWorkspaceSession($request, new FilesystemArena($workspace), $workspace->paths->root, $prepareLoop, $diagnostics, $outcome);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamStateless(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, diagnostics: $diagnostics);
        $state = $this->seed($definition, $request);
        $build = static fn (AgentState $s): TellResult => new TellResult($s, diagnostics: $diagnostics->all());
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
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($workspace === null) {
            throw new RuntimeException('Tell durable execution requires an initialized workspace. Call tell init or initialize the workspace first.');
        }

        return match ($request->session) {
            null => $this->streamWorkspaceTurn($request, new FilesystemArena($workspace), $workspace->paths->root, $prepareLoop, (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve($request->branch), $diagnostics, $outcome),
            default => $this->streamWorkspaceSession($request, new FilesystemArena($workspace), $workspace->paths->root, $prepareLoop, $diagnostics, $outcome),
        };
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamTransient(TellRequest $request, ?callable $prepareLoop, TellDiagnostics $diagnostics, ?TellRunOutcome $outcome = null): Generator {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($workspace === null) {
            [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, diagnostics: $diagnostics);
            $state = $this->seed($definition, $request);
            $build = static fn (AgentState $s): TellResult => new TellResult($s, transient: true, diagnostics: $diagnostics->all());
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
        $branch = $session === null ? (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve($request->branch) : null;
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace->paths->root, $branch?->branch, $diagnostics);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            state: $s,
            transient: true,
            session: $request->session,
            workspace: $workspace->paths->root,
            branch: $branch?->branch,
            branchSource: $branch === null ? null : ($branch->invocationLocal ? 'invocation' : 'current'),
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = (new TransientRunner(
            arena: new FilesystemArena($workspace),
            ref: $session === null ? $branch->ref : 'main',
        ))->iterate($session, $loop, $definition, $request->prompt);
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
        FilesystemArena $arena,
        string $workspace,
        ?callable $prepareLoop,
        ResolvedBranch $branch,
        TellDiagnostics $diagnostics,
        ?TellRunOutcome $outcome = null,
    ): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch->branch, $diagnostics);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            state: $s,
            durable: true,
            workspace: $workspace,
            branch: $branch->branch,
            branchSource: $branch->invocationLocal ? 'invocation' : 'current',
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = (new TurnRunner($arena, ref: $branch->ref))->iterate($loop, $definition, $request->prompt, $outcome);
        foreach ($states as $state) {
            yield new TellProgress($state);
        }

        return $this->finish($outcome, $states->getReturn(), $build);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWorkspaceSession(
        TellRequest $request,
        FilesystemArena $arena,
        string $workspace,
        ?callable $prepareLoop,
        TellDiagnostics $diagnostics,
        ?TellRunOutcome $outcome = null,
    ): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, diagnostics: $diagnostics);
        $build = static fn (AgentState $s): TellResult => new TellResult(
            state: $s,
            warnings: $outcome?->warnings() ?? [],
            durable: true,
            session: $request->session,
            workspace: $workspace,
            diagnostics: $diagnostics->all(),
        );
        $outcome?->useBuilder($build);
        $states = (new SessionRunner(
            arena: $arena,
        ))->iterate(SessionId::from($request->session ?? ''), $loop, $definition, $request->prompt, $outcome);
        foreach ($states as $state) {
            yield new TellProgress($state);
        }

        return $this->finish($outcome, $states->getReturn(), $build);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function loop(
        TellRequest $request,
        AgentDefinition $definition,
        ?callable $prepareLoop,
        ?string $workspace = null,
        ?string $selectedBranch = null,
        ?TellDiagnostics $diagnostics = null,
    ): AgentLoop {
        $delegation = match ($workspace) {
            null => null,
            default => new TellDelegationScope(
                $this->agents->workspace()->discover($workspace) ?? throw new RuntimeException('Tell delegation requires a valid workspace.'),
                new ResolvedBranch($selectedBranch ?? 'main', $selectedBranch === null || $selectedBranch === 'main' ? 'main' : 'branches/' . $selectedBranch, false),
                cancellation: $this->cancellation,
            ),
        };
        $loop = $this->agents->build($request->toOptions(), $definition, $this->cancellation, $delegation, $diagnostics);
        $this->agents->attachExecutionTrace($loop, $request->toOptions());
        if ($this->observer !== null) {
            $observer = $this->observer;
            $normalized = new TellEventNormalizer($selectedBranch ?? $request->branch, $request->session);
            $loop->wiretap(static function (object $event) use ($observer, $normalized, $request): void {
                $observer->observe(TellEventEnvelope::fromNormalized(
                    $normalized->normalize($event),
                    $request->mode,
                    $request->agent,
                ));
            });
        }
        foreach ($request->listeners() as $listener) {
            $normalizer = new TellEventNormalizer($selectedBranch ?? $request->branch, $request->session);
            $loop->wiretap(static function (object $event) use ($listener, $normalizer, $request): void {
                $listener(TellEventEnvelope::fromNormalized(
                    $normalizer->normalize($event),
                    $request->mode,
                    $request->agent,
                ));
            });
        }
        if ($prepareLoop !== null) {
            $prepareLoop($loop, $request, $selectedBranch);
        }

        return $loop;
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return array{AgentDefinition, AgentLoop}
     */
    private function definitionAndLoop(
        TellRequest $request,
        ?callable $prepareLoop,
        ?string $workspace = null,
        ?string $selectedBranch = null,
        ?TellDiagnostics $diagnostics = null,
    ): array {
        $definition = $this->agents->definition($request->toOptions());

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

    private function withBranchConfig(TellRequest $request): TellRequest {
        $workspace = $this->agents->workspace()->discover($request->directory);
        $userDefaults = $this->userPolicyDefaults();
        $projectDefaults = $workspace === null
            ? []
            : TellPolicyDefaults::fromFile($workspace->paths->config . '/defaults.json');
        if ($request->session !== null) {
            return $request->withPolicy(TellExecutionPolicy::resolve(
                branchValues: [],
                cliOverrides: $request->policyOverrides,
                projectDefaults: $projectDefaults,
                userDefaults: $userDefaults,
            ));
        }
        if ($workspace === null) {
            return $request->withPolicy(TellExecutionPolicy::resolve(
                branchValues: [],
                cliOverrides: $request->policyOverrides,
                userDefaults: $userDefaults,
            ));
        }
        $selection = (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve($request->branch);
        $branchValues = (new BranchConfigStore($workspace))->runtimeValues($selection->branch);

        return $request
            ->withBranchConfig($branchValues)
            ->withPolicy(TellExecutionPolicy::resolve(
                branchValues: $branchValues,
                cliOverrides: $request->policyOverrides,
                projectDefaults: $projectDefaults,
                userDefaults: $userDefaults,
            ));
    }

    /** @return array<string, int> */
    private function userPolicyDefaults(): array {
        return TellPolicyDefaults::fromFile($this->agents->paths()->configDirectory . '/execution-defaults.json');
    }
}
