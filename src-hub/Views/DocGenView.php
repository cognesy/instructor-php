<?php

namespace Cognesy\InstructorHub\Views;

use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;

class DocGenView
{
    public function renderHeader() : void {
        Cli::outln("Updating files...", [Color::GRAY]);
    }

    public function renderFile(Example $example) : void {
        Cli::out(" [.] ", Color::DARK_GRAY);
        Cli::grid([[22, $example->name, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
    }

    public function renderResult(bool $success) : void {
        if (!$success) {
            Cli::out("> ", [Color::DARK_GRAY]);
            Cli::outln("ERROR", [Color::RED]);
            return;
        }
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("DONE", [Color::GREEN]);
    }

    public function renderUpdate(bool $success) : void {
        Cli::out("Updating index... ", [Color::GRAY]);
        if (!$success) {
            Cli::outln("ERROR", [Color::RED]);
            return;
        }
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("DONE", [Color::WHITE]);
    }

    public function renderExists(bool $hasChanged) : void {
        if (!$hasChanged) {
            Cli::out("> ", [Color::DARK_GRAY]);
            Cli::grid([[20, "no changes", STR_PAD_RIGHT, Color::DARK_GRAY]]);
            Cli::out("> ", [Color::DARK_GRAY]);
            Cli::grid([[12, "skipping", STR_PAD_RIGHT, Color::DARK_GRAY]]);
            return;
        }
        // if the file already exists, replace it
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::grid([[20, "found updated example", STR_PAD_RIGHT, Color::GRAY]]);
    }

    public function renderNew() : void {
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::grid([[20, "found new example", STR_PAD_RIGHT, Color::DARK_YELLOW]]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::grid([[12, "copying file", STR_PAD_RIGHT, Color::GRAY]]);
    }
}
