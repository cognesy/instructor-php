<?php

namespace Cognesy\Instructor\Utils;

class Console
{
    public const BLACK        = "\033[0;30m";
    public const RED          = "\033[1;31m";
    public const GREEN        = "\033[1;32m";
    public const YELLOW       = "\033[1;33m";
    public const BLUE         = "\033[1;34m";
    public const MAGENTA      = "\033[1;35m";
    public const CYAN         = "\033[1;36m";
    public const WHITE        = "\033[1;37m";
    public const GRAY         = "\033[0;37m";
    public const DARK_RED     = "\033[0;31m";
    public const DARK_GREEN   = "\033[0;32m";
    public const DARK_YELLOW  = "\033[0;33m";
    public const DARK_BLUE    = "\033[0;34m";
    public const DARK_MAGENTA = "\033[0;35m";
    public const DARK_CYAN    = "\033[0;36m";
    public const DARK_WHITE   = "\033[0;37m";
    public const DARK_GRAY    = "\033[1;30m";
    public const BG_BLACK     = "\033[40m";
    public const BG_RED       = "\033[41m";
    public const BG_GREEN     = "\033[42m";
    public const BG_YELLOW    = "\033[43m";
    public const BG_BLUE      = "\033[44m";
    public const BG_MAGENTA   = "\033[45m";
    public const BG_CYAN      = "\033[46m";
    public const BG_WHITE     = "\033[47m";
    public const BOLD         = "\033[1m";
    public const ITALICS      = "\033[3m";
    public const RESET        = "\033[0m";

    static function columns(array $columns, int $maxWidth): string {
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
                    $message .= $color;
                }
                $message .= self::toColumn(
                    chars: $row[0],
                    text: $row[1],
                    align: $row[2]??STR_PAD_RIGHT
                );
            }
            $message .= ' ';
        }
        return trim($message);
    }

    static private function toColumn(int $chars, mixed $text, int $align): string {
        $short = ($align == STR_PAD_LEFT)
            ? substr($text, -$chars)
            : substr($text, 0, $chars);
        if ($text != $short) {
            $short = ($align == STR_PAD_LEFT)
                ? '…'.substr($short,1)
                : substr($short, 0, -1).'…';
        }
        return str_pad($short, $chars, ' ', $align);
    }
}