<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CompactAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompactCommand extends Command
{
    protected static $defaultName = 'compact';

    public function __construct(
        private readonly CompactAction $action,
    ) {
        parent::__construct('compact');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Compact/sort the JSONL file by id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $issues = $this->action->__invoke($file);

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Status']);
        foreach ($issues as $issue) {
            $table->addRow([$issue->id, $issue->title, $issue->status->value]);
        }
        $table->render();
        $output->writeln("<info>Compacted:</info> {$file}");
        return Command::SUCCESS;
    }
}
