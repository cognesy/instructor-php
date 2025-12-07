<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\UpdateAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{

    public function __construct(
        private readonly UpdateAction $action,
        private readonly TbdInputMapper $map,
    ) {
        parent::__construct('update');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Update an issue')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Title')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Description')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Status')
            ->addOption('priority', null, InputOption::VALUE_OPTIONAL, 'Priority (0-4)')
            ->addOption('assignee', null, InputOption::VALUE_OPTIONAL, 'Assignee')
            ->addOption('labels', null, InputOption::VALUE_OPTIONAL, 'Comma-separated labels')
            ->addOption('acceptance', null, InputOption::VALUE_OPTIONAL, 'Acceptance criteria')
            ->addOption('notes', null, InputOption::VALUE_OPTIONAL, 'Notes')
            ->addOption('estimate-min', null, InputOption::VALUE_OPTIONAL, 'Estimate minutes')
            ->addOption('close-reason', null, InputOption::VALUE_OPTIONAL, 'Close reason')
            ->addOption('updated-at', null, InputOption::VALUE_OPTIONAL, 'Updated at')
            ->addOption('design', null, InputOption::VALUE_OPTIONAL, 'Design')
            ->addOption('external-ref', null, InputOption::VALUE_OPTIONAL, 'External reference');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        $id = (string) $input->getArgument('id');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $labels = $this->map->labels((string) $input->getOption('labels'));
        $estimateRaw = $input->getOption('estimate-min');
        $estimate = $estimateRaw === null || $estimateRaw === '' ? null : (int) $estimateRaw;
        $opt = fn(string $name) => ($input->getOption($name) === null || $input->getOption($name) === '') ? null : (string) $input->getOption($name);

        $issue = $this->action->__invoke(
            $file,
            $id,
            $opt('title'),
            $opt('description'),
            $opt('status'),
            $opt('priority'),
            $opt('assignee'),
            $labels,
            $opt('acceptance'),
            $opt('notes'),
            $estimate,
            $opt('close-reason'),
            $opt('updated-at'),
            $opt('design'),
            $opt('external-ref'),
        );

        $output->writeln("<info>Updated:</info> {$issue->id}");
        return Command::SUCCESS;
    }
}
