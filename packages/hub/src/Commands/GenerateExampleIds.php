<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateExampleIds extends Command
{
    public function __construct(
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('gen:ids')
            ->setDescription('Generate stable short hex IDs for all examples')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without writing');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $dryRun = $input->getOption('dry-run');

        Cli::outln("Generating example IDs...", [Color::BOLD, Color::YELLOW]);
        Cli::outln();

        $examples = $this->examples->getAllExamples();
        $usedIds = [];
        $generated = 0;
        $skipped = 0;
        $collisions = [];

        // First pass: collect existing IDs
        foreach ($examples as $example) {
            if (!empty($example->id)) {
                if (isset($usedIds[$example->id])) {
                    $collisions[] = [
                        'id' => $example->id,
                        'path1' => $usedIds[$example->id],
                        'path2' => $example->relativePath,
                    ];
                } else {
                    $usedIds[$example->id] = $example->relativePath;
                }
            }
        }

        // Second pass: generate missing IDs
        foreach ($examples as $example) {
            if (!empty($example->id)) {
                $skipped++;
                continue;
            }

            $id = $this->generateId($example->relativePath, $usedIds);
            $usedIds[$id] = $example->relativePath;

            if (!$dryRun) {
                $this->writeIdToFrontMatter($example->runPath, $id);
            }

            Cli::out("  ", []);
            Cli::out("[x{$id}]", [Color::CYAN]);
            Cli::out(" ", []);
            Cli::outln($example->relativePath, [Color::DARK_GRAY]);
            $generated++;
        }

        Cli::outln();

        if (!empty($collisions)) {
            Cli::outln("COLLISIONS DETECTED:", [Color::RED, Color::BOLD]);
            foreach ($collisions as $c) {
                Cli::outln("  ID '{$c['id']}' used by:", [Color::RED]);
                Cli::outln("    - {$c['path1']}", [Color::DARK_GRAY]);
                Cli::outln("    - {$c['path2']}", [Color::DARK_GRAY]);
            }
            Cli::outln();
        }

        Cli::out("Generated: ", [Color::DARK_GRAY]);
        Cli::outln((string)$generated, [Color::GREEN, Color::BOLD]);
        Cli::out("Skipped (already had ID): ", [Color::DARK_GRAY]);
        Cli::outln((string)$skipped, [Color::WHITE]);
        Cli::out("Total: ", [Color::DARK_GRAY]);
        Cli::outln((string)count($examples), [Color::WHITE]);

        if ($dryRun) {
            Cli::outln();
            Cli::outln("(dry run - no files were modified)", [Color::DARK_YELLOW]);
        }

        return empty($collisions) ? Command::SUCCESS : Command::FAILURE;
    }

    private function generateId(string $relativePath, array $usedIds): string {
        $hash = md5($relativePath);

        // Try 4-char hex first
        $id = substr($hash, 0, 4);
        if (!isset($usedIds[$id])) {
            return $id;
        }

        // Collision: extend to 5 chars
        $id = substr($hash, 0, 5);
        if (!isset($usedIds[$id])) {
            return $id;
        }

        // Extend to 6 chars
        $id = substr($hash, 0, 6);
        if (!isset($usedIds[$id])) {
            return $id;
        }

        // Very unlikely: try different offset
        for ($offset = 1; $offset < 28; $offset++) {
            $id = substr($hash, $offset, 4);
            if (!isset($usedIds[$id])) {
                return $id;
            }
        }

        throw new \RuntimeException("Cannot generate unique ID for: {$relativePath}");
    }

    private function writeIdToFrontMatter(string $filePath, string $id): void {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        // Check if file has front-matter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontMatter = $matches[1];
            $rest = $matches[2];

            // Add id field to front-matter (after last existing field)
            $frontMatter = rtrim($frontMatter) . "\nid: '{$id}'";

            $content = "---\n{$frontMatter}\n---\n{$rest}";
        } else {
            // No front-matter exists - add one
            $content = "---\nid: '{$id}'\n---\n{$content}";
        }

        file_put_contents($filePath, $content);
    }
}
