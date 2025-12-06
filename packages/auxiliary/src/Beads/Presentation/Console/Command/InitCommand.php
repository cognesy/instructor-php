<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\InitAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    protected static $defaultName = 'init';

    public function __construct(
        private readonly InitAction $action,
    ) {
        parent::__construct('init');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Initialize a JSONL task file if missing')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $this->action->__invoke($file);
        $output->writeln("<info>Initialized:</info> {$file}");
        return Command::SUCCESS;
    }
}
