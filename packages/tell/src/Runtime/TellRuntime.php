<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\TellExecutionMode;
use Cognesy\Tell\TellEvent;
use Cognesy\Tell\TellProgress;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\TellResult;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchResolver;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\WorkspaceSessionExecution;
use Cognesy\Tell\Workspace\WorkspaceSessionRunner;
use Cognesy\Tell\Workspace\WorkspaceTransientRunner;
use Cognesy\Tell\Workspace\WorkspaceTurnRunner;
use Closure;
use Generator;
use RuntimeException;

final readonly class TellRuntime
{
    public function __construct(
        private TellAgentFactory $agents,
        private ?CanProvideCancellationSignal $cancellation = null,
    ) {}

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    public function run(TellRequest $request, ?callable $prepareLoop = null): TellResult
    {
        $this->assertSelection($request);
        $request = $this->withBranchConfig($request);
        $options = $request->toOptions();
        $this->agents->assertReady($options);

        return match ($request->mode) {
            TellExecutionMode::Automatic => $this->runAutomatic($request, $prepareLoop),
            TellExecutionMode::Stateless => $this->runStateless($request, $prepareLoop),
            TellExecutionMode::Durable => $this->runDurable($request, $prepareLoop),
            TellExecutionMode::Transient => $this->runTransient($request, $prepareLoop),
        };
    }

    /**
     * @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    public function stream(TellRequest $request, ?callable $prepareLoop = null): Generator
    {
        $this->assertSelection($request);
        $request = $this->withBranchConfig($request);
        $options = $request->toOptions();
        $this->agents->assertReady($options);

        return match ($request->mode) {
            TellExecutionMode::Automatic => $this->streamAutomatic($request, $prepareLoop),
            TellExecutionMode::Stateless => $this->streamStateless($request, $prepareLoop),
            TellExecutionMode::Durable => $this->streamDurable($request, $prepareLoop),
            TellExecutionMode::Transient => $this->streamTransient($request, $prepareLoop),
        };
    }

    /**
     * Resolves the same branch and policy inputs as a turn without creating a
     * state, attaching traces, or publishing an arena ref. Direct tool calls
     * deliberately use this boundary instead of run().
     */
    public function resolveDirectOptions(TellOptions $options): TellOptions
    {
        $request = TellRequest::fromOptions($options);
        $this->assertSelection($request);

        return $this->withBranchConfig($request)->toOptions();
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runAutomatic(TellRequest $request, ?callable $prepareLoop): TellResult
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($request->session === null && $workspace === null) {
            if ($request->branch !== null) {
                throw new RuntimeException('Tell branch selection requires an initialized workspace. Call tell init or initialize the workspace first.');
            }
            return $this->runStateless($request, $prepareLoop);
        }
        if ($request->session === null) {
            $arena = new ArenaStore($workspace);
            return $this->runWorkspaceTurn($request, $arena, $workspace->paths->root, $prepareLoop, (new BranchResolver($arena))->resolve($request->branch));
        }
        if ($workspace !== null) {
            return $this->runWorkspaceSession($request, new ArenaStore($workspace), $workspace->paths->root, $prepareLoop);
        }

        return $this->runLegacySession($request, $prepareLoop);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamAutomatic(TellRequest $request, ?callable $prepareLoop): Generator
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($request->session === null && $workspace === null) {
            if ($request->branch !== null) {
                throw new RuntimeException('Tell branch selection requires an initialized workspace. Call tell init or initialize the workspace first.');
            }
            return $this->streamStateless($request, $prepareLoop);
        }
        if ($request->session === null) {
            $arena = new ArenaStore($workspace);
            return $this->streamWorkspaceTurn($request, $arena, $workspace->paths->root, $prepareLoop, (new BranchResolver($arena))->resolve($request->branch));
        }
        if ($workspace !== null) {
            return $this->streamWorkspaceSession($request, new ArenaStore($workspace), $workspace->paths->root, $prepareLoop);
        }

        return $this->streamLegacySession($request, $prepareLoop);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runStateless(TellRequest $request, ?callable $prepareLoop): TellResult
    {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop);
        $state = $loop->execute($this->seed($definition, $request));

        return new TellResult($state);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamStateless(TellRequest $request, ?callable $prepareLoop): Generator
    {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop);
        $state = $this->seed($definition, $request);
        $states = $loop->iterate($state);
        $finalState = $state;
        foreach ($states as $checkpoint) {
            $finalState = $checkpoint;
            yield new TellProgress($checkpoint);
        }

        return new TellResult($finalState);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runDurable(TellRequest $request, ?callable $prepareLoop): TellResult
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($workspace === null) {
            throw new RuntimeException('Tell durable execution requires an initialized workspace. Call tell init or initialize the workspace first.');
        }

        return match ($request->session) {
            null => $this->runWorkspaceTurn($request, new ArenaStore($workspace), $workspace->paths->root, $prepareLoop, (new BranchResolver(new ArenaStore($workspace)))->resolve($request->branch)),
            default => $this->runWorkspaceSession($request, new ArenaStore($workspace), $workspace->paths->root, $prepareLoop),
        };
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamDurable(TellRequest $request, ?callable $prepareLoop): Generator
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($workspace === null) {
            throw new RuntimeException('Tell durable execution requires an initialized workspace. Call tell init or initialize the workspace first.');
        }

        return match ($request->session) {
            null => $this->streamWorkspaceTurn($request, new ArenaStore($workspace), $workspace->paths->root, $prepareLoop, (new BranchResolver(new ArenaStore($workspace)))->resolve($request->branch)),
            default => $this->streamWorkspaceSession($request, new ArenaStore($workspace), $workspace->paths->root, $prepareLoop),
        };
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runTransient(TellRequest $request, ?callable $prepareLoop): TellResult
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($workspace === null) {
            [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop);
            return new TellResult($loop->execute($this->seed($definition, $request)), transient: true);
        }

        $session = $request->session === null ? null : SessionId::from($request->session);
        $branch = $session === null ? (new BranchResolver(new ArenaStore($workspace)))->resolve($request->branch) : null;
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace->paths->root, $branch?->branch);
        $state = (new WorkspaceTransientRunner(
            arena: new ArenaStore($workspace),
            paths: $this->agents->paths(),
            ref: $session === null ? $branch->ref : 'main',
        ))->execute($session, $loop, $definition, $request->prompt);

        return new TellResult(
            state: $state,
            transient: true,
            session: $request->session,
            workspace: $workspace->paths->root,
            branch: $branch?->branch,
            branchSource: $branch === null ? null : ($branch->invocationLocal ? 'invocation' : 'current'),
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamTransient(TellRequest $request, ?callable $prepareLoop): Generator
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        if ($workspace === null) {
            [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop);
            $state = $this->seed($definition, $request);
            $states = $loop->iterate($state);
            $finalState = $state;
            foreach ($states as $checkpoint) {
                $finalState = $checkpoint;
                yield new TellProgress($checkpoint);
            }

            return new TellResult($finalState, transient: true);
        }

        $session = $request->session === null ? null : SessionId::from($request->session);
        $branch = $session === null ? (new BranchResolver(new ArenaStore($workspace)))->resolve($request->branch) : null;
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace->paths->root, $branch?->branch);
        $states = (new WorkspaceTransientRunner(
            arena: new ArenaStore($workspace),
            paths: $this->agents->paths(),
            ref: $session === null ? $branch->ref : 'main',
        ))->iterate($session, $loop, $definition, $request->prompt);
        foreach ($states as $state) {
            yield new TellProgress($state);
        }

        return new TellResult(
            state: $states->getReturn(),
            transient: true,
            session: $request->session,
            workspace: $workspace->paths->root,
            branch: $branch?->branch,
            branchSource: $branch === null ? null : ($branch->invocationLocal ? 'invocation' : 'current'),
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runWorkspaceTurn(
        TellRequest $request,
        ArenaStore $arena,
        string $workspace,
        ?callable $prepareLoop,
        \Cognesy\Tell\Workspace\BranchSelection $branch,
    ): TellResult
    {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch->branch);
        $state = (new WorkspaceTurnRunner($arena, ref: $branch->ref))->execute($loop, $definition, $request->prompt);

        return new TellResult(
            state: $state,
            durable: true,
            workspace: $workspace,
            branch: $branch->branch,
            branchSource: $branch->invocationLocal ? 'invocation' : 'current',
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWorkspaceTurn(
        TellRequest $request,
        ArenaStore $arena,
        string $workspace,
        ?callable $prepareLoop,
        \Cognesy\Tell\Workspace\BranchSelection $branch,
    ): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace, $branch->branch);
        $states = (new WorkspaceTurnRunner($arena, ref: $branch->ref))->iterate($loop, $definition, $request->prompt);
        foreach ($states as $state) {
            yield new TellProgress($state);
        }

        return new TellResult(
            state: $states->getReturn(),
            durable: true,
            workspace: $workspace,
            branch: $branch->branch,
            branchSource: $branch->invocationLocal ? 'invocation' : 'current',
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runWorkspaceSession(
        TellRequest $request,
        ArenaStore $arena,
        string $workspace,
        ?callable $prepareLoop,
    ): TellResult
    {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace);
        $execution = (new WorkspaceSessionRunner(
            arena: $arena,
            paths: $this->agents->paths(),
        ))->execute(SessionId::from($request->session ?? ''), $loop, $definition, $request->prompt);

        return $this->resultFromExecution(
            execution: $execution,
            durable: true,
            session: $request->session,
            workspace: $workspace,
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamWorkspaceSession(
        TellRequest $request,
        ArenaStore $arena,
        string $workspace,
        ?callable $prepareLoop,
    ): Generator {
        [$definition, $loop] = $this->definitionAndLoop($request, $prepareLoop, $workspace);
        $states = (new WorkspaceSessionRunner(
            arena: $arena,
            paths: $this->agents->paths(),
        ))->iterate(SessionId::from($request->session ?? ''), $loop, $definition, $request->prompt);
        foreach ($states as $state) {
            yield new TellProgress($state);
        }

        return $this->resultFromExecution(
            execution: $states->getReturn(),
            durable: true,
            session: $request->session,
            workspace: $workspace,
        );
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function runLegacySession(TellRequest $request, ?callable $prepareLoop): TellResult
    {
        $sessionId = SessionId::from($request->session ?? '');
        $repository = $this->agents->sessionRepository();
        if (! $repository->exists($sessionId)) {
            $definition = $this->agents->definition($request->toOptions());
            $repository->create(new AgentSession(
                header: AgentSessionInfo::fresh($sessionId, $definition->name, $definition->label()),
                definition: $definition,
                state: (new DefinitionStateFactory)->instantiateAgentState($definition),
            ));
        }

        $factory = new class($this->agents, $request, $prepareLoop, $this->cancellation) implements CanInstantiateAgentLoop
        {
            /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
            public function __construct(
                private TellAgentFactory $agents,
                private TellRequest $request,
                ?callable $prepareLoop,
                private ?CanProvideCancellationSignal $cancellation,
            ) {
                $this->prepareLoop = match ($prepareLoop) {
                    null => null,
                    default => Closure::fromCallable($prepareLoop),
                };
            }

            /** @var Closure(AgentLoop, TellRequest, ?string): void|null */
            private ?Closure $prepareLoop;

            #[\Override]
            public function instantiateAgentLoop(AgentDefinition $definition): CanControlAgentLoop
            {
                $options = $this->request->toOptions();
                $loop = $this->agents->build($options, $definition, $this->cancellation);
                $this->agents->attachExecutionTrace($loop, $options);
                $normalizer = new TellEventNormalizer($this->request->branch, $this->request->session);
                foreach ($this->request->listeners() as $listener) {
                    $loop->wiretap(function (object $event) use ($listener, $normalizer): void {
                        $envelope = $normalizer->normalize($event);
                        $envelope['mode'] = $this->request->mode->value;
                        $envelope['agent'] = $this->request->agent;
                        $listener(new TellEvent($envelope, $event));
                    });
                }
                if ($this->prepareLoop !== null) {
                    ($this->prepareLoop)($loop, $this->request, null);
                }

                return $loop;
            }
        };

        $session = $this->agents->sessions()->execute($sessionId, new SendMessage($request->prompt, $factory));

        return new TellResult($session->state(), durable: true, session: $request->session);
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    private function streamLegacySession(TellRequest $request, ?callable $prepareLoop): Generator
    {
        $result = $this->runLegacySession($request, $prepareLoop);
        yield new TellProgress($result->state());

        return $result;
    }

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    private function loop(
        TellRequest $request,
        AgentDefinition $definition,
        ?callable $prepareLoop,
        ?string $workspace = null,
        ?string $selectedBranch = null,
    ): AgentLoop
    {
        $delegation = match ($workspace) {
            null => null,
            default => new TellDelegationScope(
                $this->agents->workspace()->discover($workspace) ?? throw new RuntimeException('Tell delegation requires a valid workspace.'),
                new \Cognesy\Tell\Workspace\BranchSelection($selectedBranch ?? 'main', $selectedBranch === null || $selectedBranch === 'main' ? 'main' : 'branches/'.$selectedBranch, false),
                cancellation: $this->cancellation,
            ),
        };
        $loop = $this->agents->build($request->toOptions(), $definition, $this->cancellation, $delegation);
        $this->agents->attachExecutionTrace($loop, $request->toOptions());
        foreach ($request->listeners() as $listener) {
            $normalizer = new TellEventNormalizer($selectedBranch ?? $request->branch, $request->session);
            $loop->wiretap(static function (object $event) use ($listener, $normalizer, $request, $workspace): void {
                $envelope = $normalizer->normalize($event);
                $envelope['mode'] = $request->mode->value;
                $envelope['agent'] = $request->agent;
                $listener(new TellEvent($envelope, $event, $workspace));
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
    ): array
    {
        $definition = $this->agents->definition($request->toOptions());

        return [$definition, $this->loop($request, $definition, $prepareLoop, $workspace, $selectedBranch)];
    }

    private function seed(AgentDefinition $definition, TellRequest $request): \Cognesy\Agents\Data\AgentState
    {
        return (new DefinitionStateFactory)
            ->instantiateAgentState($definition)
            ->withUserMessage($request->prompt);
    }

    private function assertSelection(TellRequest $request): void
    {
        if ($request->branch !== null && $request->session !== null) {
            throw new \InvalidArgumentException('--branch and --session cannot be used together.');
        }
    }

    private function withBranchConfig(TellRequest $request): TellRequest
    {
        $workspace = $this->agents->workspace()->discover($request->directory);
        $userDefaults = $this->userPolicyDefaults();
        $projectDefaults = $workspace === null
            ? []
            : TellPolicyDefaults::fromFile($workspace->paths->config.'/defaults.json');
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
        $selection = (new BranchResolver(new ArenaStore($workspace)))->resolve($request->branch);
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
    private function userPolicyDefaults(): array
    {
        return TellPolicyDefaults::fromFile($this->agents->paths()->configDirectory.'/execution-defaults.json');
    }

    private function resultFromExecution(
        WorkspaceSessionExecution $execution,
        bool $durable,
        ?string $session,
        ?string $workspace,
    ): TellResult {
        return new TellResult(
            state: $execution->state,
            warnings: $execution->warnings,
            transient: $execution->transient,
            durable: $durable,
            session: $session,
            workspace: $workspace,
        );
    }

}
