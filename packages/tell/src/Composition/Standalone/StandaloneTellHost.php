<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Runtime\StandardTellAgentBuilder;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Workspace\WorkspaceRepository;

/** Composition root for framework-free Tell workers and CLI processes. */
final readonly class StandaloneTellHost
{
    public static function open(
        string $directory,
        ?TellAgentFactory $agents = null,
        ?CanProvideCancellationSignal $cancellation = null,
    ): Tell {
        $agents ??= TellAgentFactory::installed();
        $host = self::builder(
            directory: $directory,
            paths: $agents->paths(),
            agentBuilder: new StandardTellAgentBuilder($agents),
            cancellation: $cancellation,
        )->boot();

        return Tell::fromCapabilities(
            directory: $directory,
            runner: $host->runner(),
            workspaces: $host->workspace(),
            conversations: $host->conversations(),
            paths: $host->paths(),
            tools: $host->tools(),
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
        ?WorkspaceRepository $workspaces = null,
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
        ?WorkspaceRepository $workspaces = null,
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
        ?WorkspaceRepository $workspaces = null,
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
