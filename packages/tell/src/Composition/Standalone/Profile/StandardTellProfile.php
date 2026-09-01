<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanBuildTellConsoleApplication;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellExtensions;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanContributeTellCommands;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanRunTellProtocol;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanLoadTellAgentDefinitions;
use Cognesy\Tell\Core\Contract\Secrets\CanManageTellCredentials;
use Cognesy\Tell\Composition\Standalone\Host\TellHostProfile;
use Cognesy\Tell\Core\Agent\TellAgentFactory;

final readonly class StandardTellProfile
{
    /** @param callable(): CanUseTools|null $driverFactory */
    public static function runtime(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanProvideTellWorkspace $workspaces = null,
    ): TellHostProfile {
        return new TellHostProfile(
            name: 'standard',
            modules: [
                StandardTellModules::paths($paths),
                StandardTellModules::secrets($directory),
                StandardTellModules::model($directory),
                StandardTellModules::providerCatalogue($directory),
                StandardTellModules::clock(),
                StandardTellModules::cancellation($cancellation),
                StandardTellModules::tracing($directory),
                StandardTellModules::agentDefinitions($directory),
                StandardTellModules::composerAgentDiscovery(),
                StandardTellModules::codingTools($directory),
                StandardTellModules::askUserTool(),
                StandardTellModules::subagents(),
                StandardTellModules::standardAgentCapabilities(),
                StandardTellModules::agent($directory, $driverFactory, $agentBuilder),
                StandardTellModules::workspace($workspaces),
                StandardTellModules::executionWorkspace(),
                StandardTellModules::configuration(),
                StandardTellModules::extensions(),
                StandardTellModules::tools($directory),
                StandardTellModules::observation(),
                StandardTellModules::runtime(),
                StandardTellModules::execution(),
                StandardTellModules::conversations($directory),
                StandardTellModules::protocol(),
            ],
            requiredCapabilities: [
                CanResolveTellPaths::class,
                CanResolveTellSecrets::class,
                CanResolveTellModel::class,
                CanCatalogueTellProviders::class,
                CanReadTellClock::class,
                CanProvideCancellationSignal::class,
                CanTraceTellExecution::class,
                CanLoadTellAgentDefinitions::class,
                CanContributeTellAgent::class,
                CanBuildTellAgent::class,
                CanManageTellWorkspace::class,
                CanOpenTellWorkspace::class,
                CanReadTellBranchConfiguration::class,
                CanOpenTellExecutionWorkspace::class,
                CanAccessTellConversations::class,
                CanResolveTellConfiguration::class,
                CanCatalogueTellExtensions::class,
                CanDispatchTellTool::class,
                CanObserveTellExecution::class,
                CanCreateTellRuntime::class,
                CanRunTell::class,
                CanRunTellProtocol::class,
            ],
        );
    }

    /** @param callable(): CanUseTools|null $driverFactory */
    public static function cli(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanProvideTellWorkspace $workspaces = null,
    ): TellHostProfile {
        $runtime = self::runtime($directory, $paths, $driverFactory, $agentBuilder, $cancellation, $workspaces);

        return new TellHostProfile(
            name: 'cli',
            modules: [
                ...$runtime->modules,
                StandardTellModules::credentialManagement($directory),
                StandardTellModules::commands($directory),
                StandardTellModules::application(),
            ],
            requiredCapabilities: [
                ...$runtime->requiredCapabilities,
                CanManageTellCredentials::class,
                CanContributeTellCommands::class,
                CanBuildTellConsoleApplication::class,
            ],
        );
    }
}
