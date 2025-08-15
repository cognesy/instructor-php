<?php

declare(strict_types=1);

namespace Cognesy\Doctor\Freeze;

class FreezeConfig
{
    public const THEME_BASE = 'base';
    public const THEME_FULL = 'full';
    public const THEME_DRACULA = 'dracula';
    public const THEME_GITHUB = 'github';
    public const THEME_MONOKAI = 'monokai';
    public const THEME_NORD = 'nord';
    public const THEME_SOLARIZED_DARK = 'solarized-dark';
    public const THEME_SOLARIZED_LIGHT = 'solarized-light';

    public const CONFIG_BASE = 'base';
    public const CONFIG_FULL = 'full';
    public const CONFIG_USER = 'user';

    public const FORMAT_SVG = 'svg';
    public const FORMAT_PNG = 'png';
    public const FORMAT_WEBP = 'webp';
    public const FORMAT_JPG = 'jpg';

    public static function themes(): array {
        return [
            self::THEME_BASE,
            self::THEME_FULL,
            self::THEME_DRACULA,
            self::THEME_GITHUB,
            self::THEME_MONOKAI,
            self::THEME_NORD,
            self::THEME_SOLARIZED_DARK,
            self::THEME_SOLARIZED_LIGHT,
        ];
    }

    public static function configs(): array {
        return [
            self::CONFIG_BASE,
            self::CONFIG_FULL,
            self::CONFIG_USER,
        ];
    }

    public static function formats(): array {
        return [
            self::FORMAT_SVG,
            self::FORMAT_PNG,
            self::FORMAT_WEBP,
            self::FORMAT_JPG,
        ];
    }
}