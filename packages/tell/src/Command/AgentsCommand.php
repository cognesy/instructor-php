<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

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

final class AgentsCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null)
    {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('agents');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('List available agent definitions')
            ->setHelp(<<<'HELP'
List agent definitions discovered from package, user, and project locations.

Examples:
  tell agents
  tell agents --fields=name,description
  tell agents --json
HELP)
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Project directory', '')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated fields: name,label,description', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        $registry = $this->agents->definitions($project);
        $definitions = [];
        foreach ($registry->all() as $definition) {
            $definitions[] = [
                'name' => $definition->name,
                'label' => $definition->label(),
                'description' => $definition->description,
            ];
        }
        usort(
            $definitions,
            static fn (array $left, array $right): int => $left['name'] <=> $right['name'],
        );
        $errors = $registry->errors();
        ksort($errors);
        $fields = FieldSelection::from(
            (string) $input->getOption('fields'),
            ['name', 'label', 'description'],
            ['name', 'label', 'description'],
        );
        $errorRows = [];
        foreach ($errors as $path => $error) {
            $errorRows[] = ['path' => $path, 'error' => $error];
        }
        $payload = [
            'count' => count($definitions),
            'agents' => $fields->project($definitions),
            'errorCount' => count($errorRows),
            'errors' => $errorRows,
            'help' => [
                'Run `tell describe --agent <name>` for runtime details.',
                'Run `tell "<prompt>" --agent <name>` to use an agent.',
            ],
        ];
        if ($definitions === []) {
            $payload['message'] = 'No agent definitions found in package, user, or project locations.';
        }
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'agents',
            responsibility: 'Inspect configured agent-definition inventory and discovery diagnostics.',
            ownedState: 'Definition files remain owned by package, user, and project stores; Tell is read-only.',
            input: 'Package, user, and project definition locations.',
            output: 'AgentDefinition inventory and bounded discovery errors.',
            authority: 'Read agent configuration; no create, update, or delete authority.',
            degradedBehavior: 'Reports source-specific discovery errors; execution fails explicitly if the selected definition is unavailable.',
        );
    }
}
