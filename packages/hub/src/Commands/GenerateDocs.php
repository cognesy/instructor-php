<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateDocs extends Command
{
    public function __construct(
        private MintlifyDocGenerator $docGen,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('gendocs')
            ->setDescription('Generate documentation')
            ->addArgument('refresh', InputArgument::OPTIONAL, 'Use "fresh" to refresh all files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $arg = $input->getArgument('refresh') ?? '';
        $refresh = $this->isRefresh($arg);
        $timeStart = microtime(true);

        Cli::outln("Generating docs...", [Color::BOLD, Color::YELLOW]);

        try {
            $this->docGen->makeDocs();
        } catch (\Exception $e) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            Cli::outln($e->getMessage(), Color::GRAY);
            return Command::FAILURE;
        }

        $time = round(microtime(true) - $timeStart, 2);
        Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::YELLOW]);

        return Command::SUCCESS;
    }

    private function isRefresh(string $arg): bool {
        return $arg === 'fresh';
    }
}