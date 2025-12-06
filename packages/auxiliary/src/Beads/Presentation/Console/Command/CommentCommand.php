<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console\Command;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CommentAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommentCommand extends Command
{
    protected static $defaultName = 'comment';

    public function __construct(
        private readonly CommentAction $action,
    ) {
        parent::__construct('comment');
    }

    #[\Override]
    protected function configure(): void {
        $this->setDescription('Add a comment to an issue')
            ->addArgument('id', InputArgument::REQUIRED, 'Issue id')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSONL file')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Author')
            ->addOption('text', null, InputOption::VALUE_REQUIRED, 'Comment text')
            ->addOption('created-at', null, InputOption::VALUE_OPTIONAL, 'Created at');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = (string) $input->getOption('file');
        $author = (string) $input->getOption('author');
        $text = (string) $input->getOption('text');
        if ($file === '' || $author === '' || $text === '') {
            $output->writeln('<error>--file, --author, --text are required</error>');
            return Command::INVALID;
        }
        $id = (string) $input->getArgument('id');
        $opt = fn(string $name) => ($input->getOption($name) === null || $input->getOption($name) === '') ? null : (string) $input->getOption($name);
        $issue = $this->action->__invoke(
            $file,
            $id,
            $author,
            $text,
            $opt('created-at'),
        );
        $output->writeln("<info>Comment added:</info> {$issue->id}");
        return Command::SUCCESS;
    }
}
