<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\ListAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{

    public function __construct(
        private readonly ListAction $action,
        private readonly TbdInputMapper $map,
    ) {
        parent::__construct('list');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('List issues')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Status filter')
            ->addOption('assignee', null, InputOption::VALUE_OPTIONAL, 'Assignee filter')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL, 'Comma-separated labels')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit', null);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        if ($file === '') {
            $output->writeln('<error>--file is required</error>');
            return Command::INVALID;
        }
        $statusOpt = $input->getOption('status');
        $status = $statusOpt === null || $statusOpt === '' ? null : StatusEnum::from(strtolower((string) $statusOpt));
        $assignee = $input->getOption('assignee');
        $labels = $this->map->labels((string) $input->getOption('label'));
        $limitRaw = $input->getOption('limit');
        $limit = $limitRaw === null || $limitRaw === '' ? null : (int) $limitRaw;

        $issues = $this->action->__invoke($file, $status, $assignee !== '' ? (string) $assignee : null, $labels, $limit);
        $this->renderTable($issues, $output);
        return Command::SUCCESS;
    }

    /**
     * @param IssueDTO[] $issues
     */
    private function renderTable(array $issues, OutputInterface $output): void {
        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Status', 'Priority', 'Assignee']);
        foreach ($issues as $issue) {
            $table->addRow([
                $issue->id,
                $issue->title,
                $issue->status->value,
                $issue->priority->value,
                $issue->assignee ?? '',
            ]);
        }
        $table->render();
    }
}
