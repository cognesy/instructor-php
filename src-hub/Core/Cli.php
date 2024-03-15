<?php

namespace Cognesy\InstructorHub\Core;

use Cognesy\Instructor\Utils\Console;
use Cognesy\InstructorHub\Utils\Color;

class Cli
{
    static public function str(string $output = '', string|array $color = null) : string {
        if ($color) {
            $colorOut = is_array($color) ? implode('', $color) : $color;
            $output = $colorOut . $output . Color::RESET;
        }
        return $output;
    }

    static public function strln(string $output = '', string|array $color = null) : string {
        return self::str($output . "\n", $color);
    }

    static public function out(string $output = '', string|array $color = null) : void {
        echo self::str($output, $color);
    }

    static public function outln(string $output = '', string|array $color = null) : void {
        self::out($output . "\n", $color);
    }

    static public function margin(string $output = '', int $size = 3, string|array $mcolor = null, string|array $color = null) : void {
        $margined = self::smargin($output, $size, $mcolor, $color);
        self::out($margined);
    }

    static public function smargin(string $output = '', int $size = 3, string|array $mcolor = null, string|array $color = null) : string {
        $lines = explode("\n", $output);
        $margined = [];
        foreach ($lines as $line) {
            $margined[] = implode('', [
                Cli::str(" |".str_repeat(' ', $size), $mcolor),
                Cli::str($line, $color),
            ]);
        }
        return self::strln(implode("\n", $margined));
    }

    static public function grid(array $data) : void {
        self::out(Console::columns($data, 80));
    }

    public static function limit(string $line, int $param) {
        if (strlen($line) <= $param) {
            return $line;
        }
        $short = substr($line, 0, $param);
        if (strlen($line) > $param) {
            $short .= '...';
        }
        return $short;
    }

    public static function removeColors(string $line) {
        return preg_replace('/\e\[[0-9;]*m/', '', $line);
    }
}
