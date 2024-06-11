<?php

namespace Cognesy\Instructor\Utils;

use Cognesy\InstructorHub\Utils\Color;

class Console
{
    public static function columns(array $columns, int $maxWidth): string {
        $maxWidth = max($maxWidth, 80);
        $message = '';
        foreach ($columns as $row) {
            if (is_string($row)) {
                $message .= $row;
            } else {
                if ($row[0] == -1) {
                    $row[0] = $maxWidth - strlen($message);
                }
                if ($color = $row[3] ?? 0) {
                    $message .= self::color($color);
                }
                $message .= self::toColumn(
                    chars: $row[0],
                    text: $row[1],
                    align: $row[2]??STR_PAD_RIGHT
                );
            }
            $message .= ' ';
        }
        return $message;
    }

    static private function toColumn(int $chars, mixed $text, int $align, string|array $color = ''): string {
        $short = ($align == STR_PAD_LEFT)
            ? substr($text, -$chars)
            : substr($text, 0, $chars);
        if ($text != $short) {
            $short = ($align == STR_PAD_LEFT)
                ? '…'.substr($short,1)
                : substr($short, 0, -1).'…';
        }
        $output = str_pad($short, $chars, ' ', $align);
        $output = self::color($color, $output);
        return $output;
    }

    static private function color(string|array $color, string $output = '') : string {
        if (!$color) {
            return $output;
        }
        if (is_array($color)) {
            $colorStr = implode('', $color);
        } else {
            $colorStr = $color;
        }
        // if no output, then just return the color
        if (!$output) {
            return $colorStr;
        }
        // if output, wrap the output in color and reset
        return $colorStr . $output . Color::RESET;
    }
}