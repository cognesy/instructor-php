<?php declare(strict_types=1);

namespace Cognesy\Doctor\Lesson\Commands;

use Cognesy\Doctor\Freeze\Freeze;
use Cognesy\Doctor\Freeze\FreezeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lesson:image',
    description: 'Generate a beautiful image from a markdown lesson file using Freeze'
)]
class MakeLessonImage extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Path to the markdown file to convert'
            )
            ->addArgument(
                'output',
                InputArgument::REQUIRED,
                'Output path for the generated image (.png, .svg, .webp)'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourcePath = $input->getArgument('source');
        $outputPath = $input->getArgument('output');

        // Validate source file exists
        if (!file_exists($sourcePath)) {
            $output->writeln("<error>Source file not found: {$sourcePath}</error>");
            return Command::FAILURE;
        }

        // Validate source is a markdown file
        if (!str_ends_with(strtolower($sourcePath), '.md')) {
            $output->writeln("<error>Source must be a markdown file (.md)</error>");
            return Command::FAILURE;
        }

        // Create output directory if needed
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $result = Freeze::file($sourcePath)
                ->output($outputPath)
                ->run();

            if ($result->failed()) {
                $output->writeln("<error>Freeze execution failed</error>");
                $output->writeln("<error>Command: {$result->command}</error>");
                $output->writeln("<error>Error: {$result->errorOutput}</error>");
                $output->writeln("<error>Output: {$result->output}</error>");
                return Command::FAILURE;
            }

            if (!$result->hasOutputFile()) {
                $output->writeln("<error>Image file was not created</error>");
                return Command::FAILURE;
            }
            
            $output->writeln("<info>Successfully generated image!</info>");

            $output->writeln("<info>Image saved to: {$outputPath}</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error generating image: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
