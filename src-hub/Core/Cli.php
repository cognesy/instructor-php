<?php

namespace Cognesy\InstructorHub\Core;

use Cognesy\Instructor\Utils\Console;

class Cli
{
    static public function out(string $output = '', string|array $color = null) {
        if ($color) {
            $colorOut = is_array($color) ? implode('', $color) : $color;
            $output = $colorOut . $output . Color::RESET;
        }
        echo $output;
    }

    static public function outln(string $output = '', string|array $color = null) {
        self::out($output . "\n", $color);
    }

    static public function grid(array $data) {
        Cli::out(Console::columns($data, 80));
    }
}