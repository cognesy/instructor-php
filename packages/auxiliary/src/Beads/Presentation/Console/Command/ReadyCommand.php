<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\ReadyAction;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReadyCommand extends Command
{

    public function __construct(
        private readonly ReadyAction $action,
    ) {
        parent::__construct('ready');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('List ready issues (no blocking dependencies)')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit', null);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $limitRaw = $input->getOption('limit');
        $limit = $limitRaw === null || $limitRaw === '' ? null : (int) $limitRaw;

        $issues = $this->action->__invoke($file, $limit);
        $this->renderTable($issues, $output);
        return Command::SUCCESS;
    }

    /**
     * @param IssueDTO[] $issues
     */
    private function renderTable(array $issues, OutputInterface $output): void {
        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Status', 'Priority']);
        foreach ($issues as $issue) {
            $table->addRow([
                $issue->id,
                $issue->title,
                $issue->status->value,
                $issue->priority->value,
            ]);
        }
        $table->render();
    }
}
