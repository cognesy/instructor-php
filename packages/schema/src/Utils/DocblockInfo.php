<?php declare(strict_types=1);

namespace Cognesy\Schema\Utils;

final class DocblockInfo
{
    public static function summary(string $docComment) : string {
        if ($docComment === '') {
            return '';
        }

        $lines = preg_split('/\R/', $docComment) ?: [];
        $descriptionLines = [];

        foreach ($lines as $line) {
            $cleanLine = trim($line);
            $cleanLine = preg_replace('/^\/\*\*?/', '', $cleanLine) ?? '';
            $cleanLine = preg_replace('/\*\/$/', '', $cleanLine) ?? '';
            $cleanLine = preg_replace('/^\*/', '', $cleanLine) ?? '';
            $cleanLine = trim($cleanLine);

            if ($cleanLine === '' || str_starts_with($cleanLine, '@')) {
                continue;
            }

            $descriptionLines[] = $cleanLine;
        }

        return trim(implode("\n", $descriptionLines));
    }

    public static function parameterDescription(string $docComment, string $parameterName) : string {
        if ($docComment === '') {
            return '';
        }

        if (!preg_match_all('/@param\s+[^$]*\$(\w+)\s*(.*)$/m', $docComment, $matches, PREG_SET_ORDER)) {
            return '';
        }

        foreach ($matches as $match) {
            if ($match[1] !== $parameterName) {
                continue;
            }

            return trim($match[2]);
        }

        return '';
    }
}

