<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone;

use Closure;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Adapter\Console\Symfony\CoreTellCommands;
use Cognesy\Tell\Adapter\Console\Symfony\TellCommands;
use Cognesy\Tell\Adapter\Console\Symfony\TellConsoleApplication;
use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanRunTellProtocol;
use Cognesy\Tell\Adapter\Protocol\OneRun\OneRunTellProtocol;
use Cognesy\Tell\Capability\Agent\ComposerDiscovery\ComposerTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Definitions\FilesystemTellAgentDefinitions;
use Cognesy\Tell\Capability\Agent\Standard\StandardTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Subagent\TellSubagentContribution;
use Cognesy\Tell\Capability\Configuration\Standard\StandardTellConfigurationResolver;
use Cognesy\Tell\Capability\Discovery\Polyglot\PolyglotTellProviderCatalogue;
use Cognesy\Tell\Capability\Execution\System\SystemTellClock;
use Cognesy\Tell\Capability\Model\Polyglot\PolyglotTellModelResolver;
use Cognesy\Tell\Capability\Observation\ExecutionJournal\JournalBackedTellRuns;
use Cognesy\Tell\Capability\Observation\FilesystemTrace\StandardTellExecutionTracer;
use Cognesy\Tell\Capability\Observation\ExecutionJournal\StandardTellExecutionJournal;
use Cognesy\Tell\Capability\Observation\Null\NullTellObserver;
use Cognesy\Tell\Capability\Paths\Installed\StandardTellPathResolver;
use Cognesy\Tell\Capability\Secrets\Standard\StandardTellSecretResolver;
use Cognesy\Tell\Capability\Secrets\Standard\TellCredentialStore;
use Cognesy\Tell\Capability\Tool\AskUser\AskUserToolContribution;
use Cognesy\Tell\Capability\Tool\Coding\CodingToolContribution;
use Cognesy\Tell\Capability\Tool\Standard\StandardTellToolDispatcher;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemTellWorkspaceProvider;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository;
use Cognesy\Tell\Core\Agent\TellAgentContributions;
use Cognesy\Tell\Core\Agent\TellAgentFactory;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanLoadTellAgentDefinitions;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanJournalTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanInspectTellRuns;
use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Core\Contract\Secrets\CanManageTellCredentials;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Core\Execution\TellRuntimeFactory;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Workspace\Execution\TellExecutionWorkspaceProvider;
use Cognesy\Tell\Core\Workspace\TellConversations;
use Cognesy\Tell\Tell;
use Cognesy\Utils\Container\Container;
use LogicException;

/** Selects and registers the standard standalone Tell services. */
final readonly class StandardTellServices
{
    /**
     * @param Closure(TellAgentContributions): TellAgentContributions $selectContributions
     * @param Closure(): CanUseTools|null $driverFactory
     */
    public static function registerRuntime(
        Container $services,
        string $directory,
        ?TellPaths $paths,
        Closure $selectContributions,
        ?Closure $driverFactory = null,
    ): void {
        $services->singleton(
            CanResolveTellPaths::class,
            static fn (): CanResolveTellPaths => new StandardTellPathResolver($paths ?? TellPaths::installed()),
        );
        $services->singleton(
            CanResolveTellSecrets::class,
            static function (Container $services) use ($directory): CanResolveTellSecrets {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new StandardTellSecretResolver(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    $resolved->project,
                );
            },
        );
        $services->singleton(
            CanResolveTellModel::class,
            static function (Container $services) use ($directory): CanResolveTellModel {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new PolyglotTellModelResolver(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    self::get($services, CanResolveTellSecrets::class),
                );
            },
        );
        $services->singleton(
            CanCatalogueTellProviders::class,
            static function (Container $services) use ($directory): CanCatalogueTellProviders {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new PolyglotTellProviderCatalogue(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                );
            },
        );
        $services->singleton(CanReadTellClock::class, static fn (): CanReadTellClock => new SystemTellClock());
        $services->singleton(
            CanProvideCancellationSignal::class,
            static fn (): CanProvideCancellationSignal => new InMemoryCancellationSource(),
        );
        $services->singleton(
            CanTraceTellExecution::class,
            static function (Container $services) use ($directory): CanTraceTellExecution {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new StandardTellExecutionTracer(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                );
            },
        );
        $services->singleton(
            CanJournalTellExecution::class,
            static function (Container $services) use ($directory): CanJournalTellExecution {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new StandardTellExecutionJournal(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                );
            },
        );
        $services->singleton(
            CanInspectTellRuns::class,
            static function (Container $services) use ($directory): CanInspectTellRuns {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new JournalBackedTellRuns(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                );
            },
        );
        $services->singleton(
            CanLoadTellAgentDefinitions::class,
            static function (Container $services) use ($directory): CanLoadTellAgentDefinitions {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new FilesystemTellAgentDefinitions(
                    new TellPaths($resolved->packageAgents, $resolved->home),
                );
            },
        );
        self::registerAgent($services, $directory, $selectContributions, $driverFactory);
        self::registerWorkspace($services, new FilesystemTellWorkspaceProvider(new WorkspaceRepository()));
        $services->singleton(
            CanOpenTellExecutionWorkspace::class,
            static fn (Container $services): CanOpenTellExecutionWorkspace => new TellExecutionWorkspaceProvider(
                self::get($services, CanOpenTellWorkspace::class),
            ),
        );
        $services->singleton(
            CanResolveTellConfiguration::class,
            static fn (Container $services): CanResolveTellConfiguration => new StandardTellConfigurationResolver(
                self::get($services, CanResolveTellPaths::class),
                self::get($services, CanReadTellBranchConfiguration::class),
            ),
        );
        $services->singleton(
            CanObserveTellExecution::class,
            static fn (): CanObserveTellExecution => new NullTellObserver(),
        );
        $services->singleton(
            TellRuntimeFactory::class,
            static fn (Container $services): TellRuntimeFactory => new TellRuntimeFactory(
                self::get($services, CanBuildTellAgent::class),
                self::get($services, CanOpenTellExecutionWorkspace::class),
                self::get($services, CanTraceTellExecution::class),
                self::get($services, CanJournalTellExecution::class),
                self::get($services, CanProvideCancellationSignal::class),
                self::get($services, CanResolveTellConfiguration::class),
                self::get($services, CanObserveTellExecution::class),
            ),
        );
        $services->singleton(
            CanAccessTellConversations::class,
            static function (Container $services) use ($directory): CanAccessTellConversations {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new TellConversations(
                    self::get($services, CanBuildTellAgent::class),
                    self::get($services, TellRuntimeFactory::class),
                    self::get($services, CanTraceTellExecution::class),
                    self::get($services, CanOpenTellWorkspace::class),
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    self::get($services, CanCatalogueTellProviders::class),
                );
            },
        );
        $services->singleton(
            CanDispatchTellTool::class,
            static fn (Container $services): CanDispatchTellTool => new StandardTellToolDispatcher(
                self::get($services, CanBuildTellAgent::class),
                self::get($services, CanResolveTellConfiguration::class),
                $directory,
                self::get($services, CanProvideCancellationSignal::class),
            ),
        );
        $services->singleton(
            Tell::class,
            static fn (Container $services): Tell => new Tell(
                directory: $directory,
                runtimeFactory: self::get($services, TellRuntimeFactory::class),
                workspaces: self::get($services, CanManageTellWorkspace::class),
                conversations: self::get($services, CanAccessTellConversations::class),
                providerCatalogue: self::get($services, CanCatalogueTellProviders::class),
                toolDispatcher: self::get($services, CanDispatchTellTool::class),
                cancellation: self::get($services, CanProvideCancellationSignal::class),
            ),
        );
    }

    /**
     * @param Closure(TellAgentContributions): TellAgentContributions $selectContributions
     * @param Closure(): CanUseTools|null $driverFactory
     */
    public static function registerAgent(
        Container $services,
        string $directory,
        Closure $selectContributions,
        ?Closure $driverFactory = null,
    ): void {
        $services->singleton(
            CanBuildTellAgent::class,
            static function (Container $services) use ($directory, $selectContributions, $driverFactory): CanBuildTellAgent {
                $paths = self::get($services, CanResolveTellPaths::class);
                $resolved = $paths->resolve($directory);
                $tellPaths = new TellPaths($resolved->packageAgents, $resolved->home);
                $standard = new TellAgentContributions(
                    new ComposerTellAgentContribution(),
                    new CodingToolContribution($tellPaths),
                    new AskUserToolContribution(),
                    new TellSubagentContribution(),
                    new StandardTellAgentContribution(),
                );

                return new TellAgentFactory(
                    paths: $tellPaths,
                    tracer: self::get($services, CanTraceTellExecution::class),
                    clock: self::get($services, CanReadTellClock::class),
                    modelResolver: self::get($services, CanResolveTellModel::class),
                    providerCatalogue: self::get($services, CanCatalogueTellProviders::class),
                    definitionLoader: self::get($services, CanLoadTellAgentDefinitions::class),
                    contributions: $selectContributions($standard),
                    driver: $driverFactory?->__invoke(),
                );
            },
        );
    }

    public static function registerWorkspace(Container $services, CanProvideTellWorkspace $workspace): void {
        $services->instance(CanProvideTellWorkspace::class, $workspace);
        $services->instance(CanManageTellWorkspace::class, $workspace);
        $services->instance(CanOpenTellWorkspace::class, $workspace);
        $services->instance(CanReadTellBranchConfiguration::class, $workspace);
    }

    public static function registerCli(
        Container $services,
        string $directory,
        TellCommands $additionalCommands,
    ): void {
        $services->singleton(
            CanManageTellCredentials::class,
            static function (Container $services) use ($directory): CanManageTellCredentials {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);

                return new TellCredentialStore(new TellPaths($resolved->packageAgents, $resolved->home));
            },
        );
        $services->singleton(
            CanRunTellProtocol::class,
            static fn (Container $services): CanRunTellProtocol => new OneRunTellProtocol(
                self::get($services, TellRuntimeFactory::class),
            ),
        );
        $services->singleton(
            TellCommands::class,
            static function (Container $services) use ($directory, $additionalCommands): TellCommands {
                $resolved = self::get($services, CanResolveTellPaths::class)->resolve($directory);
                $commands = new CoreTellCommands(
                    self::get($services, CanBuildTellAgent::class),
                    self::get($services, TellRuntimeFactory::class),
                    self::get($services, CanDispatchTellTool::class),
                    self::get($services, CanAccessTellConversations::class),
                    self::get($services, CanManageTellWorkspace::class),
                    self::get($services, CanReadTellBranchConfiguration::class),
                    self::get($services, CanManageTellCredentials::class),
                    self::get($services, CanCatalogueTellProviders::class),
                    self::get($services, CanInspectTellRuns::class),
                    new TellPaths($resolved->packageAgents, $resolved->home),
                    self::get($services, CanProvideCancellationSignal::class),
                    self::get($services, CanRunTellProtocol::class),
                );

                return $commands->commands()->merge($additionalCommands);
            },
        );
        $services->singleton(
            TellConsoleApplication::class,
            static fn (Container $services): TellConsoleApplication => new TellConsoleApplication(
                self::get($services, TellCommands::class),
            ),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private static function get(Container $services, string $id): object {
        $service = $services->get($id);
        if (!$service instanceof $id) {
            throw new LogicException("Tell service {$id} resolved an invalid implementation.");
        }

        return $service;
    }
}
