<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Views;

use Cognesy\Doctor\Docgen\Data\FileProcessingResult;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\Utils\Cli\Color;

class ExampleGenerationView
{
    public function renderStart(): void
    {
        Cli::outln("Generating example documentation...", [Color::BOLD, Color::YELLOW]);
    }

    public function renderExampleProcessing(Example $example): void
    {
        Cli::out(" [.] ", Color::DARK_GRAY);
        Cli::grid([[22, $example->name, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
    }

    public function renderFileResult(FileProcessingResult $result): void
    {
        Cli::out("> ", [Color::DARK_GRAY]);
        
        match($result->action) {
            'created' => $this->renderCreated($result->message),
            'updated' => $this->renderUpdated($result->message),
            'skipped' => $this->renderSkipped($result->message),
            'error' => $this->renderError($result->message),
        };
    }

    public function renderFinalResult(GenerationResult $result): void
    {
        if ($result->isSuccess()) {
            Cli::out("✓ Examples generated: ", [Color::GREEN]);
            Cli::outln(
                sprintf(
                    "%d processed, %d created, %d updated, %d skipped (%.2fs)",
                    $result->filesProcessed,
                    $result->filesCreated,
                    $result->filesUpdated,
                    $result->filesSkipped,
                    $result->duration
                ),
                [Color::WHITE]
            );
        } else {
            Cli::out("✗ Example generation failed: ", [Color::RED]);
            Cli::outln($result->message, [Color::GRAY]);
            
            if ($result->hasErrors()) {
                foreach ($result->errors as $error) {
                    Cli::outln("  - $error", [Color::RED]);
                }
            }
        }
    }

    private function renderCreated(string $message): void
    {
        Cli::grid([[20, "new example", STR_PAD_RIGHT, Color::DARK_YELLOW]]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("CREATED", [Color::GREEN]);
    }

    private function renderUpdated(string $message): void
    {
        Cli::grid([[20, "updated example", STR_PAD_RIGHT, Color::GRAY]]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("UPDATED", [Color::GREEN]);
    }

    private function renderSkipped(string $message): void
    {
        Cli::grid([[20, "no changes", STR_PAD_RIGHT, Color::DARK_GRAY]]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("SKIPPED", [Color::DARK_GRAY]);
    }

    private function renderError(string $message): void
    {
        Cli::grid([[20, "error", STR_PAD_RIGHT, Color::RED]]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("ERROR", [Color::RED]);
    }
}