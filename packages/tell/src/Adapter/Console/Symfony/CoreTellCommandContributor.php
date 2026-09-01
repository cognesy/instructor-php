<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Adapter\Console\Command\AgentCommand;
use Cognesy\Tell\Adapter\Console\Command\AgentsCommand;
use Cognesy\Tell\Adapter\Console\Command\AuthCommand;
use Cognesy\Tell\Adapter\Console\Command\BranchCommand;
use Cognesy\Tell\Adapter\Console\Command\CheckoutCommand;
use Cognesy\Tell\Adapter\Console\Command\ClearCommand;
use Cognesy\Tell\Adapter\Console\Command\CompactCommand;
use Cognesy\Tell\Adapter\Console\Command\ConfigCommand;
use Cognesy\Tell\Adapter\Console\Command\ContextCommand;
use Cognesy\Tell\Adapter\Console\Command\DescribeCommand;
use Cognesy\Tell\Adapter\Console\Command\InitCommand;
use Cognesy\Tell\Adapter\Console\Command\ModelsCommand;
use Cognesy\Tell\Adapter\Console\Command\PlanesCommand;
use Cognesy\Tell\Adapter\Console\Command\ProvidersCommand;
use Cognesy\Tell\Adapter\Console\Command\ResetCommand;
use Cognesy\Tell\Adapter\Console\Command\SessionsCommand;
use Cognesy\Tell\Adapter\Console\Command\ToolCommand;
use Cognesy\Tell\Adapter\Console\Command\ToolsCommand;
use Cognesy\Tell\Adapter\Console\Command\WorkspaceInspectionCommand;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanContributeTellCommands;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanRunTellProtocol;
use Cognesy\Tell\Core\Contract\Secrets\CanManageTellCredentials;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Data\TellCommandDescriptor;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Adapter\Console\Operational\PlaneMap;
use LogicException;
use Symfony\Component\Console\Command\Command;

/** Symfony is confined to the shell edge; the host sees framework-neutral descriptors. */
final readonly class CoreTellCommandContributor implements CanContributeTellCommands
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanCreateTellRuntime $runtime,
        private CanDispatchTellTool $tools,
        private CanAccessTellConversations $conversations,
        private CanManageTellWorkspace $workspaces,
        private CanReadTellBranchConfiguration $branchConfiguration,
        private CanManageTellCredentials $credentials,
        private CanCatalogueTellProviders $providers,
        private TellPaths $paths,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanRunTellProtocol $protocol = null,
    ) {}

    #[\Override]
    public function commands(): TellCommandDescriptors {
        $tell = new TellCommand(
            $this->runtime,
            $this->agents,
            $this->workspaces,
            $this->branchConfiguration,
            $this->paths,
        );
        $agent = new AgentCommand(
            $this->protocol ?? throw new LogicException('Tell protocol runner is required.'),
            $this->cancellation,
        );
        $commands = [
            $tell,
            $agent,
            new AgentsCommand($this->agents),
            new AuthCommand($this->credentials),
            new BranchCommand($this->conversations),
            new ClearCommand($this->conversations),
            new CheckoutCommand($this->conversations),
            new CompactCommand($this->conversations),
            new ConfigCommand($this->conversations),
            new ContextCommand($this->conversations),
            new DescribeCommand($this->agents),
            new InitCommand($this->workspaces),
            new ModelsCommand($this->providers),
            new ProvidersCommand($this->providers),
            new ResetCommand($this->conversations),
            new SessionsCommand($this->conversations),
            new ToolsCommand($this->agents),
            new ToolCommand($this->tools),
            new WorkspaceInspectionCommand('history', $this->conversations),
            new WorkspaceInspectionCommand('transcript', $this->conversations),
        ];
        $planeMap = PlaneMap::fromCommands(...$commands);
        array_splice($commands, 15, 0, [new PlanesCommand($planeMap)]);

        return new TellCommandDescriptors(...array_map(
            static fn (Command $command): TellCommandDescriptor => new TellCommandDescriptor(
                name: (string) $command->getName(),
                factory: static fn (): object => $command,
                description: $command->getDescription(),
            ),
            $commands,
        ));
    }
}
