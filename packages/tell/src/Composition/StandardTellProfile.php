<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanBuildTellApplication;
use Cognesy\Tell\Contracts\CanCatalogueTellExtensions;
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
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\TellAgentFactory;

final readonly class StandardTellProfile
{
    /** @param callable(): CanUseTools|null $driverFactory */
    public static function runtime(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?TellAgentFactory $agentFactory = null,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellHostProfile {
        return new TellHostProfile(
            name: 'standard',
            modules: [
                StandardTellModules::paths($paths),
                StandardTellModules::secrets($directory),
                StandardTellModules::model($directory),
                StandardTellModules::clock(),
                StandardTellModules::cancellation($cancellation),
                StandardTellModules::agent($directory, $driverFactory, $agentFactory),
                StandardTellModules::workspace(),
                StandardTellModules::configuration(),
                StandardTellModules::extensions(),
                StandardTellModules::tools($directory),
                StandardTellModules::observation(),
                StandardTellModules::execution(),
                StandardTellModules::protocol(),
                StandardTellModules::commands(),
                StandardTellModules::application(),
            ],
            requiredCapabilities: [
                CanResolveTellPaths::class,
                CanResolveTellSecrets::class,
                CanResolveTellModel::class,
                CanReadTellClock::class,
                CanProvideCancellationSignal::class,
                CanBuildTellAgent::class,
                CanManageTellWorkspace::class,
                CanAccessTellConversations::class,
                CanResolveTellConfiguration::class,
                CanCatalogueTellExtensions::class,
                CanDispatchTellTool::class,
                CanObserveTellExecution::class,
                CanRunTell::class,
                CanRunTellProtocol::class,
                CanContributeTellCommands::class,
                CanBuildTellApplication::class,
            ],
        );
    }
}
