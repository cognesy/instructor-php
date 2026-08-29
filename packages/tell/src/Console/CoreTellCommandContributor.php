<?php

declare(strict_types=1);

namespace Cognesy\Tell\Console;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
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
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Data\TellCommandDescriptor;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Operational\PlaneMap;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Symfony\Component\Console\Command\Command;

/** Symfony is confined to the shell edge; the host sees framework-neutral descriptors. */
final readonly class CoreTellCommandContributor implements CanContributeTellCommands
{
    public function __construct(
        private TellAgentFactory $agents,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanRunTellProtocol $protocol = null,
    ) {}

    public function commands(): TellCommandDescriptors {
        $tell = new TellCommand($this->agents);
        $agent = new AgentCommand($this->agents, $this->cancellation, $this->protocol);
        $commands = [
            $tell,
            $agent,
            new AgentsCommand($this->agents),
            new AuthCommand($this->agents),
            new BranchCommand($this->agents),
            new ClearCommand($this->agents),
            new CheckoutCommand($this->agents),
            new CompactCommand($this->agents),
            new ConfigCommand($this->agents),
            new ContextCommand($this->agents),
            new DescribeCommand($this->agents),
            new InitCommand(),
            new ModelsCommand($this->agents),
            new ProvidersCommand($this->agents),
            new ResetCommand($this->agents),
            new SessionsCommand($this->agents),
            new ToolsCommand($this->agents),
            new ToolCommand($this->agents),
            new WorkspaceInspectionCommand('history', $this->agents),
            new WorkspaceInspectionCommand('transcript', $this->agents),
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
