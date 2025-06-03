<?php

namespace Cognesy\Evals\Console;

use Cognesy\Evals\Execution;
use Cognesy\Evals\Experiment;
use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use Cognesy\Utils\Str;
use Exception;

class Display
{
    private int $terminalWidth = 120;

    public function __construct(array $options = []) {
        $this->terminalWidth = Console::getWidth();
    }

    public function header(Experiment $experiment) : void {
        $id = $experiment->id();
        $title = ' EXPERIMENT (' . Str::limit(
            text: $id,
            limit: 4,
            cutMarker: '',
            align: STR_PAD_LEFT,
            fit: false
        ) . ") ";
        $startedAt = ' ' . $experiment->startedAt()->format('Y-m-d H:i:s') . ' ';

        Console::println('');
        Console::printColumns([
            [22, $title, STR_PAD_RIGHT, [Color::BG_BLUE, Color::WHITE, Color::BOLD]],
            [$this->flex(22, 30, -2), ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
            [30, $startedAt, STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
        ], $this->terminalWidth, '');
        Console::println('');
        Console::println('');
    }

    public function footer(Experiment $experiment) {
        $title = ' SUMMARY ';
        $info = ' Time: ' . number_format($experiment->timeElapsed(), 2) . ' sec '
            . '  ' . $experiment->usage()->toString() . ' ';

        Console::println('');
        Console::printColumns([
            [20, $title, STR_PAD_RIGHT, [Color::BG_BLUE, Color::WHITE, Color::BOLD]],
            [$this->flex(20, 60), ' ', STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
            [60, $info, STR_PAD_LEFT, [Color::BG_GRAY, Color::DARK_GRAY]],
        ], $this->terminalWidth, '');
        Console::println('');
        $this->displayObservations($experiment);
        Console::println('');
    }

    public function displayExecution(Execution $execution) : void {
        $id = Str::limit(text: $execution->id(), limit: 4, cutMarker: '', align: STR_PAD_LEFT);
        $preset = $execution->get('case.preset');
        $mode = $execution->get('case.mode')->value;
        $streamed = $execution->get('case.isStreamed');
        $streamLabel = $streamed ? 'stream' : 'sync';

        Console::printColumns([
            [5, $id, STR_PAD_LEFT, Color::DARK_GRAY],
            [16, $preset, STR_PAD_RIGHT, Color::WHITE],
            [16, $mode, STR_PAD_RIGHT, Color::YELLOW],
            [8, $streamLabel, STR_PAD_LEFT, $streamed ? Color::BLUE : Color::DARK_BLUE],
        ], $this->terminalWidth);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
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
//            if (Debug::isEnabled()) {
//                Console::println($exception->getTraceAsString(), [Color::DARK_GRAY]);
//            }
        }
        Console::println('');
    }

    // INTERNAL /////////////////////////////////////////////////

    private function displayResult(Execution $execution) : void {
        $answer = $execution->get('output.notes');
        $answerLine = str_replace("\n", '\n', $answer);
        $timeElapsed = $execution->timeElapsed();
        $tokensPerSec = $execution->outputTps();
        $isCorrect = $execution->hasException();

        $rowStatus = match($isCorrect) {
            false => 'DONE',
            true => 'FAIL',
            default => '????',
        };
        $cliColor = match($isCorrect) {
            false => [Color::BG_GREEN, Color::WHITE],
            true => [Color::BG_RED, Color::WHITE],
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
        Console::printColumns([
            [5, 'ID', STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [25, 'KEY', STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [10, 'VALUE', STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [10, 'UNIT', STR_PAD_RIGHT, [Color::DARK_YELLOW]],
            [10, 'AGGR', STR_PAD_RIGHT, [Color::DARK_YELLOW]],
            [$this->flex(5,25,10,10, 10), 'META', STR_PAD_RIGHT, [Color::DARK_YELLOW]],
        ], $this->terminalWidth);

        foreach ($experiment->observations() as $observation) {
            $id = Str::limit(text: $observation->id(), limit: 4, cutMarker: '', align: STR_PAD_LEFT);
            $value = $observation->value();
            $unit = $observation->metadata()->get('unit', '-');
            $format = $observation->metadata()->get('format', '%s');
            $method = $observation->metadata()->get('aggregationMethod', '-');
            $meta = Str::limit(
                text: $observation->metadata()->except('experimentId', 'unit', 'format', 'aggregationMethod')->toJson(),
                limit: 60
            );

            Console::printColumns([
                [5, $id, STR_PAD_LEFT, [Color::DARK_GRAY]],
                [25, $observation->key(), STR_PAD_RIGHT, [Color::GRAY]],
                [10, sprintf($format, $value), STR_PAD_LEFT, [Color::WHITE]],
                [10, $unit, STR_PAD_RIGHT, [Color::DARK_GRAY]],
                [10, $method, STR_PAD_RIGHT, [Color::DARK_GRAY]],
                [$this->flex(5,25,10,10, 10), $meta, STR_PAD_RIGHT, [Color::GRAY]],
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