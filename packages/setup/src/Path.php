<?php

namespace Cognesy\Setup;

class Path
{
    public static function resolve(string $path): string {
        if (self::isAbsolute($path)) {
            return $path;
        }

        return getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    private static function isAbsolute(string $path): bool {
        return strpos($path, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}
