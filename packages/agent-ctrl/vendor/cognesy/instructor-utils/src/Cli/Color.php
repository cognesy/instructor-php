<?php declare(strict_types=1);

namespace Cognesy\Utils\Cli;

class Color
{
    public const DARK_GRAY    = "\033[1;30m";
    public const RED          = "\033[1;31m";
    public const GREEN        = "\033[1;32m";
    public const YELLOW       = "\033[1;33m";
    public const BLUE         = "\033[1;34m";
    public const MAGENTA      = "\033[1;35m";
    public const CYAN         = "\033[1;36m";
    public const WHITE        = "\033[1;37m";

    public const BLACK        = "\033[0;30m";
    public const DARK_RED     = "\033[0;31m";
    public const DARK_GREEN   = "\033[0;32m";
    public const DARK_YELLOW  = "\033[0;33m";
    public const DARK_BLUE    = "\033[0;34m";
    public const DARK_MAGENTA = "\033[0;35m";
    public const DARK_CYAN    = "\033[0;36m";
    public const GRAY         = "\033[0;37m";

    public const BG_BLACK     = "\033[40m";
    public const BG_RED       = "\033[41m";
    public const BG_GREEN     = "\033[42m";
    public const BG_YELLOW    = "\033[43m";
    public const BG_BLUE      = "\033[44m";
    public const BG_MAGENTA   = "\033[45m";
    public const BG_CYAN      = "\033[46m";
    public const BG_WHITE     = "\033[47m";
    public const BG_GRAY      = "\033[47m";

    //public const BG_RESET     = "\033[49m";

    public const BOLD         = "\033[1m";
    public const ITALICS      = "\033[3m";
    public const RESET        = "\033[0m";
    public const CLEAR        = "\033[2J";
}