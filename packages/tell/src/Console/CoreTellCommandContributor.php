<?php

declare(strict_types=1);

namespace Cognesy\Tell\Console;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Command\AgentCommand;
use Cognesy\Tell\Command\AgentsCommand;
use Cognesy\Tell\Command\AuthCommand;
use Cognesy\Tell\Command\BranchCommand;
use Cognesy\Tell\Command\CheckoutCommand;
use Cognesy\Tell\Command\ClearCommand;
use Cognesy\Tell\Command\CompactCommand;
use Cognesy\Tell\Command\ConfigCommand;
use Cognesy\Tell\Command\ContextCommand;
use Cognesy\Tell\Command\DescribeCommand;
use Cognesy\Tell\Command\InitCommand;
use Cognesy\Tell\Command\ModelsCommand;
use Cognesy\Tell\Command\PlanesCommand;
use Cognesy\Tell\Command\ProvidersCommand;
use Cognesy\Tell\Command\ResetCommand;
use Cognesy\Tell\Command\SessionsCommand;
use Cognesy\Tell\Command\ToolCommand;
use Cognesy\Tell\Command\ToolsCommand;
use Cognesy\Tell\Command\WorkspaceInspectionCommand;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanCreateTellRuntime;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Contracts\CanTraceTellExecution;
use Cognesy\Tell\Data\TellCommandDescriptor;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Operational\PlaneMap;
use Cognesy\Tell\Workspace\WorkspaceRepository;
use LogicException;
use Symfony\Component\Console\Command\Command;

/** Symfony is confined to the shell edge; the host sees framework-neutral descriptors. */
final readonly class CoreTellCommandContributor implements CanContributeTellCommands
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanCreateTellRuntime $runtime,
        private CanDispatchTellTool $tools,
        private CanTraceTellExecution $tracer,
        private WorkspaceRepository $workspaces,
        private TellPaths $paths,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanRunTellProtocol $protocol = null,
    ) {}

    #[\Override]
    public function commands(): TellCommandDescriptors {
        $tell = new TellCommand($this->runtime, $this->agents, $this->workspaces, $this->paths);
        $agent = new AgentCommand(
            $this->protocol ?? throw new LogicException('Tell protocol runner is required.'),
            $this->cancellation,
        );
        $commands = [
            $tell,
            $agent,
            new AgentsCommand($this->agents),
            new AuthCommand($this->paths),
            new BranchCommand($this->workspaces),
            new ClearCommand($this->workspaces),
            new CheckoutCommand($this->workspaces),
            new CompactCommand($this->agents, $this->tracer, $this->workspaces),
            new ConfigCommand($this->paths, $this->workspaces),
            new ContextCommand($this->agents, $this->workspaces),
            new DescribeCommand($this->agents),
            new InitCommand(),
            new ModelsCommand($this->paths),
            new ProvidersCommand($this->paths),
            new ResetCommand($this->workspaces),
            new SessionsCommand($this->workspaces),
            new ToolsCommand($this->agents),
            new ToolCommand($this->tools),
            new WorkspaceInspectionCommand('history', $this->workspaces),
            new WorkspaceInspectionCommand('transcript', $this->workspaces),
        ];
        $planeMap = PlaneMap::fromCommands(...$commands);
        array_splice($commands, 15, 0, [new PlanesCommand($planeMap)]);

        return new TellCommandDescriptors(...array_map(
            static fn (Command $command): TellCommandDescriptor => new TellCommandDescriptor(
                name: (string) $command->getName(),
                factory: static fn (): object => $command,
                description: $command->getDescription(),
                aliases: array_values($command->getAliases()),
            ),
            $commands,
        ));
    }
}
