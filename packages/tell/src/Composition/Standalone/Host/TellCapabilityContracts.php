<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Host;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanBuildTellConsoleApplication;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanContributeTellCommands;
use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanRunTellProtocol;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellExtensions;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanLoadTellAgentDefinitions;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Secrets\CanManageTellCredentials;
/** Canonical cardinality policy used by graph admission and documentation. */
final readonly class TellCapabilityContracts
{
    /** @return array<class-string, TellCapabilityCardinality> */
    public static function cardinalities(): array {
        return [
            CanRunTell::class => TellCapabilityCardinality::Singleton,
            CanCreateTellRuntime::class => TellCapabilityCardinality::Singleton,
            CanBuildTellAgent::class => TellCapabilityCardinality::Singleton,
            CanLoadTellAgentDefinitions::class => TellCapabilityCardinality::Singleton,
            CanContributeTellAgent::class => TellCapabilityCardinality::OrderedContribution,
            CanResolveTellModel::class => TellCapabilityCardinality::Singleton,
            CanResolveTellSecrets::class => TellCapabilityCardinality::Singleton,
            CanManageTellCredentials::class => TellCapabilityCardinality::Singleton,
            CanManageTellWorkspace::class => TellCapabilityCardinality::Singleton,
            CanOpenTellWorkspace::class => TellCapabilityCardinality::Singleton,
            CanOpenTellExecutionWorkspace::class => TellCapabilityCardinality::Singleton,
            CanAccessTellConversations::class => TellCapabilityCardinality::Singleton,
            CanReadTellBranchConfiguration::class => TellCapabilityCardinality::OptionalSingleton,
            CanResolveTellConfiguration::class => TellCapabilityCardinality::Singleton,
            CanResolveTellPaths::class => TellCapabilityCardinality::Singleton,
            CanCatalogueTellExtensions::class => TellCapabilityCardinality::Singleton,
            CanCatalogueTellProviders::class => TellCapabilityCardinality::Singleton,
            CanDispatchTellTool::class => TellCapabilityCardinality::Singleton,
            CanObserveTellExecution::class => TellCapabilityCardinality::Singleton,
            CanTraceTellExecution::class => TellCapabilityCardinality::Singleton,
            CanContributeTellCommands::class => TellCapabilityCardinality::OrderedContribution,
            CanBuildTellConsoleApplication::class => TellCapabilityCardinality::Singleton,
            CanRunTellProtocol::class => TellCapabilityCardinality::Singleton,
            CanProvideCancellationSignal::class => TellCapabilityCardinality::Singleton,
            CanReadTellClock::class => TellCapabilityCardinality::Singleton,
        ];
    }

    /** @param class-string $capability */
    public static function cardinality(string $capability): ?TellCapabilityCardinality {
        return self::cardinalities()[$capability] ?? null;
    }
}
