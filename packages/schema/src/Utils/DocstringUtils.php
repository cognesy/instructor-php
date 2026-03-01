<?php declare(strict_types=1);

namespace Cognesy\Schema\Utils;

class DocstringUtils
{
    public static function descriptionsOnly(string $docComment) : string {
        if ($docComment === '') {
            return '';
        }

        $docComment = preg_replace('/^\s*\/\*\*|\*\/\s*$/m', '', $docComment) ?? '';
        $lines = preg_split('/\R/', $docComment) ?: [];

        $descriptionLines = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\s*\*\s?/', '', $line) ?? '');
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }
            $descriptionLines[] = $line;
        }

        return trim(implode("\n", $descriptionLines));
    }

    public static function getParameterDescription(string $name, string $docComment) : string {
        if ($docComment === '') {
            return '';
        }

        if (!preg_match('/@param\s+[^\s]+\s+\$?' . preg_quote($name, '/') . '\s*(.*)/', $docComment, $matches)) {
            return '';
        }

        return trim($matches[1]);
    }
}
