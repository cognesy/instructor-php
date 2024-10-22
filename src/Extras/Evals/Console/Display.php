<?php

namespace Cognesy\Instructor\Extras\Evals\Console;

use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Str;
use Exception;

class Display
{
    public function header(Experiment $experiment) : void {
        Console::println('');
        Console::printColumns([
            [22, ' EXPERIMENT (' . Str::limit(text: $experiment->id(), limit: 4, align: STR_PAD_LEFT, fit: false) . ") ", STR_PAD_RIGHT, [Color::BG_BLUE, Color::WHITE, Color::BOLD]],
            [70, ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
            [30, ' ' . $experiment->startedAt()->format('Y-m-d H:i:s') . ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
        ], 120, '');
        Console::println('');
        Console::println('');
    }

    public function footer(Experiment $experiment) {
        Console::println('');
        Console::printColumns([
            [20, number_format($experiment->timeElapsed(), 2) . ' sec ', STR_PAD_LEFT, [Color::BG_BLUE, Color::WHITE, Color::BOLD]],
            [100, ' ' . $experiment->usage()->toString() . ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
        ], 120, '');
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
        ], 80);
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
            ], 120);
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

        $columns = array_merge([
                [9, $this->timeFormat($timeElapsed), STR_PAD_LEFT, [Color::DARK_YELLOW]],
                [10, $this->tokensPerSecFormat($tokensPerSec), STR_PAD_LEFT, [Color::CYAN]],
            ],
            $this->makeEvalColumns($execution),
            [
                [60, $answerLine, STR_PAD_RIGHT, [Color::WHITE, Color::BG_BLACK]]
            ],
        );

        echo Console::columns($columns, 120);
    }

    private function makeEvalColumns(Execution $execution, int $maxCols = 3) : array {
        $columns = [];
        $count = 0;
        foreach ($execution->summaries() as $aggregate) {
            $columns[] = [6, $metric->toString(), STR_PAD_BOTH, $metric->toCliColor()];
            $count++;
            if ($count >= $maxCols) {
                break;
            }
        }
        foreach ($execution->observations() as $observation) {
            $columns[] = [6, $observation->value(), STR_PAD_BOTH, [Color::GRAY]];
            $count++;
            if ($count >= $maxCols) {
                break;
            }
        }
        return $columns;
    }

    private function displayException(Exception $exception) : void {
        echo Console::columns([
            [9, '', STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [10, '', STR_PAD_LEFT, [Color::CYAN]],
            [6, '!!!!', STR_PAD_BOTH, [Color::WHITE, COLOR::BOLD, Color::BG_MAGENTA]],
            [60, $this->exceptionToText($exception, 80), STR_PAD_RIGHT, [Color::RED, Color::BG_BLACK]],
        ], 120);
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
        Console::println('RESULTS:', [Color::WHITE, Color::BOLD]);
        foreach ($experiment->observations() as $observation) {
            Console::printColumns([
                [20, $observation->key(), STR_PAD_LEFT, [Color::DARK_GRAY]],
                [20, $observation->value(), STR_PAD_RIGHT, [Color::WHITE]],
            ], 120);
            Console::println('');
        }
    }
}