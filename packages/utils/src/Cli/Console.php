<?php declare(strict_types=1);

namespace Cognesy\Utils\Cli;

use Cognesy\Utils\Str;

class Console
{
    const COLUMN_DIVIDER = ' ';

    public static function print(string $message, string|array $color = ''): void {
        print(self::color($color, $message));
    }

    public static function println(string $message, string|array $color = ''): void {
        print(self::color($color, $message) . PHP_EOL);
    }

    public static function printColumns(array $columns, int $maxWidth, string $divider = ' '): void {
        print(self::columns($columns, $maxWidth, $divider));
    }

    public static function clearScreen(): void {
        print("\033[2J\033[;H");
        //echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
    }

    public static function center(string $message, int $width, string|array $color = ''): string {
        $message = self::color($color, $message);
        $message = str_pad($message, $width, ' ', STR_PAD_BOTH);
        return $message;
    }

    public static function columns(array $columns, int $maxWidth, string $divider = ' '): string {
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
                    width: $row[0],
                    text: $row[1],
                    align: $row[2]??STR_PAD_RIGHT
                );
            }
            $message .= Color::RESET;
            $message .= $divider;
        }
        return $message;
    }

    static private function toColumn(
        int          $width,
        mixed        $text,
        int          $align,
        string|array $color = ''
    ): string {
        $short = Str::limit(text: $text, limit: $width, align: $align);
        return self::color($color, str_pad($short, $width, ' ', $align));
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

    public static function getWidth() : int {
        return (int) exec('tput cols');
    }
}
