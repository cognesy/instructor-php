<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile;

use Cognesy\Tell\Composition\Standalone\Host\TellModuleDefinition;
use Cognesy\Tell\Composition\Standalone\Host\TellCapabilityProviders;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Capability\Agent\ComposerDiscovery\ComposerTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Definitions\FilesystemTellAgentDefinitions;
use Cognesy\Tell\Capability\Agent\Standard\StandardTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Subagent\TellSubagentContribution;
use Cognesy\Tell\Capability\Tool\AskUser\AskUserToolContribution;
use Cognesy\Tell\Capability\Tool\Coding\CodingToolContribution;
use Cognesy\Tell\Capability\Model\Polyglot\PolyglotTellModelResolver;
use Cognesy\Tell\Capability\Configuration\Standard\StandardTellConfigurationResolver;
use Cognesy\Tell\Capability\Paths\Installed\StandardTellPathResolver;
use Cognesy\Tell\Capability\Secrets\Standard\StandardTellSecretResolver;
use Cognesy\Tell\Capability\Secrets\Standard\TellCredentialStore;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Adapter\Console\Symfony\CoreTellCommandContributor;
use Cognesy\Tell\Adapter\Console\Symfony\SymfonyConsoleApplicationBuilder;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanBuildTellConsoleApplication;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellExtensions;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanContributeTellCommands;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanRunTellProtocol;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Secrets\CanManageTellCredentials;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Capability\Discovery\Composer\ComposerTellExtensionCatalogue;
use Cognesy\Tell\Capability\Discovery\Polyglot\PolyglotTellProviderCatalogue;
use Cognesy\Tell\Capability\Observation\FilesystemTrace\StandardTellExecutionTracer;
use Cognesy\Tell\Capability\Observation\Null\NullTellObserver;
use Cognesy\Tell\Adapter\Protocol\OneRun\OneRunTellProtocol;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;
use Cognesy\Tell\Core\Execution\DefaultTellRunner;
use Cognesy\Tell\Composition\Standalone\Profile\StandardTellRuntimeFactory;
use Cognesy\Tell\Capability\Execution\System\SystemTellClock;
use Cognesy\Tell\Core\Agent\TellAgentFactory;
use Cognesy\Tell\Capability\Tool\Standard\StandardTellToolDispatcher;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanLoadTellAgentDefinitions;
use Cognesy\Tell\Core\Workspace\Execution\TellExecutionWorkspaceProvider;
use Cognesy\Tell\Core\Workspace\TellConversations;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemTellWorkspaceProvider;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository;

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

    public static function providerCatalogue(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'provider-catalogue.polyglot',
            provides: [CanCatalogueTellProviders::class],
            requires: [CanResolveTellPaths::class],
            factory: static function (CanResolveTellPaths $paths) use ($directory): object {
                $resolved = $paths->resolve($directory);

                return new PolyglotTellProviderCatalogue(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                );
            },
            description: 'Credential-free Polyglot connection and provider catalogue',
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
            requires: [
                CanResolveTellPaths::class,
                CanResolveTellModel::class,
                CanReadTellClock::class,
                CanTraceTellExecution::class,
                CanLoadTellAgentDefinitions::class,
                CanContributeTellAgent::class,
            ],
            factory: static function (
                CanResolveTellPaths $paths,
                CanResolveTellModel $model,
                CanReadTellClock $clock,
                CanTraceTellExecution $tracer,
                CanLoadTellAgentDefinitions $definitions,
                TellCapabilityProviders $contributions,
            ) use ($directory, $driverFactory, $agentBuilder): object {
                if ($agentBuilder !== null) {
                    return $agentBuilder;
                }
                $resolved = $paths->resolve($directory);

                $selected = [];
                foreach ($contributions as $contribution) {
                    if (!$contribution instanceof CanContributeTellAgent) {
                        throw new \LogicException('Invalid Tell agent contribution.');
                    }
                    $selected[] = $contribution;
                }

                return new TellAgentFactory(
                    paths: new TellPaths($resolved->packageAgents, $resolved->home),
                    tracer: $tracer,
                    clock: $clock,
                    modelResolver: $model,
                    definitionLoader: $definitions,
                    contributions: $selected,
                    driver: $driverFactory === null ? null : $driverFactory(),
                );
            },
            description: 'Cognesy Agents runtime with replaceable inference driver factory',
        );
    }

    public static function agentDefinitions(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent-definitions.filesystem',
            provides: [CanLoadTellAgentDefinitions::class],
            requires: [CanResolveTellPaths::class],
            factory: static fn (CanResolveTellPaths $paths): object => new FilesystemTellAgentDefinitions(
                new TellPaths($paths->resolve($directory)->packageAgents, $paths->resolve($directory)->home),
            ),
            description: 'Filesystem agent definition discovery',
        );
    }

    public static function composerAgentDiscovery(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent-contribution.composer-discovery',
            provides: [CanContributeTellAgent::class],
            factory: static fn (): object => new ComposerTellAgentContribution(),
            description: 'Composer-discovered Agents capabilities and tools',
        );
    }

    public static function standardAgentCapabilities(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent-contribution.standard',
            provides: [CanContributeTellAgent::class],
            factory: static fn (): object => new StandardTellAgentContribution(),
            description: 'System prompt, self knowledge, description, and definition capabilities',
        );
    }

    public static function subagents(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent-contribution.subagents',
            provides: [CanContributeTellAgent::class],
            factory: static fn (): object => new TellSubagentContribution(),
            description: 'Bounded Tell child-agent delegation',
        );
    }

    public static function codingTools(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent-contribution.coding-tools',
            provides: [CanContributeTellAgent::class],
            requires: [CanResolveTellPaths::class],
            factory: static fn (CanResolveTellPaths $paths): object => new CodingToolContribution(
                new TellPaths($paths->resolve($directory)->packageAgents, $paths->resolve($directory)->home),
            ),
            description: 'Sandboxed coding tool contribution',
        );
    }

    public static function askUserTool(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent-contribution.ask-user',
            provides: [CanContributeTellAgent::class],
            factory: static fn (): object => new AskUserToolContribution(),
            description: 'Pre-supplied non-interactive answer contribution',
        );
    }

    public static function commands(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'commands.core',
            provides: [CanContributeTellCommands::class],
            requires: [
                CanBuildTellAgent::class,
                CanCreateTellRuntime::class,
                CanDispatchTellTool::class,
                CanResolveTellPaths::class,
                CanProvideCancellationSignal::class,
                CanRunTellProtocol::class,
                CanAccessTellConversations::class,
                CanManageTellWorkspace::class,
                CanReadTellBranchConfiguration::class,
                CanManageTellCredentials::class,
                CanCatalogueTellProviders::class,
            ],
            factory: static function (
                CanBuildTellAgent $agents,
                CanCreateTellRuntime $runtime,
                CanDispatchTellTool $tools,
                CanResolveTellPaths $paths,
                CanProvideCancellationSignal $cancellation,
                CanRunTellProtocol $protocol,
                CanAccessTellConversations $conversations,
                CanManageTellWorkspace $workspaces,
                CanReadTellBranchConfiguration $branchConfiguration,
                CanManageTellCredentials $credentials,
                CanCatalogueTellProviders $providers,
            ) use ($directory): object {
                $resolved = $paths->resolve($directory);

                return new CoreTellCommandContributor(
                    $agents,
                    $runtime,
                    $tools,
                    $conversations,
                    $workspaces,
                    $branchConfiguration,
                    $credentials,
                    $providers,
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    $cancellation,
                    $protocol,
                );
            },
            description: 'Canonical Symfony shell command contribution',
        );
    }

    public static function credentialManagement(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'credentials.filesystem',
            provides: [CanManageTellCredentials::class],
            requires: [CanResolveTellPaths::class],
            factory: static function (CanResolveTellPaths $paths) use ($directory): object {
                $resolved = $paths->resolve($directory);

                return new TellCredentialStore(new TellPaths($resolved->packageAgents, $resolved->home));
            },
            description: 'Private Tell credential management',
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

    public static function runtime(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'runtime.standard',
            provides: [CanCreateTellRuntime::class],
            requires: [
                CanBuildTellAgent::class,
                CanOpenTellExecutionWorkspace::class,
                CanProvideCancellationSignal::class,
                CanResolveTellConfiguration::class,
                CanObserveTellExecution::class,
                CanTraceTellExecution::class,
            ],
            factory: static function (
                CanBuildTellAgent $agents,
                CanOpenTellExecutionWorkspace $workspaces,
                CanProvideCancellationSignal $cancellation,
                CanResolveTellConfiguration $configuration,
                CanObserveTellExecution $observer,
                CanTraceTellExecution $tracer,
            ): object {
                return new StandardTellRuntimeFactory(
                    $agents,
                    $workspaces,
                    $tracer,
                    $cancellation,
                    $configuration,
                    $observer,
                );
            },
            description: 'Runtime construction from explicit execution services',
        );
    }

    public static function executionWorkspace(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'workspace.execution',
            provides: [CanOpenTellExecutionWorkspace::class],
            requires: [CanOpenTellWorkspace::class],
            factory: static fn (CanOpenTellWorkspace $workspaces): object => new TellExecutionWorkspaceProvider($workspaces),
            description: 'Backend-neutral durable execution workspace opener',
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

    public static function workspace(?CanProvideTellWorkspace $workspaces = null): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'workspace.filesystem',
            provides: [
                CanManageTellWorkspace::class,
                CanOpenTellWorkspace::class,
                CanReadTellBranchConfiguration::class,
            ],
            factory: static fn (): object => $workspaces ?? new FilesystemTellWorkspaceProvider(new WorkspaceRepository()),
            description: 'Canonical workspace, conversations, refs, and branch configuration',
        );
    }

    public static function conversations(string $directory): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'conversations.standard',
            provides: [CanAccessTellConversations::class],
            requires: [CanBuildTellAgent::class, CanRunTell::class, CanTraceTellExecution::class, CanResolveTellPaths::class, CanOpenTellWorkspace::class, CanCatalogueTellProviders::class],
            factory: static fn (
                CanBuildTellAgent $agents,
                CanRunTell $runner,
                CanTraceTellExecution $tracer,
                CanResolveTellPaths $paths,
                CanOpenTellWorkspace $workspaces,
                CanCatalogueTellProviders $providers,
            ): object => new TellConversations(
                $agents,
                $runner,
                $tracer,
                $workspaces,
                new TellPaths($paths->resolve($directory)->packageAgents, $paths->resolve($directory)->home),
                $providers,
            ),
            description: 'Backend-neutral conversation, ref, and branch facades',
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
