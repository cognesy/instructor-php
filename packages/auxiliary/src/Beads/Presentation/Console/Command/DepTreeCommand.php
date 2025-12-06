<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\DepTreeAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DepTreeCommand extends Command
{
    protected static $defaultName = 'dep:tree';

    public function __construct(
        private readonly DepTreeAction $action,
        private readonly TbdInputMapper $map,
    ) {
        parent::__construct('dep:tree');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Show dependency edges')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('direction', null, InputOption::VALUE_OPTIONAL, 'Direction (up|down|both)', 'down');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $id = (string) $input->getArgument('id');
        $direction = $this->map->direction((string) $input->getOption('direction'));
        $edges = $this->action->__invoke($file, $id, $direction);

        if ($direction === 'both') {
            $output->writeln('<info>Downstream</info>');
            $this->renderEdges($edges['down'], $output);
            $output->writeln('<info>Upstream</info>');
            $this->renderEdges($edges['up'], $output);
            return Command::SUCCESS;
        }

        $this->renderEdges($edges, $output);
        return Command::SUCCESS;
    }

    private function renderEdges(array $edges, OutputInterface $output): void {
        $table = new Table($output);
        $table->setHeaders(['from', 'to', 'type']);
        foreach ($edges as $edge) {
            $table->addRow([$edge['from'], $edge['to'], $edge['type']]);
        }
        $table->render();
    }
}
