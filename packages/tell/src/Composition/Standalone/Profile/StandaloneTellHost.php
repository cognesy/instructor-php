<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Composition\Standalone\Host\TellHost;
use Cognesy\Tell\Composition\Standalone\Host\TellHostBuilder;
use Cognesy\Tell\Tell;

/** Composition root for framework-free Tell workers and CLI processes. */
final readonly class StandaloneTellHost
{
    /** @param callable(): CanUseTools|null $driverFactory */
    public static function open(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanProvideTellWorkspace $workspaces = null,
    ): Tell {
        $host = self::builder(
            directory: $directory,
            paths: $paths,
            driverFactory: $driverFactory,
            agentBuilder: $agentBuilder,
            cancellation: $cancellation,
            workspaces: $workspaces,
        )->boot();

        return new Tell(
            directory: $directory,
            runner: $host->runner(),
            workspaces: $host->workspace(),
            conversations: $host->conversations(),
            providerCatalogue: $host->providerCatalogue(),
            toolDispatcher: $host->tools(),
            resources: $host,
            cancellation: $cancellation,
        );
    }

    /** @param callable(): CanUseTools|null $driverFactory */
    public static function builder(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanProvideTellWorkspace $workspaces = null,
    ): TellHostBuilder {
        return TellHostBuilder::fromProfile(StandardTellProfile::runtime(
            $directory,
            $paths,
            $driverFactory,
            $agentBuilder,
            $cancellation,
            $workspaces,
        ));
    }

    /** @param callable(): CanUseTools|null $driverFactory */
    public static function cli(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanProvideTellWorkspace $workspaces = null,
    ): TellHost {
        return self::cliBuilder(
            $directory,
            $paths,
            $driverFactory,
            $agentBuilder,
            $cancellation,
            $workspaces,
        )->boot();
    }

    /** @param callable(): CanUseTools|null $driverFactory */
    public static function cliBuilder(
        string $directory,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?CanBuildTellAgent $agentBuilder = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanProvideTellWorkspace $workspaces = null,
    ): TellHostBuilder {
        return TellHostBuilder::fromProfile(StandardTellProfile::cli(
            $directory,
            $paths,
            $driverFactory,
            $agentBuilder,
            $cancellation,
            $workspaces,
        ));
    }
}
