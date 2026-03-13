<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExcludeCommand extends Command
{
    public function __construct(
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('exclude')
            ->setDescription('Exclude an example from execution by setting skip: true in its front-matter')
            ->addArgument('example', InputArgument::REQUIRED,
                'Example to exclude (index, short ID like x5265, or docname)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arg = $input->getArgument('example');
        $example = $this->examples->argToExample($arg);

        if ($example === null) {
            Cli::outln('');
            Cli::outln("Example not found: {$arg}", [Color::RED]);
            Cli::outln('');
            return Command::FAILURE;
        }

        if ($example->skip) {
            Cli::outln('');
            Cli::outln("[{$example->index}] {$example->group}/{$example->name} is already excluded.", [Color::YELLOW]);
            Cli::outln('');
            return Command::SUCCESS;
        }

        $runPath = $example->runPath;
        $content = file_get_contents($runPath);
        if ($content === false) {
            Cli::outln("Failed to read: {$runPath}", [Color::RED]);
            return Command::FAILURE;
        }

        $updated = $this->addSkipToFrontMatter($content);
        if ($updated === null) {
            Cli::outln('');
            Cli::outln("Could not modify front-matter in: {$runPath}", [Color::RED]);
            Cli::outln('');
            return Command::FAILURE;
        }

        if (file_put_contents($runPath, $updated) === false) {
            Cli::outln("Failed to write: {$runPath}", [Color::RED]);
            return Command::FAILURE;
        }

        Cli::outln('');
        Cli::outln("[{$example->index}] {$example->group}/{$example->name} excluded (skip: true)", [Color::GREEN]);
        Cli::outln("  {$runPath}", [Color::DARK_GRAY]);
        Cli::outln('');

        return Command::SUCCESS;
    }

    private function addSkipToFrontMatter(string $content): ?string
    {
        // Match the closing --- of front-matter
        $pattern = '/^(---\s*\n)(.*?\n)(---\s*\n)/s';

        if (!preg_match($pattern, $content, $matches)) {
            return null;
        }

        $frontMatter = $matches[2];

        // If skip is already there (e.g. skip: false), replace it
        if (preg_match('/^skip:\s*.*/m', $frontMatter)) {
            $frontMatter = preg_replace('/^skip:\s*.*$/m', 'skip: true', $frontMatter);
        } else {
            // Append before closing ---
            $frontMatter .= "skip: true\n";
        }

        return $matches[1] . $frontMatter . $matches[3] . substr($content, strlen($matches[0]));
    }
}
