<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Utils\CliMarkdown;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowExample extends Command
{
    public function __construct(
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('show')
            ->setDescription('Show example')
            ->addArgument('example', InputArgument::REQUIRED, 'Example name or index to show');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = $input->getArgument('example');

        if (empty($file)) {
            Cli::outln("Please specify an example to show");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return Command::FAILURE;
        }

        $example = $this->examples->argToExample($file);
        if (is_null($example)) {
            Cli::outln("Example not found", [Color::RED]);
            return Command::FAILURE;
        }

        Cli::out("Example: ", [Color::DARK_GRAY]);
        Cli::outln($example->name, [Color::BOLD, Color::WHITE]);
        Cli::outln("---");
        Cli::outln();
        Cli::outln();

        $parser = new CliMarkdown;
        $md = $parser->parse($example->content);
        Cli::outln($md);
        Cli::outln("---");
        Cli::outln();
        Cli::outln("Run this example:", [Color::DARK_YELLOW]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("./bin/instructor-hub run {$example->name}", [Color::BOLD, Color::WHITE]);
        Cli::outln();

        return Command::SUCCESS;
    }
}