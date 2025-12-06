<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CreateAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends Command
{
    protected static $defaultName = 'create';

    public function __construct(
        private readonly CreateAction $action,
        private readonly TbdInputMapper $map,
    ) {
        parent::__construct('create');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Create a new issue')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Description')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Type (task|bug|epic|story|feature|chore)')
            ->addOption('priority', null, InputOption::VALUE_OPTIONAL, 'Priority (0-4)')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Status')
            ->addOption('assignee', null, InputOption::VALUE_OPTIONAL, 'Assignee')
            ->addOption('labels', null, InputOption::VALUE_OPTIONAL, 'Comma-separated labels')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Explicit id')
            ->addOption('created-at', null, InputOption::VALUE_OPTIONAL, 'ISO date')
            ->addOption('external-ref', null, InputOption::VALUE_OPTIONAL, 'External reference')
            ->addOption('acceptance', null, InputOption::VALUE_OPTIONAL, 'Acceptance criteria')
            ->addOption('notes', null, InputOption::VALUE_OPTIONAL, 'Notes')
            ->addOption('estimate-min', null, InputOption::VALUE_OPTIONAL, 'Estimate minutes')
            ->addOption('design', null, InputOption::VALUE_OPTIONAL, 'Design link or notes');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        $title = (string) $input->getOption('title');
        $description = (string) $input->getOption('description');
        if ($file === '' || $title === '' || $description === '') {
            $output->writeln('<error>--file, --title, --description are required</error>');
            return Command::INVALID;
        }

        $labels = $this->map->labels((string) $input->getOption('labels'));
        $estimateRaw = $input->getOption('estimate-min');
        $estimate = $estimateRaw === null || $estimateRaw === '' ? null : (int) $estimateRaw;
        $opt = fn(string $name) => ($input->getOption($name) === null || $input->getOption($name) === '') ? null : (string) $input->getOption($name);

        $issue = $this->action->__invoke(
            $file,
            $title,
            $description,
            $opt('type'),
            $opt('priority'),
            $opt('status'),
            $opt('assignee'),
            $labels,
            $opt('id'),
            $opt('created-at'),
            $opt('external-ref'),
            $opt('acceptance'),
            $opt('notes'),
            $estimate,
            $opt('design'),
        );

        $output->writeln("<info>Created:</info> {$issue->id} {$issue->title}");
        return Command::SUCCESS;
    }
}
