<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\PolyglotTellModelResolver;
use Cognesy\Tell\Configuration\StandardTellConfigurationResolver;
use Cognesy\Tell\Configuration\StandardTellPathResolver;
use Cognesy\Tell\Configuration\StandardTellSecretResolver;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Console\CoreTellCommandContributor;
use Cognesy\Tell\Console\SymfonyTellApplicationBuilder;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanBuildTellApplication;
use Cognesy\Tell\Contracts\CanCatalogueTellExtensions;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
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
use Cognesy\Tell\Discovery\ComposerTellExtensionCatalogue;
use Cognesy\Tell\Observability\NullTellObserver;
use Cognesy\Tell\Protocol\OneRunTellProtocol;
use Cognesy\Tell\Runtime\CanOpenTellRuntime;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\DefaultTellRunner;
use Cognesy\Tell\Runtime\StandardTellAgentBuilder;
use Cognesy\Tell\Runtime\SystemTellClock;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tool\StandardTellToolDispatcher;

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
        ?TellAgentFactory $agentFactory = null,
    ): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'agent.cognesy',
            provides: [CanBuildTellAgent::class, CanOpenTellRuntime::class],
            requires: [CanResolveTellPaths::class, CanResolveTellModel::class, CanReadTellClock::class],
            factory: static function (
                CanResolveTellPaths $paths,
                CanResolveTellModel $model,
                CanReadTellClock $clock,
            ) use ($directory, $driverFactory, $agentFactory): object {
                if ($agentFactory !== null) {
                    return new StandardTellAgentBuilder($agentFactory);
                }
                $resolved = $paths->resolve($directory);

                return new StandardTellAgentBuilder(new TellAgentFactory(
                    paths: new TellPaths($resolved->packageAgents, $resolved->home),
                    clock: $clock,
                    driver: $driverFactory === null ? null : $driverFactory(),
                    modelResolver: $model,
                ));
            },
            description: 'Cognesy Agents runtime with replaceable inference driver factory',
        );
    }

    public static function commands(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'commands.core',
            provides: [CanContributeTellCommands::class],
            requires: [CanOpenTellRuntime::class, CanProvideCancellationSignal::class, CanRunTellProtocol::class],
            factory: static fn (
                CanOpenTellRuntime $runtime,
                CanProvideCancellationSignal $cancellation,
                CanRunTellProtocol $protocol,
            ): object => new CoreTellCommandContributor($runtime->agents(), $cancellation, $protocol),
            description: 'Canonical Symfony shell command contribution',
        );
    }

    public static function protocol(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'protocol.one-run',
            provides: [CanRunTellProtocol::class],
            requires: [CanRunTell::class],
            factory: static fn (CanRunTell $runner): object => new OneRunTellProtocol($runner),
            description: 'Bounded one-request/one-run protocol execution',
        );
    }

    public static function application(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'application.symfony',
            provides: [CanBuildTellApplication::class],
            factory: static fn (): object => new SymfonyTellApplicationBuilder(),
            description: 'Symfony Console application edge',
        );
    }

    public static function execution(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'execution.default',
            provides: [CanRunTell::class],
            requires: [CanOpenTellRuntime::class, CanProvideCancellationSignal::class, CanResolveTellConfiguration::class, CanObserveTellExecution::class],
            factory: static fn (
                CanOpenTellRuntime $agents,
                CanProvideCancellationSignal $cancellation,
                CanResolveTellConfiguration $configuration,
                CanObserveTellExecution $observer,
            ): object => new DefaultTellRunner($agents->runtime($cancellation, $configuration, $observer)),
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

    public static function workspace(): TellModuleDefinition {
        return new TellModuleDefinition(
            id: 'workspace.filesystem',
            provides: [
                CanManageTellWorkspace::class,
                CanAccessTellConversations::class,
                CanReadTellBranchConfiguration::class,
            ],
            requires: [CanOpenTellRuntime::class],
            factory: static fn (CanOpenTellRuntime $runtime): object => new FilesystemWorkspaceModule($runtime->agents()),
            description: 'Canonical filesystem workspace, conversations, refs, and branch configuration',
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
            requires: [CanOpenTellRuntime::class, CanProvideCancellationSignal::class],
            factory: static fn (
                CanOpenTellRuntime $runtime,
                CanProvideCancellationSignal $cancellation,
            ): object => new StandardTellToolDispatcher($runtime->agents(), $directory, $cancellation),
            description: 'Policy-bound direct tool invocation using the agent tool graph',
        );
    }
}
