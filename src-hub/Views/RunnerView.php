<?php

namespace Cognesy\InstructorHub\Views;

use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Exception;

class RunnerView
{
    public function runStart(Example $example) : void {
        // execute run.php and print the output to CLI
        Cli::grid([[4, "[".$example->index."]", STR_PAD_RIGHT, Color::DARK_GRAY]]);
        Cli::grid([[30, $example->name, STR_PAD_RIGHT, Color::WHITE]]);
        Cli::grid([[13, "> running ...", STR_PAD_RIGHT, Color::DARK_GRAY]]);
    }

    public function executionError(Example $example, Exception $e) : void {
        Cli::outln();
        Cli::out("[!] ", Color::DARK_YELLOW);
        Cli::outln("Failure while running example: {$example->name}", Color::RED);
        Cli::outln();
        Cli::outln("[Message]", Color::DARK_GRAY);
        Cli::outln($e->getMessage(), Color::GRAY);
        Cli::outln();
        Cli::outln("[Trace]", Color::DARK_GRAY);
        Cli::outln($e->getTraceAsString(), Color::GRAY);
    }

    public function stats(int $correct, int $incorrect, int $total) : void {
        $correctPercent = $this->percent($correct, $total);
        $incorrectPercent = $this->percent($incorrect, $total);
        Cli::outln();
        Cli::outln();
        Cli::outln("RESULTS:", [Color::YELLOW, Color::BOLD]);
        Cli::out("[+]", Color::GREEN);
        Cli::outln(" Correct runs ..... $correct ($correctPercent%)");
        Cli::out("[-]", Color::RED);
        Cli::outln(" Incorrect runs ... $incorrect ($incorrectPercent%)");
        Cli::outln("Total ................ $total (100%)", [Color::BOLD, Color::WHITE]);
        Cli::outln();
    }

    public function renderOutput(array $errors, float $timeElapsed) : void {
        Cli::grid([[1, ">", STR_PAD_RIGHT, Color::DARK_GRAY]]);
        if (!empty($errors)) {
            Cli::grid([[8, "ERROR", STR_PAD_BOTH, [Color::WHITE, Color::BG_RED]]]);
        } else {
            Cli::grid([[8, "OK", STR_PAD_BOTH, [Color::WHITE, Color::BG_GREEN]]]);
        }
        $this->printTimeElapsed($timeElapsed);
        Cli::outln();
    }

    public function onError() : void {
        Cli::outln();
        Cli::out("[!] ", Color::DARK_YELLOW);
        Cli::outln("Terminating - error encountered...", Color::YELLOW);
    }

    public function onStop() : void {
        Cli::outln();
        Cli::out("[!] ", Color::DARK_YELLOW);
        Cli::outln("Terminating - set limit reached...", Color::YELLOW);
    }

    public function displayErrors(array $errors, bool $displayErrors) : void {
        if ($displayErrors && !empty($errors)) {
            Cli::outln();
            Cli::outln();
            Cli::outln("ERRORS:", [Color::YELLOW, Color::BOLD]);
            foreach ($errors as $name => $group) {
                Cli::outln("[$name]", Color::DARK_YELLOW);
                foreach ($group as $error) {
                    Cli::outln('---', Color::DARK_YELLOW);
                    Cli::margin($error->output, 4, Color::RED, Color::GRAY);
                    Cli::outln();
                }
            }
        }
    }

    public function printTimeElapsed(float $totalTime) {
        Cli::out("(", [Color::DARK_GRAY, Color::BG_BLACK]);
        Cli::grid([[10, (round($totalTime, 2) . " sec"), STR_PAD_LEFT, Color::DARK_GRAY]]);
        Cli::out(")", [Color::DARK_GRAY]);
    }

    private function percent(int $value, int $total) : int {
        return ($total == 0) ? 0 : round(($value / $total) * 100, 0);
    }
}