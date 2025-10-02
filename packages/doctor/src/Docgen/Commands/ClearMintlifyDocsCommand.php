<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'clear:mintlify',
    description: 'Clear Mintlify documentation'
)]
class ClearMintlifyDocsCommand extends Command
{
    public function __construct(
        private MintlifyDocumentation $documentation,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('clear')
            ->setDescription('Clear all documentation');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        Cli::outln("Clearing Mintlify documentation...", [Color::BOLD, Color::YELLOW]);

        $result = $this->documentation->clearDocumentation();
        
        if (!$result->isSuccess()) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            foreach ($result->errors as $error) {
                Cli::outln("  • $error", Color::RED);
            }
            return Command::FAILURE;
        }

        $time = round(microtime(true) - $timeStart, 2);
        Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::GREEN]);
        return Command::SUCCESS;
    }
}