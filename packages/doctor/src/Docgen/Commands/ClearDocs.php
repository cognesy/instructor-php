<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearDocs extends Command
{
    public function __construct(
        private MintlifyDocumentation $documentation,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('clear')
            ->setDescription('Clear all documentation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        Cli::outln("Clearing all docs...", [Color::BOLD, Color::YELLOW]);

        try {
            $this->documentation->clearDocumentation();
        } catch (\Exception $e) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            Cli::outln($e->getMessage(), Color::GRAY);
            return Command::FAILURE;
        }

        $time = round(microtime(true) - $timeStart, 2);
        Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::YELLOW]);

        return Command::SUCCESS;
    }
}