<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\DepRemoveAction;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\DependencyTypeEnum;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DepRemoveCommand extends Command
{

    public function __construct(
        private readonly DepRemoveAction $action,
    ) {
        parent::__construct('dep:rm');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Remove a dependency edge')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('on', null, InputOption::VALUE_REQUIRED, 'Blocking issue id')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Dependency type filter');
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
        $typeOpt = $input->getOption('type');
        $type = null;
        if ($typeOpt !== null && $typeOpt !== '') {
            $type = DependencyTypeEnum::from(strtolower((string) $typeOpt));
        }
        $issue = $this->action->__invoke($file, $id, $on, $type);
        $output->writeln("<info>Dependency removed:</info> {$issue->id} !-> {$on}");
        return Command::SUCCESS;
    }
}
