<?php

namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Exception;

class Display
{
    public function before(Mode $mode, string $connection, bool $isStreamed) : void {
        echo Console::columns([
            [14, $mode->value, STR_PAD_RIGHT, Color::YELLOW],
            [12, $connection, STR_PAD_RIGHT, Color::WHITE],
            [10, $isStreamed ? 'stream' : 'sync', STR_PAD_LEFT, $isStreamed ? Color::BLUE : Color::DARK_BLUE],
        ], 80);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
    }

    public function after(EvalResponse $evalResponse) : void {
        $answer = $evalResponse->answer;
        $isCorrect = $evalResponse->isCorrect;
        $timeElapsed = $evalResponse->timeElapsed;
        $tokensPerSec = $evalResponse->outputTps();
        $exception = $evalResponse->exception;

        if ($exception) {
            //Console::print('          ');
            //Console::print(' !!!! ', [Color::RED, Color::BG_BLACK]);
            //Console::println(, [Color::RED, Color::BG_BLACK]);
            echo Console::columns([
                [9, '', STR_PAD_LEFT, [Color::DARK_YELLOW]],
                [5, ' !!!!', STR_PAD_RIGHT, [Color::WHITE, COLOR::BOLD, Color::BG_MAGENTA]],
                [60, ' ' . $this->exc2txt($exception, 80), STR_PAD_RIGHT, [Color::RED, Color::BG_BLACK]],
            ], 120);
        } else {
            $answerLine = str_replace("\n", '\n', $answer);
            echo Console::columns([
                [9, $this->timeFormat($timeElapsed), STR_PAD_LEFT, [Color::DARK_YELLOW]],
                [10, $this->tokensPerSecFormat($tokensPerSec), STR_PAD_LEFT, [Color::CYAN]],
                [5, $isCorrect ? '  OK ' : ' FAIL', STR_PAD_RIGHT, $isCorrect ? [Color::BG_GREEN, Color::WHITE] : [Color::BG_RED, Color::WHITE]],
                [60, ' ' . $answerLine, STR_PAD_RIGHT, [Color::WHITE, Color::BG_BLACK]],
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