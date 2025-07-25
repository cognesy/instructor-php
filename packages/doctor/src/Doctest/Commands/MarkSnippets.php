<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Commands;

use Cognesy\Doctor\Doctest\Services\DocRepository;
use Cognesy\Doctor\Markdown\MarkdownFile;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mark',
    description: 'Process Markdown file and add IDs to code snippets'
)]
class MarkSnippets extends Command
{
    public function __construct(
        private DocRepository $docRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source Markdown file path'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output Markdown file path (if not provided, content is displayed on screen)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $sourcePath = $this->getRequiredOption($input, 'source');
            $outputPath = $input->getOption('output');

            // Read source file
            $sourceContent = $this->docRepository->readFile($sourcePath);

            // Process content
            $markdown = MarkdownFile::fromString(
                text: $sourceContent,
                path: $sourcePath
            );

            // Write or display result
            if ($outputPath) {
                $this->docRepository->writeFile($outputPath, $markdown->toString());
                $io->success("Processed content written to: {$outputPath}");
            } else {
                $this->displayContent($markdown->toString(), $io);
            }

            $processedSnippets = iterator_count($markdown->codeBlocks());
            $io->success("Processed {$processedSnippets} code snippets.");

            return Command::SUCCESS;

        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        } catch (RuntimeException $e) {
            $io->error("Processing error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function getRequiredOption(InputInterface $input, string $optionName): string
    {
        $value = $input->getOption($optionName);
        if (empty($value)) {
            throw new InvalidArgumentException("Source file path is required. Use --{$optionName} option.");
        }
        return $value;
    }

    private function displayContent(string $content, SymfonyStyle $io): void
    {
        $io->writeln("Processed Markdown content:");
        $io->writeln(str_repeat('=', 50));
        $io->writeln($content);
        $io->writeln(str_repeat('=', 50));
    }
}