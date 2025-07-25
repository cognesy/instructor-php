<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Views;

use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color;

class PackageGenerationView
{
    public function renderStart(): void
    {
        Cli::outln("Generating package documentation...", [Color::BOLD, Color::YELLOW]);
    }

    public function renderPackageProcessing(string $packageName): void
    {
        Cli::out(" [%] ", Color::DARK_GRAY);
        Cli::grid([[21, $packageName, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
        Cli::out(" > ", Color::DARK_GRAY);
        Cli::out("processing...", [Color::GRAY]);
    }

    public function renderPackageResult(string $packageName, bool $success): void
    {
        Cli::out("> ", [Color::DARK_GRAY]);
        if ($success) {
            Cli::outln("DONE", [Color::GREEN]);
        } else {
            Cli::outln("ERROR", [Color::RED]);
        }
    }

    public function renderInlineStart(string $subpackage): void
    {
        Cli::out("  → Inlining code examples: ", [Color::GRAY]);
        Cli::outln($subpackage, [Color::WHITE, Color::BOLD]);
    }

    public function renderInlineFile(string $docFile, string $subpackage): void
    {
        Cli::out("    [.] ", Color::DARK_GRAY);
        Cli::grid([[35, basename($docFile), STR_PAD_LEFT, [Color::GRAY]]]);
    }

    public function renderInlineResult(string $result): void
    {
        Cli::out(" > ", [Color::DARK_GRAY]);
        
        match($result) {
            'ok' => Cli::outln("INLINED", [Color::GREEN]),
            'skip' => Cli::outln("SKIPPED", [Color::DARK_GRAY]),
            'error' => Cli::outln("ERROR", [Color::RED]),
            default => Cli::outln($result, [Color::GRAY])
        };
    }

    public function renderFinalResult(GenerationResult $result): void
    {
        if ($result->isSuccess()) {
            Cli::out("✓ Packages generated: ", [Color::GREEN]);
            Cli::outln(
                sprintf(
                    "%d packages processed (%.2fs)",
                    $result->filesProcessed,
                    $result->duration
                ),
                [Color::WHITE]
            );
        } else {
            Cli::out("✗ Package generation failed: ", [Color::RED]);
            Cli::outln($result->message, [Color::GRAY]);
            
            if ($result->hasErrors()) {
                foreach ($result->errors as $error) {
                    Cli::outln("  - $error", [Color::RED]);
                }
            }
        }
    }
}