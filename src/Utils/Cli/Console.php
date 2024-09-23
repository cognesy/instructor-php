<?php
namespace Cognesy\Instructor\Utils\Cli;

class Console
{
    public static function print(string $message, string|array $color = ''): void {
        print(self::color($color, $message));
    }

    public static function println(string $message, string|array $color = ''): void {
        print(self::color($color, $message) . PHP_EOL);
    }

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
        if (empty($color)) {
            return $output;
        }
        $colorStr = match(true) {
            is_array($color) => implode('', $color),
            default => $color,
        };
        return match(true) {
            empty($output) => $colorStr,
            default => $colorStr . $output . Color::RESET,
        };
    }
}
