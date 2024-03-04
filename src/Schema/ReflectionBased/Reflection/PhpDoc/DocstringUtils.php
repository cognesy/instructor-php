<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\PhpDoc;

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

    static public function getPhpDocType(string $type) : array {
        $isResolved = false;
        $keyTypeName = '';
        $valueTypeName = '';

        // case 1: array<> style type definition
        if (str_starts_with($type, 'array<')) {
            if (str_contains($type, ',')) {
                // extract types from "array<keyType,valueType>" type definition
                $typeData = explode(',', substr($type, 6, -1));
                $keyTypeName = $typeData[0];
                $valueTypeName = $typeData[1];
            } else {
                // extract types from "array<valueType>" type definition
                $keyTypeName = 'int';
                $valueTypeName = substr($type, 6, -1);
            }
            $isResolved = true;
        }

        // case 2: itemType[] style type definition
        if (str_ends_with($type, '[]')) {
            // extract type from "valueType[]" type definition
            $keyTypeName = 'int';
            $valueTypeName = substr($type, 0, -2);
            $isResolved = true;
        }

        // remove leading backslash from type name
        if (str_starts_with($valueTypeName, '\\')) {
            $valueTypeName = substr($valueTypeName, 1);
        }

        return [$isResolved, $keyTypeName, $valueTypeName];
    }
}
