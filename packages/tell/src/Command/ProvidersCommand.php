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
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProvidersCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly TellAgentFactory $agents) {
        parent::__construct('providers');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List credential-free Polyglot connection and provider metadata')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Project directory', '')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated fields', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
        $catalogue = (new TellProviderCatalogue($this->agents->paths()))->connections($project);
        $fields = FieldSelection::from(
            (string) $input->getOption('fields'),
            ['connection', 'provider', 'defaultModel', 'source'],
            ['connection', 'provider', 'source', 'defaultModel', 'availableModels', 'contextCapacity', 'maxOutputTokens', 'capabilities', 'unknown', 'provenance'],
        );
        (new StructuredOutput($output))->write([
            'count' => count($catalogue['connections']),
            'providers' => $fields->project($catalogue['connections']),
            'errorCount' => count($catalogue['errors']),
            'errors' => $catalogue['errors'],
        ], json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'providers',
            responsibility: 'Inspect Polyglot-owned connection presets and declared provider capabilities.',
            ownedState: 'No Tell state; preset and driver metadata remain Polyglot-owned.',
            input: 'Optional project directory and field selection.',
            output: 'Sorted, redacted connection/provider metadata with explicit unknown fields.',
            authority: 'Read-only local preset inspection; never resolves credentials or opens a network connection.',
            degradedBehavior: 'Reports malformed preset diagnostics while preserving other catalogue rows.',
        );
    }
}
