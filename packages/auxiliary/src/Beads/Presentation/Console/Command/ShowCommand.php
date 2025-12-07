<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\ShowAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends Command
{

    public function __construct(
        private readonly ShowAction $action,
    ) {
        parent::__construct('show');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Show an issue')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $id = (string) $input->getArgument('id');
        $issue = $this->action->__invoke($file, $id);

        if ($input->getOption('json')) {
            $json = json_encode($issue->toArray(), JSON_PRETTY_PRINT);
            $output->writeln($json ?: '{}');
            return Command::SUCCESS;
        }

        $output->writeln("<info>{$issue->id}</info> {$issue->title}");
        $output->writeln("status: {$issue->status->value} | priority: {$issue->priority->value} | type: {$issue->issueType->value}");
        if ($issue->assignee) {
            $output->writeln("assignee: {$issue->assignee}");
        }
        if (!empty($issue->labels)) {
            $output->writeln('labels: ' . implode(', ', $issue->labels));
        }
        $output->writeln('');
        $output->writeln($issue->description);
        return Command::SUCCESS;
    }
}
