<?php declare(strict_types=1);

namespace Cognesy\Doctor\Lesson\Commands;

use Cognesy\Doctor\Lesson\Config\LessonConfig;
use Cognesy\Doctor\Lesson\Services\LessonService;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Utils\CliMarkdown;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lesson:make',
    description: 'Generate a lesson from an example'
)]
class MakeLesson extends Command
{
    private ExampleRepository $exampleRepo;
    private LessonService $lessonService;

    public function __construct(ExampleRepository $exampleRepo) {
        parent::__construct();
        $this->exampleRepo = $exampleRepo;
        $this->lessonService = new LessonService(new LessonConfig());
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'example',
                InputArgument::REQUIRED,
                'Example number to generate lesson for'
            )
            ->addOption(
                'target-dir',
                't',
                InputOption::VALUE_REQUIRED,
                'Target directory to save the lesson file. If not provided, displays in CLI markdown format.'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Full path to save the lesson file, overriding example properties and target-dir.'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exampleArg = $input->getArgument('example');
        $targetDir = $input->getOption('target-dir');
        $path = $input->getOption('path');

        $example = $this->exampleRepo->argToExample($exampleArg);
        if (is_null($example)) {
            $output->writeln("<error>Example not found: {$exampleArg}</error>");
            return Command::FAILURE;
        }

        $lesson = $this->lessonService->generateLesson($example);

        if ($path) {
            // Save to full path, overriding example properties
            $filepath = $this->saveToPath($lesson, $path);
            $output->writeln("<info>Lesson saved to {$filepath}</info>");
        } elseif ($targetDir) {
            // Save as markdown file using example properties
            $filepath = $this->saveToFile($lesson, $targetDir, $example);
            $output->writeln("<info>Lesson saved to {$filepath}</info>");
        } else {
            // Display in CLI markdown format
            $parser = new CliMarkdown();
            $formattedLesson = $parser->parse($lesson);
            $output->write($formattedLesson);
        }

        return Command::SUCCESS;
    }

    private function saveToFile(string $content, string $targetDir, Example $example): string
    {
        // Create subdirectory structure: targetDir/tab/group/
        $subDir = rtrim($targetDir, '/') . '/' . $example->tab . '/' . $example->group;
        if (!is_dir($subDir)) {
            mkdir($subDir, 0755, true);
        }

        // Use docName for filename
        $filename = $example->docName . '.md';
        $filepath = $subDir . '/' . $filename;
        
        file_put_contents($filepath, $content);
        
        return $filepath;
    }

    private function saveToPath(string $content, string $path): string
    {
        // Ensure the directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
        
        return $path;
    }
}