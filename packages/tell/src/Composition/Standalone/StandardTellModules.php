<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\PolyglotTellModelResolver;
use Cognesy\Tell\Configuration\StandardTellConfigurationResolver;
use Cognesy\Tell\Configuration\StandardTellPathResolver;
use Cognesy\Tell\Configuration\StandardTellSecretResolver;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Console\CoreTellCommandContributor;
use Cognesy\Tell\Console\SymfonyConsoleApplicationBuilder;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanBuildTellConsoleApplication;
use Cognesy\Tell\Contracts\CanCatalogueTellExtensions;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanCreateTellRuntime;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\CanResolveTellSecrets;
use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Contracts\CanTraceTellExecution;
use Cognesy\Tell\Discovery\ComposerTellExtensionCatalogue;
use Cognesy\Tell\Observability\NullTellObserver;
use Cognesy\Tell\Observability\StandardTellExecutionTracer;
use Cognesy\Tell\Protocol\OneRunTellProtocol;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\DefaultTellRunner;
use Cognesy\Tell\Runtime\StandardTellAgentBuilder;
use Cognesy\Tell\Runtime\StandardTellRuntimeFactory;
use Cognesy\Tell\Runtime\SystemTellClock;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tool\StandardTellToolDispatcher;
use Cognesy\Tell\Workspace\Conversation\FilesystemTellConversations;
use Cognesy\Tell\Workspace\FilesystemWorkspaceProvider;
use Cognesy\Tell\Workspace\WorkspaceRepository;

/** Auditable standard module definitions; every factory creates a fresh instance. */
final readonly class StandardTellModules
{
    public static function paths(?TellPaths $paths = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'paths.standard',
            provides: [CanResolveTellPaths::class],
            factory: static fn (): object => new StandardTellPathResolver($paths ?? TellPaths::installed()),
            description: 'Installed Tell path policy',
        );
    }

    public static function secrets(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'secrets.standard',
            provides: [CanResolveTellSecrets::class],
            requires: [CanResolveTellPaths::class],
            factory: static function (CanResolveTellPaths $paths) use ($directory): object {
                $resolved = $paths->resolve($directory);

                return new StandardTellSecretResolver(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    $resolved->project,
                );
            },
            description: 'Environment, workspace dotenv, and Tell credential chain',
        );
    }

    public static function model(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'model.polyglot',
            provides: [CanResolveTellModel::class],
            requires: [CanResolveTellPaths::class, CanResolveTellSecrets::class],
            factory: static function (CanResolveTellPaths $paths, CanResolveTellSecrets $secrets) use ($directory): object {
                $resolved = $paths->resolve($directory);

                return new PolyglotTellModelResolver(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    $secrets,
                );
            },
            description: 'Immutable Polyglot model selection',
        );
    }

    public static function clock(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'clock.system',
            provides: [CanReadTellClock::class],
            factory: static fn (): object => new SystemTellClock(),
            description: 'Monotonic execution clock',
        );
    }

    public static function cancellation(?CanProvideCancellationSignal $source = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'cancellation.memory',
            provides: [CanProvideCancellationSignal::class],
            factory: static fn (): object => $source ?? new InMemoryCancellationSource(),
            description: 'Host-local cooperative cancellation source',
        );
    }

    /** @param callable(): CanUseTools|null $driverFactory */
    public static function agent(
        string $directory,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
    ): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent.cognesy',
            provides: [CanBuildTellAgent::class],
            requires: [CanResolveTellPaths::class, CanResolveTellModel::class, CanReadTellClock::class, CanTraceTellExecution::class],
            factory: static function (
                CanResolveTellPaths $paths,
                CanResolveTellModel $model,
                CanReadTellClock $clock,
                CanTraceTellExecution $tracer,
            ) use ($directory, $driverFactory, $agentBuilder): object {
                if ($agentBuilder !== null) {
                    return $agentBuilder;
                }
                $resolved = $paths->resolve($directory);

                return new StandardTellAgentBuilder(new TellAgentFactory(
                    paths: new TellPaths($resolved->packageAgents, $resolved->home),
                    tracer: $tracer,
                    clock: $clock,
                    driver: $driverFactory === null ? null : $driverFactory(),
                    modelResolver: $model,
                ));
            },
            description: 'Cognesy Agents runtime with replaceable inference driver factory',
        );
    }

    public static function commands(string $directory, ?WorkspaceRepository $workspaces = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'commands.core',
            provides: [CanContributeTellCommands::class],
            requires: [
                CanBuildTellAgent::class,
                CanCreateTellRuntime::class,
                CanDispatchTellTool::class,
                CanTraceTellExecution::class,
                CanResolveTellPaths::class,
                CanProvideCancellationSignal::class,
                CanRunTellProtocol::class,
            ],
            factory: static function (
                CanBuildTellAgent $agents,
                CanCreateTellRuntime $runtime,
                CanDispatchTellTool $tools,
                CanTraceTellExecution $tracer,
                CanResolveTellPaths $paths,
                CanProvideCancellationSignal $cancellation,
                CanRunTellProtocol $protocol,
            ) use ($directory, $workspaces): object {
                $resolved = $paths->resolve($directory);

                return new CoreTellCommandContributor(
                    $agents,
                    $runtime,
                    $tools,
                    $tracer,
                    $workspaces ?? new WorkspaceRepository(),
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    $cancellation,
                    $protocol,
                );
            },
            description: 'Canonical Symfony shell command contribution',
        );
    }

    public static function protocol(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'protocol.one-run',
            provides: [CanRunTellProtocol::class],
            requires: [CanCreateTellRuntime::class],
            factory: static fn (CanCreateTellRuntime $runtime): object => new OneRunTellProtocol($runtime),
            description: 'Bounded one-request/one-run protocol execution',
        );
    }

    public static function application(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'application.symfony-console',
            provides: [CanBuildTellConsoleApplication::class],
            factory: static fn (): object => new SymfonyConsoleApplicationBuilder(),
            description: 'Symfony Console application edge',
        );
    }

    public static function runtime(string $directory, ?WorkspaceRepository $workspaces = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'runtime.standard',
            provides: [CanCreateTellRuntime::class],
            requires: [
                CanBuildTellAgent::class,
                CanResolveTellPaths::class,
                CanProvideCancellationSignal::class,
                CanResolveTellConfiguration::class,
                CanObserveTellExecution::class,
                CanTraceTellExecution::class,
            ],
            factory: static function (
                CanBuildTellAgent $agents,
                CanResolveTellPaths $paths,
                CanProvideCancellationSignal $cancellation,
                CanResolveTellConfiguration $configuration,
                CanObserveTellExecution $observer,
                CanTraceTellExecution $tracer,
            ) use ($directory, $workspaces): object {
                $resolved = $paths->resolve($directory);

                return new StandardTellRuntimeFactory(
                    $agents,
                    $workspaces ?? new WorkspaceRepository(),
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    $tracer,
                    $cancellation,
                    $configuration,
                    $observer,
                );
            },
            description: 'Runtime construction from explicit execution services',
        );
    }

    public static function execution(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'execution.default',
            provides: [CanRunTell::class],
            requires: [CanCreateTellRuntime::class],
            factory: static fn (CanCreateTellRuntime $runtime): object => new DefaultTellRunner($runtime),
            description: 'Default Tell execution modes and persistence semantics',
        );
    }

    /** @param callable(): CanObserveTellExecution|null $observerFactory */
    public static function observation(?callable $observerFactory = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'observation.standard',
            provides: [CanObserveTellExecution::class],
            factory: static fn (): object => $observerFactory === null ? new NullTellObserver() : $observerFactory(),
            description: 'Normalized redacted execution observation edge',
        );
    }

    public static function tracing(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'tracing.standard',
            provides: [CanTraceTellExecution::class],
            requires: [CanResolveTellPaths::class],
            factory: static function (CanResolveTellPaths $paths) use ($directory): object {
                $resolved = $paths->resolve($directory);

                return new StandardTellExecutionTracer(new TellPaths($resolved->packageAgents, $resolved->home));
            },
            description: 'Opt-in filesystem execution tracing',
        );
    }

    /** @param array<string, int|list<string>|string> $hostSettings */
    public static function configuration(array $hostSettings = []): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'configuration.standard',
            provides: [CanResolveTellConfiguration::class],
            requires: [CanResolveTellPaths::class],
            optional: [CanReadTellBranchConfiguration::class],
            factory: static fn (
                CanResolveTellPaths $paths,
                ?CanReadTellBranchConfiguration $branches,
            ): object => new StandardTellConfigurationResolver($paths, $branches, $hostSettings),
            description: 'Request, branch, host, user, and bundled configuration precedence',
        );
    }

    public static function workspace(?WorkspaceRepository $workspaces = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'workspace.filesystem',
            provides: [
                CanManageTellWorkspace::class,
                CanReadTellBranchConfiguration::class,
            ],
            factory: static fn (): object => new FilesystemWorkspaceProvider($workspaces ?? new WorkspaceRepository()),
            description: 'Canonical filesystem workspace, conversations, refs, and branch configuration',
        );
    }

    public static function conversations(string $directory, ?WorkspaceRepository $workspaces = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'conversations.filesystem',
            provides: [CanAccessTellConversations::class],
            requires: [CanBuildTellAgent::class, CanRunTell::class, CanTraceTellExecution::class, CanResolveTellPaths::class],
            factory: static fn (
                CanBuildTellAgent $agents,
                CanRunTell $runner,
                CanTraceTellExecution $tracer,
                CanResolveTellPaths $paths,
            ): object => new FilesystemTellConversations(
                $agents,
                $runner,
                $tracer,
                $workspaces ?? new WorkspaceRepository(),
                new TellPaths($paths->resolve($directory)->packageAgents, $paths->resolve($directory)->home),
            ),
            description: 'Canonical filesystem conversation, ref, and branch facades',
        );
    }

    public static function extensions(?string $vendorDirectory = null, ?string $rootComposerPath = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'extensions.composer',
            provides: [CanCatalogueTellExtensions::class],
            factory: static fn (): object => new ComposerTellExtensionCatalogue($vendorDirectory, $rootComposerPath),
            description: 'Descriptive Composer Agents extension catalogue and diagnostics',
        );
    }

    public static function tools(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'tools.standard',
            provides: [CanDispatchTellTool::class],
            requires: [CanBuildTellAgent::class, CanCreateTellRuntime::class, CanProvideCancellationSignal::class],
            factory: static fn (
                CanBuildTellAgent $agents,
                CanCreateTellRuntime $runtime,
                CanProvideCancellationSignal $cancellation,
            ): object => new StandardTellToolDispatcher($agents, $runtime->create(), $directory, $cancellation),
            description: 'Policy-bound direct tool invocation using the agent tool graph',
        );
    }
}
