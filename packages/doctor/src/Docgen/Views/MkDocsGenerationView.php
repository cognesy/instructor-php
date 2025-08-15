<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Views;

use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\Utils\Cli\Color;

class MkDocsGenerationView
{
    public function renderStart(): void {
        Cli::outln("Generating MkDocs documentation...", [Color::BOLD, Color::CYAN]);
        Cli::outln("");
    }

    public function renderPackageProcessing(string $package): void {
        Cli::out(" [📦] ", Color::BLUE);
        Cli::grid([[25, "Processing package", STR_PAD_RIGHT, [Color::GRAY]]]);
        Cli::out(" > ", Color::DARK_GRAY);
        Cli::grid([[20, $package, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
    }

    public function renderInlineStart(string $package): void {
        Cli::out(" [🔗] ", Color::YELLOW);
        Cli::grid([[25, "Inlining code blocks", STR_PAD_RIGHT, [Color::GRAY]]]);
        Cli::out(" > ", Color::DARK_GRAY);
        Cli::grid([[20, $package, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
    }

    public function renderPackageResult(string $package, bool $success): void {
        if ($success) {
            Cli::out(" > ", [Color::DARK_GRAY]);
            Cli::outln("DONE", [Color::GREEN]);
        } else {
            Cli::out(" > ", [Color::DARK_GRAY]);
            Cli::outln("ERROR", [Color::RED]);
        }
        Cli::outln("");
    }

    public function renderExampleProcessing(Example $example): void {
        Cli::out(" [📄] ", Color::MAGENTA);
        Cli::grid([[25, "Processing example", STR_PAD_RIGHT, [Color::GRAY]]]);
        Cli::out(" > ", Color::DARK_GRAY);
        Cli::grid([[35, $example->name, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
    }

    public function renderExampleResult(Example $example, string $action): void {
        $color = match($action) {
            'created' => Color::GREEN,
            'updated' => Color::YELLOW,
            'skipped' => Color::DARK_GRAY,
            'error' => Color::RED,
            default => Color::WHITE,
        };

        $status = match($action) {
            'created' => 'CREATED',
            'updated' => 'UPDATED',
            'skipped' => 'SKIPPED',
            'error' => 'ERROR',
            default => 'DONE',
        };

        Cli::out(" > ", [Color::DARK_GRAY]);
        Cli::outln($status, [$color]);
    }

    public function renderConfigUpdate(): void {
        Cli::out(" [⚙️] ", Color::CYAN);
        Cli::grid([[25, "Updating mkdocs.yml", STR_PAD_RIGHT, [Color::GRAY]]]);
        Cli::out(" > ", Color::DARK_GRAY);
        Cli::outln("DONE", [Color::GREEN]);
        Cli::outln("");
    }

    public function renderFinalResult(GenerationResult $result): void {
        Cli::outln("");
        
        if ($result->isSuccess()) {
            Cli::outln("✅ MkDocs Documentation Generation Complete", [Color::BOLD, Color::GREEN]);
        } else {
            Cli::outln("❌ MkDocs Documentation Generation Failed", [Color::BOLD, Color::RED]);
        }

        Cli::outln("");

        // Stats
        if ($result->filesProcessed > 0) {
            Cli::grid([
                [20, "Files processed:", STR_PAD_RIGHT, [Color::GRAY]],
                [10, (string)$result->filesProcessed, STR_PAD_LEFT, [Color::WHITE]]
            ]);
        }

        if ($result->filesCreated > 0) {
            Cli::grid([
                [20, "Files created:", STR_PAD_RIGHT, [Color::GRAY]],
                [10, (string)$result->filesCreated, STR_PAD_LEFT, [Color::GREEN]]
            ]);
        }

        if ($result->filesUpdated > 0) {
            Cli::grid([
                [20, "Files updated:", STR_PAD_RIGHT, [Color::GRAY]],
                [10, (string)$result->filesUpdated, STR_PAD_LEFT, [Color::YELLOW]]
            ]);
        }

        if ($result->filesSkipped > 0) {
            Cli::grid([
                [20, "Files skipped:", STR_PAD_RIGHT, [Color::GRAY]],
                [10, (string)$result->filesSkipped, STR_PAD_LEFT, [Color::DARK_GRAY]]
            ]);
        }

        if ($result->duration > 0) {
            Cli::grid([
                [20, "Duration:", STR_PAD_RIGHT, [Color::GRAY]],
                [10, sprintf("%.2fs", $result->duration), STR_PAD_LEFT, [Color::CYAN]]
            ]);
        }

        // Errors
        if (!empty($result->errors)) {
            Cli::outln("");
            Cli::outln("Errors:", [Color::BOLD, Color::RED]);
            foreach ($result->errors as $error) {
                Cli::outln("  • $error", [Color::RED]);
            }
        }

        // Message
        if (!empty($result->message)) {
            Cli::outln("");
            Cli::outln($result->message, $result->isSuccess() ? [Color::GREEN] : [Color::RED]);
        }

        Cli::outln("");
    }
}