<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CloseAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CloseCommand extends Command
{
    public function __construct(
        private readonly CloseAction $action,
    ) {
        parent::__construct('close');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Close an issue')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('reason', null, InputOption::VALUE_OPTIONAL, 'Close reason')
            ->addOption('closed-at', null, InputOption::VALUE_OPTIONAL, 'Closed at (ISO)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $id = (string) $input->getArgument('id');
        $opt = fn(string $name) => ($input->getOption($name) === null || $input->getOption($name) === '') ? null : (string) $input->getOption($name);

        $issue = $this->action->__invoke(
            $file,
            $id,
            $opt('reason'),
            $opt('closed-at'),
        );

        $output->writeln("<info>Closed:</info> {$issue->id}");
        return Command::SUCCESS;
    }
}
