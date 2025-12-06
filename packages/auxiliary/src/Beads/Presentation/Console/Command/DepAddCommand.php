<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\DepAddAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DepAddCommand extends Command
{
    protected static $defaultName = 'dep:add';

    public function __construct(
        private readonly DepAddAction $action,
        private readonly TbdInputMapper $map,
    ) {
        parent::__construct('dep:add');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Add a dependency edge')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('on', null, InputOption::VALUE_REQUIRED, 'Blocking issue id')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Dependency type', 'blocks')
            ->addOption('created-by', null, InputOption::VALUE_OPTIONAL, 'Created by', 'tbd')
            ->addOption('created-at', null, InputOption::VALUE_OPTIONAL, 'Created at');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        $on = (string) $input->getOption('on');
        if ($file === '' || $on === '') {
            $output->writeln('<error>--file and --on are required</error>');
            return Command::INVALID;
        }
        $id = (string) $input->getArgument('id');
        $opt = fn(string $name) => ($input->getOption($name) === null || $input->getOption($name) === '') ? null : (string) $input->getOption($name);
        $issue = $this->action->__invoke(
            $file,
            $id,
            $on,
            $opt('type') ?? 'blocks',
            $opt('created-by') ?? 'tbd',
            $opt('created-at'),
        );
        $output->writeln("<info>Dependency added:</info> {$issue->id} depends on {$on}");
        return Command::SUCCESS;
    }
}
