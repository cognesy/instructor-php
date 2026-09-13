<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Adapter\Console\Render\FieldSelection;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ModelsCommand extends Command
{
    public function __construct(private readonly CanCatalogueTellProviders $providers) {
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
            $rows = $this->providers->models($project, is_string($selector) ? $selector : null);
            $fields = FieldSelection::from(
                (string) $input->getOption('fields'),
                ['provider', 'model', 'status', 'contextCapacity'],
                ['provider', 'model', 'status', 'connections', 'defaultFor', 'contextCapacity', 'maxOutputTokens', 'modalities', 'capabilities', 'catalogSource', 'catalogVersion', 'provenance'],
            );
            (new StructuredOutput($output))->write(['count' => count($rows), 'models' => $fields->project($rows)], json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::INVALID;
        }
    }

}
