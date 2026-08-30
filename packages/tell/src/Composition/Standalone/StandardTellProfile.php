<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanBuildTellConsoleApplication;
use Cognesy\Tell\Contracts\CanCatalogueTellExtensions;
use Cognesy\Tell\Contracts\CanCreateTellRuntime;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\CanResolveTellSecrets;
use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Contracts\CanTraceTellExecution;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\WorkspaceRepository;

final readonly class StandardTellProfile
{
    /** @param callable(): CanUseTools|null $driverFactory */
    public static function runtime(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?WorkspaceRepository $workspaces = null,
    ): TellHostProfile {
        $workspaces ??= new WorkspaceRepository();

        return new TellHostProfile(
            name: 'standard',
            modules: [
                StandardTellModules::paths($paths),
                StandardTellModules::secrets($directory),
                StandardTellModules::model($directory),
                StandardTellModules::clock(),
                StandardTellModules::cancellation($cancellation),
                StandardTellModules::tracing($directory),
                StandardTellModules::agent($directory, $driverFactory, $agentBuilder),
                StandardTellModules::workspace($workspaces),
                StandardTellModules::configuration(),
                StandardTellModules::extensions(),
                StandardTellModules::tools($directory),
                StandardTellModules::observation(),
                StandardTellModules::runtime($directory, $workspaces),
                StandardTellModules::execution(),
                StandardTellModules::conversations($directory, $workspaces),
                StandardTellModules::protocol(),
            ],
            requiredCapabilities: [
                CanResolveTellPaths::class,
                CanResolveTellSecrets::class,
                CanResolveTellModel::class,
                CanReadTellClock::class,
                CanProvideCancellationSignal::class,
                CanTraceTellExecution::class,
                CanBuildTellAgent::class,
                CanManageTellWorkspace::class,
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
        ?WorkspaceRepository $workspaces = null,
    ): TellHostProfile {
        $workspaces ??= new WorkspaceRepository();
        $runtime = self::runtime($directory, $paths, $driverFactory, $agentBuilder, $cancellation, $workspaces);

        return new TellHostProfile(
            name: 'cli',
            modules: [
                ...$runtime->modules,
                StandardTellModules::commands($directory, $workspaces),
                StandardTellModules::application(),
            ],
            requiredCapabilities: [
                ...$runtime->requiredCapabilities,
                CanContributeTellCommands::class,
                CanBuildTellConsoleApplication::class,
            ],
        );
    }
}
