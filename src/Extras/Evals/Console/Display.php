<?php

namespace Cognesy\Instructor\Extras\Evals\Console;

use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation\SelectObservations;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Str;
use Exception;

class Display
{
    private int $terminalWidth = 120;

    public function __construct(array $options = []) {
        $this->terminalWidth = Console::getWidth();
    }

    public function header(Experiment $experiment) : void {
        Console::println('');
        Console::printColumns([
            [22, ' EXPERIMENT (' . Str::limit(text: $experiment->id(), limit: 4, align: STR_PAD_LEFT, fit: false) . ") ", STR_PAD_RIGHT, [Color::BG_BLUE, Color::WHITE, Color::BOLD]],
            [$this->flex(22, 30, -2), ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
            [30, ' ' . $experiment->startedAt()->format('Y-m-d H:i:s') . ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
        ], $this->terminalWidth, '');
        Console::println('');
        Console::println('');
    }

    public function footer(Experiment $experiment) {
        Console::println('');
        Console::printColumns([
            [20, number_format($experiment->timeElapsed(), 2) . ' sec ', STR_PAD_LEFT, [Color::BG_BLUE, Color::WHITE, Color::BOLD]],
            [$this->flex(20, 50), ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
            [50, ' ' . $experiment->usage()->toString() . ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
        ], $this->terminalWidth, '');
        Console::println('');
        Console::println('');
        $this->displayObservations($experiment);
        Console::println('');
    }

    public function before(Execution $execution) : void {
        $connection = $execution->get('case.connection');
        $mode = $execution->get('case.mode')->value;
        $streamed = $execution->get('case.isStreamed');

        Console::printColumns([
            [10, $connection, STR_PAD_RIGHT, Color::WHITE],
            [11, $mode, STR_PAD_RIGHT, Color::YELLOW],
            [8, $streamed ? 'stream' : 'sync', STR_PAD_LEFT, $streamed ? Color::BLUE : Color::DARK_BLUE],
        ], $this->terminalWidth);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
    }

    public function after(Execution $execution) : void {
        if ($execution->hasException()) {
            $this->displayException($execution->exception());
        } else {
            $this->displayResult($execution);
        }
        echo "\n";
    }

    public function displayExceptions(array $exceptions) : void {
        Console::println('');
        Console::println(' EXCEPTIONS ', [Color::BG_MAGENTA, Color::WHITE, Color::BOLD]);
        foreach($exceptions as $key => $exception) {
            $exLine = str_replace("\n", '\n', $exception);
            Console::printColumns([
                [30, $key, STR_PAD_RIGHT, [Color::DARK_YELLOW]],
                [100, $exLine, STR_PAD_RIGHT, [Color::WHITE]]
            ], $this->terminalWidth);
            Console::println('');
            Console::println($exception->getMessage(), [Color::GRAY]);
            if (Debug::isEnabled()) {
                Console::println($exception->getTraceAsString(), [Color::DARK_GRAY]);
            }
        }
        Console::println('');
    }

    // INTERNAL /////////////////////////////////////////////////

    private function displayResult(Execution $execution) : void {
        $answer = $execution->get('output.notes');
        $answerLine = str_replace("\n", '\n', $answer);
        $timeElapsed = $execution->timeElapsed();
        $tokensPerSec = $execution->outputTps();
        $isCorrect = SelectObservations::from($execution->observations())->withKeys(['execution.is_correct'])->sole()->value();

        $rowStatus = match($isCorrect) {
            1 => 'OK',
            0 => 'FAIL',
            default => '????',
        };
        $cliColor = match($isCorrect) {
            1 => [Color::BG_GREEN, Color::WHITE],
            0 => [Color::BG_RED, Color::WHITE],
            default => [Color::BG_BLACK, Color::RED],
        };

        $columns = [
            [9, $this->timeFormat($timeElapsed), STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [10, $this->tokensPerSecFormat($tokensPerSec), STR_PAD_LEFT, [Color::CYAN]],
            [6, $rowStatus, STR_PAD_BOTH, $cliColor],
            [60, $answerLine, STR_PAD_RIGHT, [Color::WHITE, Color::BG_BLACK]]
        ];

        echo Console::columns($columns, $this->terminalWidth);
    }

    private function displayException(Exception $exception) : void {
        echo Console::columns([
            [9, '', STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [10, '', STR_PAD_LEFT, [Color::CYAN]],
            [6, '!!!!', STR_PAD_BOTH, [Color::WHITE, COLOR::BOLD, Color::BG_MAGENTA]],
            [60, $this->exceptionToText($exception, 80), STR_PAD_RIGHT, [Color::RED, Color::BG_BLACK]],
        ], $this->terminalWidth);
    }


    private function timeFormat(float $time) : string {
        return number_format($time, 2)
            . ' sec';
    }

    private function tokensPerSecFormat(float $time) : string {
        return number_format($time, 1)
            . ' t/s';
    }

    private function exceptionToText(Exception $e, int $maxLen) : string {
        return ' '
            . substr(str_replace("\n", '\n', $e->getMessage()), 0, $maxLen)
            . '...';
    }

    private function displayObservations(Experiment $experiment)
    {
        Console::println('SUMMARY:', [Color::WHITE, Color::BOLD]);
        foreach ($experiment->observations() as $observation) {
            //$format = $observation->metadata()->get('format', '%s');
            $value = $observation->value();
            $unit = $observation->metadata()->get('unit', '-');
            $meta = Str::limit($observation->metadata()->except('experimentId')->toJson(), 60);

            Console::printColumns([
                [5, $observation->id(), STR_PAD_LEFT, [Color::DARK_GRAY]],
                [25, $observation->key(), STR_PAD_LEFT, [Color::DARK_GRAY]],
                [20, $value, STR_PAD_LEFT, [Color::WHITE]],
                [10, $unit, STR_PAD_RIGHT, [Color::DARK_GRAY]],
                [$this->flex(5,25,20,10), $meta, STR_PAD_RIGHT, [Color::GRAY]],
            ], $this->terminalWidth);
            Console::println('');
        }
    }

    private function flex(int ...$cols) : int {
        $flex = 0;
        foreach ($cols as $col) {
            $flex += $col;
        }
        $count = count($cols) + 1;
        return $this->terminalWidth - $flex - $count;
    }
}