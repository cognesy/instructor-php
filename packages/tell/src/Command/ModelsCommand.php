<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Discovery\TellProviderCatalogue;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\FieldSelection;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ModelsCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly TellAgentFactory $agents) {
        parent::__construct('models');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List preset-declared models for a provider or connection')
            ->addArgument('provider-or-connection', InputArgument::OPTIONAL)
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Project directory', '')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated fields', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $directory = (string) $input->getOption('dir');
            $cwd = getcwd();
            $project = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
            $selector = $input->getArgument('provider-or-connection');
            $rows = (new TellProviderCatalogue($this->agents->paths()))->models($project, is_string($selector) ? $selector : null);
            $fields = FieldSelection::from(
                (string) $input->getOption('fields'),
                ['connection', 'provider', 'defaultModel', 'availableModels'],
                ['connection', 'provider', 'source', 'defaultModel', 'availableModels', 'contextCapacity', 'maxOutputTokens', 'capabilities', 'unknown', 'provenance'],
            );
            (new StructuredOutput($output))->write(['count' => count($rows), 'models' => $fields->project($rows)], json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::INVALID;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'models',
            responsibility: 'Inspect models declared by resolved Polyglot connection presets.',
            ownedState: 'No Tell state; model declarations remain in Polyglot preset files.',
            input: 'Optional provider or connection selector, project directory, and field selection.',
            output: 'Sorted preset-declared model rows with capability provenance and explicit unknowns.',
            authority: 'Read-only local preset inspection; never resolves credentials or opens a network connection.',
            degradedBehavior: 'Rejects unknown selectors without falling back to another provider.',
        );
    }
}
