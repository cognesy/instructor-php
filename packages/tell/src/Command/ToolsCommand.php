<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\FieldSelection;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ToolsCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null) {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('tools');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List tools resolved for a built agent')
            ->setHelp(<<<'HELP'
Build an agent and list the tools available to its runtime.

Examples:
  tell tools
  tell tools --agent reviewer --fields=name,description,deferred
  tell tools --json
HELP)
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Tool working directory', '')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated tool fields', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        $options = new TellOptions(
            prompt: 'List tools.',
            agent: (string) $input->getOption('agent'),
            directory: $project,
        );
        $tools = $this->agents->build($options)->profile()->tools->toArray();
        $rows = array_map(static fn (array $tool): array => [
            ...$tool,
            'promptVisible' => $tool['promptSnippet'] !== null,
        ], $tools);
        $fields = FieldSelection::from(
            (string) $input->getOption('fields'),
            ['name', 'description', 'promptVisible'],
            ['name', 'description', 'promptVisible', 'promptSnippet', 'promptGuidelines', 'metadata', 'instructions', 'deferred'],
        );
        $payload = [
            'agent' => $options->agent,
            'count' => count($rows),
            'tools' => $fields->project($rows),
            'help' => [
                'Run `tell describe --agent <name>` for the complete runtime profile.',
                'Use `--fields=name,description,deferred` to select another schema.',
            ],
        ];
        if ($rows === []) {
            $payload['message'] = "Agent {$options->agent} resolved zero tools.";
        }
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Control,
            command: 'tools',
            responsibility: 'Resolve and inspect the tool policy active for a built agent.',
            ownedState: 'Immutable ToolProfileList inside the invocation AgentProfile.',
            input: 'Selected AgentDefinition, discovered capabilities, and optional tool allow-list.',
            output: 'Resolved tool-policy snapshot consumed by data-plane execution.',
            authority: 'Read and narrow the invocation tool set; no tool execution.',
            degradedBehavior: 'Fails before data execution if capability or definition resolution cannot produce a valid profile.',
        );
    }
}
