<?php
namespace Cognesy\Instructor\Schema\Utils;

use Cognesy\Instructor\Utils\Pipeline;

class DocstringUtils
{
    public static function descriptionsOnly(string $code): string
    {
        return (new Pipeline())
            ->through(fn($code) => self::removeMarkers($code))
            ->through(fn($code) => self::removeAnnotations($code))
            ->then(fn($code) => trim($code))
            ->process($code);
    }

    public static function removeMarkers(string $code): string
    {
        // Pattern to match comment markers
        $pattern = '/(\/\*\*|\*\/|\/\/|#)/';

        // Remove comment markers from the string
        $cleanedString = preg_replace($pattern, '', $code);

        // Optional: Clean up extra asterisks and whitespace from multiline comments
        $cleanedString = preg_replace('/^\s*\*\s?/m', '', $cleanedString);

        return $cleanedString;
    }

    public static function removeAnnotations(string $code): string
    {
        $lines = explode("\n", $code);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }
            if ($trimmed[0] !== '@') {
                $cleanedLines[] = $line;
            }
        }
        return implode("\n", $cleanedLines);
    }
}
