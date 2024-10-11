<?php

namespace Cognesy\Instructor\Extras\Evals\Console;

use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;

class Display
{
    public function before(Experiment $experiment) : void {
        $connection = $experiment->connection;
        $mode = $experiment->mode->value;
        $streamed = $experiment->isStreamed;

        echo Console::columns([
            [10, $connection, STR_PAD_RIGHT, Color::WHITE],
            [11, $mode, STR_PAD_RIGHT, Color::YELLOW],
            [8, $streamed ? 'stream' : 'sync', STR_PAD_LEFT, $streamed ? Color::BLUE : Color::DARK_BLUE],
        ], 80);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
    }

    public function after(Experiment $experiment) : void {
        $answer = $experiment->notes;
        $answerLine = str_replace("\n", '\n', $answer);
        $metric = $experiment->metric;
        $timeElapsed = $experiment->timeElapsed;
        $tokensPerSec = $experiment->outputTps();
        $exception = $experiment->exception;

        if ($exception) {
            //Console::print('          ');
            //Console::print(' !!!! ', [Color::RED, Color::BG_BLACK]);
            //Console::println(, [Color::RED, Color::BG_BLACK]);
            echo Console::columns([
                [9, '', STR_PAD_LEFT, [Color::DARK_YELLOW]],
                [10, '', STR_PAD_LEFT, [Color::CYAN]],
                [6, '!!!!', STR_PAD_BOTH, [Color::WHITE, COLOR::BOLD, Color::BG_MAGENTA]],
                [60, $this->exc2txt($exception, 80), STR_PAD_RIGHT, [Color::RED, Color::BG_BLACK]],
            ], 120);
        } else {
            echo Console::columns([
                [9, $this->timeFormat($timeElapsed), STR_PAD_LEFT, [Color::DARK_YELLOW]],
                [10, $this->tokensPerSecFormat($tokensPerSec), STR_PAD_LEFT, [Color::CYAN]],
                [6, $metric->toString(), STR_PAD_BOTH, $metric->toCliColor()],
                [60, $answerLine, STR_PAD_RIGHT, [Color::WHITE, Color::BG_BLACK]],
            ], 120);
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

    private function timeFormat(float $time) : string {
        return number_format($time, 2)
            . ' sec';
    }

    private function tokensPerSecFormat(float $time) : string {
        return number_format($time, 1)
            . ' t/s';
    }

    private function exc2txt(Exception $e, int $maxLen) : string {
        return ' '
            . substr(str_replace("\n", '\n', $e->getMessage()), 0, $maxLen)
            . '...';
    }
}