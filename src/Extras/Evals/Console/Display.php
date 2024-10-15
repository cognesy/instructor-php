<?php

namespace Cognesy\Instructor\Extras\Evals\Console;

use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;

class Display
{
    public function before(Execution $execution) : void {
        $connection = $execution->connection;
        $mode = $execution->mode->value;
        $streamed = $execution->isStreamed;

        echo Console::columns([
            [10, $connection, STR_PAD_RIGHT, Color::WHITE],
            [11, $mode, STR_PAD_RIGHT, Color::YELLOW],
            [8, $streamed ? 'stream' : 'sync', STR_PAD_LEFT, $streamed ? Color::BLUE : Color::DARK_BLUE],
        ], 80);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
    }

    public function after(Execution $execution) : void {
        $exception = $execution->exception;
        if ($exception) {
            $this->displayException($execution);
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
            echo Console::columns([
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
        $answer = $execution->notes;
        $answerLine = str_replace("\n", '\n', $answer);
        $timeElapsed = $execution->timeElapsed;
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
        foreach ($execution->evaluations as $evaluation) {
            $value = $evaluation->metric;
            $columns[] = [6, $value->toString(), STR_PAD_BOTH, $value->toCliColor()];
            $count++;
            if ($count >= $maxCols) {
                break;
            }
        }
        return $columns;
    }

    private function displayException(Execution $execution) : void {
        $exception = $execution->exception;
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
}