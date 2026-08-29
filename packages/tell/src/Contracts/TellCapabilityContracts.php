<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Runtime\CanReadTellClock;

/** Canonical cardinality policy used by graph admission and documentation. */
final readonly class TellCapabilityContracts
{
    /** @return array<class-string, TellCapabilityCardinality> */
    public static function cardinalities(): array {
        return [
            CanRunTell::class => TellCapabilityCardinality::Singleton,
            CanBuildTellAgent::class => TellCapabilityCardinality::Singleton,
            CanResolveTellModel::class => TellCapabilityCardinality::Singleton,
            CanResolveTellSecrets::class => TellCapabilityCardinality::Singleton,
            CanManageTellWorkspace::class => TellCapabilityCardinality::Singleton,
            CanAccessTellConversations::class => TellCapabilityCardinality::Singleton,
            CanReadTellBranchConfiguration::class => TellCapabilityCardinality::OptionalSingleton,
            CanResolveTellConfiguration::class => TellCapabilityCardinality::Singleton,
            CanResolveTellPaths::class => TellCapabilityCardinality::Singleton,
            CanCatalogueTellExtensions::class => TellCapabilityCardinality::Singleton,
            CanContributeTellExtensions::class => TellCapabilityCardinality::OrderedContribution,
            CanContributeTellTools::class => TellCapabilityCardinality::OrderedContribution,
            CanDispatchTellTool::class => TellCapabilityCardinality::Singleton,
            CanObserveTellExecution::class => TellCapabilityCardinality::Singleton,
            CanContributeTellCommands::class => TellCapabilityCardinality::OrderedContribution,
            CanBuildTellApplication::class => TellCapabilityCardinality::Singleton,
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
