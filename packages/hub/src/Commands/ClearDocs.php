<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearDocs extends Command
{
    public function __construct(
        private MintlifyDocGenerator $docGen,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('cleardocs')
            ->setDescription('Clear all documentation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        Cli::outln("Clearing all docs...", [Color::BOLD, Color::YELLOW]);

        try {
            $this->docGen->clearDocs();
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